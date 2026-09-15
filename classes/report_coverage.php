<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHPUnitChecker info
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 MoodleMootDACH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_phpunitchecker;

use core\exception\moodle_exception;
use stdClass;

/**
 * Renderable output class for PHPUnit clover coverage XML reports.
 *
 * The optional PHPUnit console output is ignored in this class.
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_coverage extends report_output {
    /**
     * The selected components to include in the report, keyed by component name.
     *
     * When empty, every package found in the report is parsed. Otherwise only
     * packages whose component (the first segment of the package name) is a key
     * of this array are kept.
     *
     * @var array<string, bool>
     */
    protected array $suites = [];

    /**
     * The parsed packages from the report.
     *
     * Each entry is an associative array with the following structure:
     * - name:      Package name, for example "core_admin\admin".
     * - component: Component name, the first segment of the package name.
     * - uniqid:    Unique identifier usable as an HTML id.
     * - metrics:   Aggregated coverage metrics for the whole package.
     * - files:     List of files, each with its own metrics, method list and
     *              a list of classes. Each class carries its own metrics and,
     *              where it can be determined, the methods declared in it.
     *
     * @var array
     */
    protected array $packages = [];

    /**
     * Creates a new report output instance.
     *
     * @param string $report Raw clover XML report content.
     * @param string $consoleoutput Not used here.
     * @throws moodle_exception
     */
    public function __construct(string $report, string $consoleoutput = '') {
        if ($report === '') {
            throw new moodle_exception('Report content cannot be empty.');
        }

        $this->report = $report;
        $this->parse_report();
    }

    /**
     * Sets the components to include in the report.
     *
     * The report is re-parsed so the new filter takes effect immediately.
     *
     * @param string[] $suites The selected suite names, for example "core_admin_testsuite".
     * @return self
     */
    public function set_selected_suites(array $suites): self {
        $this->suites = [];

        foreach ($suites as $suite) {
            if (str_ends_with($suite, '_testsuite')) {
                $this->suites[substr($suite, 0, -strlen('_testsuite'))] = true;
            } else {
                $this->suites[$suite] = true;
            }
        }

        $this->parse_report();

        return $this;
    }

    /**
     * Returns the parsed packages.
     *
     * @return array
     */
    public function get_packages(): array {
        return $this->packages;
    }

    /**
     * Parses the clover coverage XML report into {@see self::$packages}.
     *
     * @return void
     */
    protected function parse_report(): void {
        $this->packages = [];

        $dom = new \DOMDocument();

        $oldsetting = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($this->report);
        libxml_clear_errors();
        libxml_use_internal_errors($oldsetting);

        if (!$loaded) {
            return;
        }

        $xpath = new \DOMXPath($dom);

        foreach ($xpath->query('/coverage/project/package') as $packagenode) {
            if (!$packagenode instanceof \DOMElement) {
                continue;
            }

            $package = $this->parse_package($xpath, $packagenode);

            if ($package !== null) {
                $this->packages[] = $package;
            }
        }
    }

    /**
     * Parses one package node.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param \DOMElement $packagenode Package node.
     * @return array|null Parsed package data, or null if the package is filtered out.
     */
    protected function parse_package(\DOMXPath $xpath, \DOMElement $packagenode): ?array {
        $name = $packagenode->attributes->getNamedItem('name')?->nodeValue ?? '';
        $component = explode('\\', $name)[0] ?? '';

        // Apply the optional component filter.
        if (!empty($this->suites) && !array_key_exists($component, $this->suites)) {
            return null;
        }

        $files = [];

        foreach ($xpath->query('file', $packagenode) as $filenode) {
            if (!$filenode instanceof \DOMElement) {
                continue;
            }

            $file = $this->parse_file($xpath, $filenode);

            if ($file !== null) {
                $files[] = $file;
            }
        }

        return [
            'uniqid' => clean_param(md5('package' . $name), PARAM_ALPHANUMEXT),
            'name' => $name,
            'component' => $component,
            'files' => $files,
            'metrics' => $this->aggregate_file_metrics($files),
        ];
    }

    /**
     * Parses one file node.
     *
     * The clover format lists a file's classes first and then all of its lines
     * (statements and methods) as siblings, so methods are not nested inside a
     * class. The method lines are therefore collected at file level; when a file
     * declares a single class they are also attached to that class, which is the
     * common Moodle case and gives a useful "methods in the class" view.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param \DOMElement $filenode File node.
     * @return array|null Parsed file data, or null if the file has no name.
     */
    protected function parse_file(\DOMXPath $xpath, \DOMElement $filenode): ?array {
        $name = $filenode->attributes->getNamedItem('name')?->nodeValue ?? '';

        if ($name === '') {
            return null;
        }

        // Parse the method lines of this file.
        $methods = [];

        foreach ($xpath->query("line[@type='method']", $filenode) as $linenode) {
            if (!$linenode instanceof \DOMElement) {
                continue;
            }

            $methods[] = $this->parse_method($linenode);
        }

        // Parse the classes of this file.
        $classes = [];

        foreach ($xpath->query('class', $filenode) as $classnode) {
            if (!$classnode instanceof \DOMElement) {
                continue;
            }

            $classes[] = $this->parse_class($xpath, $classnode);
        }

        // Attach the methods to the single class of the file where possible.
        if (count($classes) === 1) {
            $classes[0]['methods'] = $methods;
            $classes[0]['hasmethods'] = !empty($methods);
        }

        // The file-level metrics is the direct <metrics> child of the <file> node.
        $metricsnode = $xpath->query('metrics', $filenode)->item(0);

        return [
            'uniqid' => clean_param(md5('file' . $name), PARAM_ALPHANUMEXT),
            'name' => $name,
            'shortname' => basename($name),
            'classes' => $classes,
            'methods' => $methods,
            'hasmethods' => !empty($methods),
            'metrics' => $this->parse_metrics($metricsnode instanceof \DOMElement ? $metricsnode : null),
        ];
    }

    /**
     * Parses one class node.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param \DOMElement $classnode Class node.
     * @return array Parsed class data.
     */
    protected function parse_class(\DOMXPath $xpath, \DOMElement $classnode): array {
        $name = $classnode->attributes->getNamedItem('name')?->nodeValue ?? '';
        $namespace = $classnode->attributes->getNamedItem('namespace')?->nodeValue ?? '';

        $metricsnode = $xpath->query('metrics', $classnode)->item(0);

        return [
            'uniqid' => clean_param(md5('class' . $name . $namespace), PARAM_ALPHANUMEXT),
            'name' => $name,
            'shortname' => $this->get_short_classname($name),
            'namespace' => $namespace,
            'methods' => [],
            'hasmethods' => false,
            'metrics' => $this->parse_metrics($metricsnode instanceof \DOMElement ? $metricsnode : null),
        ];
    }

    /**
     * Parses one method line node.
     *
     * @param \DOMElement $linenode Line node of type "method".
     * @return array Parsed method data.
     */
    protected function parse_method(\DOMElement $linenode): array {
        $attributes = $linenode->attributes;

        $count = (int)($attributes->getNamedItem('count')?->nodeValue ?? 0);

        return [
            'name' => $attributes->getNamedItem('name')?->nodeValue ?? '',
            'line' => (int)($attributes->getNamedItem('num')?->nodeValue ?? 0),
            'visibility' => $attributes->getNamedItem('visibility')?->nodeValue ?? '',
            'complexity' => (int)($attributes->getNamedItem('complexity')?->nodeValue ?? 0),
            'crap' => $attributes->getNamedItem('crap')?->nodeValue ?? '',
            'count' => $count,
            'covered' => $count > 0,
        ];
    }

    /**
     * Parses a <metrics> element into an associative array.
     *
     * A percentage of covered elements is added for convenient display.
     *
     * @param \DOMElement|null $metricsnode Metrics node, or null when absent.
     * @return array
     */
    protected function parse_metrics(?\DOMElement $metricsnode): array {
        $keys = [
            'files',
            'loc',
            'ncloc',
            'classes',
            'complexity',
            'methods',
            'coveredmethods',
            'conditionals',
            'coveredconditionals',
            'statements',
            'coveredstatements',
            'elements',
            'coveredelements',
        ];

        $metrics = [];

        foreach ($keys as $key) {
            $value = $metricsnode?->attributes->getNamedItem($key)?->nodeValue;
            $metrics[$key] = $value === null ? 0 : (int)$value;
        }

        $metrics['coverage'] = $this->coverage_percentage($metrics['coveredelements'], $metrics['elements']);

        return $metrics;
    }

    /**
     * Aggregates the metrics of a list of files into a single metrics array.
     *
     * The clover format has no metrics element on the package node, so package
     * metrics are summed up from the file metrics.
     *
     * @param array $files Parsed files.
     * @return array
     */
    protected function aggregate_file_metrics(array $files): array {
        $metrics = [
            'files' => count($files),
            'loc' => 0,
            'ncloc' => 0,
            'classes' => 0,
            'complexity' => 0,
            'methods' => 0,
            'coveredmethods' => 0,
            'conditionals' => 0,
            'coveredconditionals' => 0,
            'statements' => 0,
            'coveredstatements' => 0,
            'elements' => 0,
            'coveredelements' => 0,
        ];

        foreach ($files as $file) {
            foreach ($file['metrics'] as $key => $value) {
                if ($key === 'files' || $key === 'coverage' || !isset($metrics[$key])) {
                    continue;
                }

                $metrics[$key] += $value;
            }
        }

        $metrics['coverage'] = $this->coverage_percentage($metrics['coveredelements'], $metrics['elements']);

        return $metrics;
    }

    /**
     * Returns the covered percentage rounded to two decimals.
     *
     * @param int $covered Number of covered elements.
     * @param int $total Total number of elements.
     * @return float
     */
    protected function coverage_percentage(int $covered, int $total): float {
        if ($total <= 0) {
            return 0.0;
        }

        return round($covered / $total * 100, 2);
    }

    /**
     * Returns display data describing a coverage ratio.
     *
     * The colour classes follow the usual traffic-light convention so a reader
     * can grasp at a glance how much of the code is covered by tests. Ratios
     * without any measurable element are reported as "no data".
     *
     * @param int $covered Number of covered items.
     * @param int $total Total number of items.
     * @return array
     */
    protected function coverage_status(int $covered, int $total): array {
        if ($total <= 0) {
            return [
                'covered' => 0,
                'total' => 0,
                'percentage' => 0.0,
                'hasdata' => false,
                'barclass' => 'bg-secondary',
                'textclass' => 'text-muted',
            ];
        }

        $percentage = $this->coverage_percentage($covered, $total);

        if ($percentage >= 80) {
            $barclass = 'bg-success';
            $textclass = 'text-success';
        } else if ($percentage >= 50) {
            $barclass = 'bg-warning';
            $textclass = 'text-warning';
        } else {
            $barclass = 'bg-danger';
            $textclass = 'text-danger';
        }

        return [
            'covered' => $covered,
            'total' => $total,
            'percentage' => $percentage,
            'hasdata' => true,
            'barclass' => $barclass,
            'textclass' => $textclass,
        ];
    }

    /**
     * Builds the labelled coverage bars (elements, methods, statements) for a metrics set.
     *
     * @param array $metrics Metrics array as produced by {@see self::parse_metrics()}.
     * @return array
     */
    protected function build_coverage_bars(array $metrics): array {
        return [
            ['label' => get_string('elements', 'tool_phpunitchecker')]
                + $this->coverage_status($metrics['coveredelements'], $metrics['elements']),
            ['label' => get_string('methods', 'tool_phpunitchecker')]
                + $this->coverage_status($metrics['coveredmethods'], $metrics['methods']),
            ['label' => get_string('statements', 'tool_phpunitchecker')]
                + $this->coverage_status($metrics['coveredstatements'], $metrics['statements']),
        ];
    }

    /**
     * Sums a list of package metrics into a single overall metrics array.
     *
     * @param array $packages Parsed packages.
     * @return array
     */
    protected function aggregate_package_metrics(array $packages): array {
        $keys = [
            'files',
            'loc',
            'ncloc',
            'classes',
            'complexity',
            'methods',
            'coveredmethods',
            'conditionals',
            'coveredconditionals',
            'statements',
            'coveredstatements',
            'elements',
            'coveredelements',
        ];

        $metrics = array_fill_keys($keys, 0);

        foreach ($packages as $package) {
            foreach ($keys as $key) {
                $metrics[$key] += $package['metrics'][$key] ?? 0;
            }
        }

        $metrics['coverage'] = $this->coverage_percentage($metrics['coveredelements'], $metrics['elements']);

        return $metrics;
    }

    /**
     * Exports one method for the template.
     *
     * @param array $method Parsed method data.
     * @return array
     */
    protected function export_method(array $method): array {
        return $method + [
            'icon' => $method['covered'] ? '✓' : '✗',
            'badgeclass' => $method['covered'] ? 'badge bg-success' : 'badge bg-danger',
            'rowclass' => $method['covered'] ? 'text-success' : 'text-danger',
            'statustext' => $method['covered']
                ? get_string('methodcovered', 'tool_phpunitchecker')
                : get_string('methoduncovered', 'tool_phpunitchecker'),
        ];
    }

    /**
     * Exports one class for the template.
     *
     * @param array $class Parsed class data.
     * @return array
     */
    protected function export_class(array $class): array {
        $status = $this->coverage_status($class['metrics']['coveredelements'], $class['metrics']['elements']);

        return [
            'uniqid' => $class['uniqid'],
            'name' => $class['name'],
            'shortname' => $class['shortname'],
            'namespace' => $class['namespace'],
            'hasmethods' => $class['hasmethods'],
            'methods' => array_map([$this, 'export_method'], $class['methods']),
            'coveredmethods' => $class['metrics']['coveredmethods'],
            'totalmethods' => $class['metrics']['methods'],
            'percentage' => $status['percentage'],
            'hasdata' => $status['hasdata'],
            'barclass' => $status['barclass'],
            'textclass' => $status['textclass'],
            'summaryitems' => $this->build_coverage_bars($class['metrics']),
        ];
    }

    /**
     * Exports one file for the template.
     *
     * @param array $file Parsed file data.
     * @return array
     */
    protected function export_file(array $file): array {
        $status = $this->coverage_status($file['metrics']['coveredelements'], $file['metrics']['elements']);

        // Methods are only shown at file level when they could not be attached to a single class.
        $hasownmethods = !empty($file['methods']) && count($file['classes']) !== 1;

        return [
            'uniqid' => $file['uniqid'],
            'name' => $file['name'],
            'shortname' => $file['shortname'],
            'classes' => array_map([$this, 'export_class'], $file['classes']),
            'hasownmethods' => $hasownmethods,
            'methods' => $hasownmethods ? array_map([$this, 'export_method'], $file['methods']) : [],
            'coveredmethods' => $file['metrics']['coveredmethods'],
            'totalmethods' => $file['metrics']['methods'],
            'percentage' => $status['percentage'],
            'hasdata' => $status['hasdata'],
            'barclass' => $status['barclass'],
            'textclass' => $status['textclass'],
            'summaryitems' => $this->build_coverage_bars($file['metrics']),
        ];
    }

    /**
     * Exports one package for the template.
     *
     * @param array $package Parsed package data.
     * @return array
     */
    protected function export_package(array $package): array {
        $status = $this->coverage_status($package['metrics']['coveredelements'], $package['metrics']['elements']);

        return [
            'uniqid' => $package['uniqid'],
            'name' => $package['name'],
            'component' => $package['component'],
            'files' => array_map([$this, 'export_file'], $package['files']),
            'filecount' => $package['metrics']['files'],
            'classcount' => $package['metrics']['classes'],
            'coveredmethods' => $package['metrics']['coveredmethods'],
            'totalmethods' => $package['metrics']['methods'],
            'percentage' => $status['percentage'],
            'hasdata' => $status['hasdata'],
            'barclass' => $status['barclass'],
            'textclass' => $status['textclass'],
            'summaryitems' => $this->build_coverage_bars($package['metrics']),
        ];
    }

    /**
     * Exports data for the Moodle Mustache template.
     *
     * @param mixed $output Moodle renderer.
     * @return stdClass
     */
    public function export_for_template($output): stdClass {
        $data = new stdClass();

        $packages = array_map([$this, 'export_package'], $this->packages);

        // Sort packages by name for a stable, readable order.
        usort($packages, static fn($a, $b) => strcmp($a['name'], $b['name']));

        $overall = $this->aggregate_package_metrics($this->packages);
        $status = $this->coverage_status($overall['coveredelements'], $overall['elements']);

        $data->hasdata = !empty($packages);
        $data->packages = $packages;

        $data->percentage = $status['percentage'];
        $data->barclass = $status['barclass'];
        $data->textclass = $status['textclass'];
        $data->statusclass = $status['hasdata'] && $status['percentage'] >= 80 ? 'alert-success' : 'alert-info';

        $data->filecount = $overall['files'];
        $data->classcount = $overall['classes'];
        $data->coveredmethods = $overall['coveredmethods'];
        $data->totalmethods = $overall['methods'];

        $data->summaryitems = $this->build_coverage_bars($overall);

        return $data;
    }
}