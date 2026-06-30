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

namespace tool_phpunitchecker;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Renderable output class for PHPUnit JUnit XML reports.
 *
 * This class accepts a PHPUnit JUnit XML report and converts it into a Moodle
 * template context.
 *
 * @package    tool_phpunitchecker
 */
class report_output implements renderable, templatable {

    /** @var string Passed test status. */
    protected const STATUS_PASSED = 'passed';

    /** @var string Failed test status. */
    protected const STATUS_FAILED = 'failed';

    /** @var string Error test status. */
    protected const STATUS_ERROR = 'error';

    /** @var string Warning test status. */
    protected const STATUS_WARNING = 'warning';

    /** @var string Risky test status. */
    protected const STATUS_RISKY = 'risky';

    /** @var string Skipped test status. */
    protected const STATUS_SKIPPED = 'skipped';

    /** @var string Incomplete test status. */
    protected const STATUS_INCOMPLETE = 'incomplete';

    /** @var string Deprecated test status. */
    protected const STATUS_DEPRECATED = 'deprecated';

    /**
     * Raw JUnit XML report content.
     *
     * @var string
     */
    protected string $report;

    /**
     * Main PHPUnit test suite name, for example mod_grouptool_testsuite.
     *
     * @var string
     */
    protected string $testsuitename = '';

    /**
     * Parsed test class suites.
     *
     * @var array
     */
    protected array $suites = [];

    /**
     * Number of passed tests.
     *
     * @var int
     */
    protected int $passed = 0;

    /**
     * Number of failed tests.
     *
     * @var int
     */
    protected int $failed = 0;

    /**
     * Number of tests with errors.
     *
     * @var int
     */
    protected int $errors = 0;

    /**
     * Number of tests with warnings.
     *
     * @var int
     */
    protected int $warnings = 0;

    /**
     * Number of risky tests.
     *
     * @var int
     */
    protected int $risky = 0;

    /**
     * Number of skipped tests.
     *
     * @var int
     */
    protected int $skipped = 0;

    /**
     * Number of incomplete tests.
     *
     * @var int
     */
    protected int $incomplete = 0;

    /**
     * Number of deprecated tests.
     *
     * @var int
     */
    protected int $deprecated = 0;

    /**
     * Total number of tests.
     *
     * @var int
     */
    protected int $total = 0;

    /**
     * Whether all tests passed.
     *
     * @var bool
     */
    protected bool $allpassed = false;

    /**
     * Creates a new report output instance.
     *
     * @param string $report Raw JUnit XML report content.
     */
    public function __construct(string $report) {
        $this->report = $report;
        $this->parse_report();
    }

    /**
     * Parses the JUnit XML report.
     *
     * @return void
     */
    protected function parse_report(): void {
        $dom = new \DOMDocument();

        $oldsetting = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($this->report);
        libxml_clear_errors();
        libxml_use_internal_errors($oldsetting);

        if (!$loaded) {
            return;
        }

        $xpath = new \DOMXPath($dom);

        $mainnode = $this->find_main_testsuite($xpath);

        if ($mainnode !== null) {
            $this->testsuitename = $mainnode->attributes->getNamedItem('name')?->nodeValue ?? '';
            $this->parse_child_testsuites($xpath, $mainnode);
        } else {
            $this->parse_child_testsuites($xpath, $dom->documentElement);
        }

        $this->total = $this->passed
            + $this->failed
            + $this->errors
            + $this->warnings
            + $this->risky
            + $this->skipped
            + $this->incomplete
            + $this->deprecated;

        $this->allpassed = $this->total > 0
            && $this->failed === 0
            && $this->errors === 0
            && $this->warnings === 0
            && $this->risky === 0
            && $this->skipped === 0
            && $this->incomplete === 0
            && $this->deprecated === 0;
    }

    /**
     * Finds the main Moodle PHPUnit testsuite.
     *
     * The JUnit XML can contain wrapper suites such as /var/www/html/phpunit.xml.
     * The real Moodle testsuite is usually named like mod_grouptool_testsuite
     * or mod_url_testsuite.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @return \DOMElement|null Main testsuite node.
     */
    protected function find_main_testsuite(\DOMXPath $xpath): ?\DOMElement {
        foreach ($xpath->query('//testsuite') as $testsuite) {
            if (!$testsuite instanceof \DOMElement) {
                continue;
            }

            $name = $testsuite->attributes->getNamedItem('name')?->nodeValue ?? '';

            if (preg_match('/^[a-z]+_[a-z0-9_]+_testsuite$/', $name)) {
                return $testsuite;
            }
        }

        return null;
    }

    /**
     * Parses all direct child testsuites which contain testcases.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param \DOMNode|null $parent Parent node.
     * @return void
     */
    protected function parse_child_testsuites(\DOMXPath $xpath, ?\DOMNode $parent): void {
        if ($parent === null) {
            return;
        }

        foreach ($xpath->query('./testsuite[testcase]', $parent) as $testsuite) {
            if (!$testsuite instanceof \DOMElement) {
                continue;
            }

            $this->parse_testclass_suite($xpath, $testsuite);
        }

        // Fallback: if the selected parent itself already contains testcases.
        if ($parent instanceof \DOMElement && $parent->getElementsByTagName('testcase')->length > 0) {
            foreach ($xpath->query('.//testsuite[testcase]', $parent) as $testsuite) {
                if (!$testsuite instanceof \DOMElement) {
                    continue;
                }

                $alreadyparsed = false;
                $classname = $testsuite->attributes->getNamedItem('name')?->nodeValue ?? '';

                foreach ($this->suites as $suite) {
                    if ($suite['classname'] === $classname) {
                        $alreadyparsed = true;
                        break;
                    }
                }

                if (!$alreadyparsed) {
                    $this->parse_testclass_suite($xpath, $testsuite);
                }
            }
        }
    }

    /**
     * Parses one test class suite.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param \DOMElement $testsuite Test class suite node.
     * @return void
     */
    protected function parse_testclass_suite(\DOMXPath $xpath, \DOMElement $testsuite): void {
        $classname = $testsuite->attributes->getNamedItem('name')?->nodeValue ?? '';
        $file = $testsuite->attributes->getNamedItem('file')?->nodeValue ?? '';

        if ($classname === '') {
            $classname = get_string('unknownsuite', 'tool_phpunitchecker');
        }

        $suiteindex = $this->add_suite(
            $this->get_short_classname($classname),
            $classname,
            $file
        );

        foreach ($xpath->query('./testcase', $testsuite) as $testcase) {
            if (!$testcase instanceof \DOMElement) {
                continue;
            }

            $testname = $testcase->attributes->getNamedItem('name')?->nodeValue ?? '';
            $testfile = $testcase->attributes->getNamedItem('file')?->nodeValue ?? $file;
            $line = $testcase->attributes->getNamedItem('line')?->nodeValue ?? '';
            $time = $testcase->attributes->getNamedItem('time')?->nodeValue ?? '';
            $assertions = $testcase->attributes->getNamedItem('assertions')?->nodeValue ?? '';

            $statusinfo = $this->get_status_from_junit_testcase($testcase);

            $this->add_test(
                $suiteindex,
                $this->humanise_test_name($testname),
                $statusinfo['status'],
                $statusinfo['type'],
                $statusinfo['details'],
                $testfile,
                $line,
                $time,
                $assertions
            );
        }

        $this->finalise_suite($suiteindex);
    }

    /**
     * Adds a suite and returns its index.
     *
     * @param string $name Suite display name.
     * @param string $classname Full class name.
     * @param string $file Optional file path.
     * @return int Suite index.
     */
    protected function add_suite(string $name, string $classname, string $file = ''): int {
        $this->suites[] = [
            'uniqid' => clean_param(md5($classname . $file . count($this->suites)), PARAM_ALPHANUMEXT),
            'name' => $name,
            'classname' => $classname,
            'file' => $file,
            'hasfile' => $file !== '',
            'tests' => [],
            'passed' => 0,
            'failed' => 0,
            'errors' => 0,
            'warnings' => 0,
            'risky' => 0,
            'skipped' => 0,
            'incomplete' => 0,
            'deprecated' => 0,
            'total' => 0,
            'allpassed' => false,
            'hasproblems' => false,
            'statusclass' => '',
            'headerclass' => '',
        ];

        return count($this->suites) - 1;
    }

    /**
     * Adds a parsed test case to a suite.
     *
     * @param int|null $suiteindex Index of the current suite.
     * @param string $name Test name.
     * @param string $status Test status.
     * @param string $type Optional problem type.
     * @param string $details Optional problem details.
     * @param string $file Optional test file.
     * @param string $line Optional line number.
     * @param string $time Optional test runtime.
     * @param string $assertions Optional number of assertions.
     * @return void
     */
    protected function add_test(
        ?int $suiteindex,
        string $name,
        string $status,
        string $type = '',
        string $details = '',
        string $file = '',
        string $line = '',
        string $time = '',
        string $assertions = ''
    ): void {
        if ($suiteindex === null || !isset($this->suites[$suiteindex])) {
            return;
        }

        $statusdata = $this->get_status_data($status);

        $this->suites[$suiteindex]['tests'][] = [
            'name' => $name,
            'status' => $status,
            'passed' => $status === self::STATUS_PASSED,
            'hasproblem' => $status !== self::STATUS_PASSED,
            'statusclass' => $statusdata['class'],
            'badgeclass' => $statusdata['badgeclass'],
            'statusicon' => $statusdata['icon'],
            'statustext' => $statusdata['text'],
            'type' => $type,
            'hastype' => $type !== '',
            'details' => $details,
            'hasdetails' => $details !== '',
            'file' => $file,
            'hasfile' => $file !== '',
            'line' => $line,
            'hasline' => $line !== '',
            'time' => $time,
            'hastime' => $time !== '',
            'assertions' => $assertions,
            'hasassertions' => $assertions !== '',
        ];

        $this->suites[$suiteindex]['total']++;

        switch ($status) {
            case self::STATUS_PASSED:
                $this->suites[$suiteindex]['passed']++;
                $this->passed++;
                break;

            case self::STATUS_FAILED:
                $this->suites[$suiteindex]['failed']++;
                $this->failed++;
                break;

            case self::STATUS_ERROR:
                $this->suites[$suiteindex]['errors']++;
                $this->errors++;
                break;

            case self::STATUS_WARNING:
                $this->suites[$suiteindex]['warnings']++;
                $this->warnings++;
                break;

            case self::STATUS_RISKY:
                $this->suites[$suiteindex]['risky']++;
                $this->risky++;
                break;

            case self::STATUS_SKIPPED:
                $this->suites[$suiteindex]['skipped']++;
                $this->skipped++;
                break;

            case self::STATUS_INCOMPLETE:
                $this->suites[$suiteindex]['incomplete']++;
                $this->incomplete++;
                break;

            case self::STATUS_DEPRECATED:
                $this->suites[$suiteindex]['deprecated']++;
                $this->deprecated++;
                break;
        }
    }

    /**
     * Gets status information from a JUnit testcase node.
     *
     * @param \DOMElement $testcase Testcase node.
     * @return array
     */
    protected function get_status_from_junit_testcase(\DOMElement $testcase): array {
        $statusnodes = [
            'error' => self::STATUS_ERROR,
            'failure' => self::STATUS_FAILED,
            'warning' => self::STATUS_WARNING,
            'skipped' => self::STATUS_SKIPPED,
            'risky' => self::STATUS_RISKY,
            'incomplete' => self::STATUS_INCOMPLETE,
            'deprecation' => self::STATUS_DEPRECATED,
            'deprecated' => self::STATUS_DEPRECATED,
            'notice' => self::STATUS_WARNING,
        ];

        foreach ($statusnodes as $nodename => $status) {
            $nodes = $testcase->getElementsByTagName($nodename);

            if ($nodes->length === 0) {
                continue;
            }

            $node = $nodes->item(0);
            $type = '';

            if ($node instanceof \DOMElement) {
                $type = $node->attributes->getNamedItem('type')?->nodeValue ?? '';
            }

            return [
                'status' => $status,
                'type' => $type,
                'details' => trim($node->textContent),
            ];
        }

        return [
            'status' => self::STATUS_PASSED,
            'type' => '',
            'details' => '',
        ];
    }

    /**
     * Returns display data for a test status.
     *
     * @param string $status Test status.
     * @return array
     */
    protected function get_status_data(string $status): array {
        switch ($status) {
            case self::STATUS_PASSED:
                return [
                    'class' => 'text-success',
                    'badgeclass' => 'badge bg-success',
                    'icon' => '✓',
                    'text' => get_string('statuspassed', 'tool_phpunitchecker'),
                ];

            case self::STATUS_FAILED:
                return [
                    'class' => 'text-danger',
                    'badgeclass' => 'badge bg-danger',
                    'icon' => '✗',
                    'text' => get_string('statusfailed', 'tool_phpunitchecker'),
                ];

            case self::STATUS_ERROR:
                return [
                    'class' => 'text-danger',
                    'badgeclass' => 'badge bg-danger',
                    'icon' => '!',
                    'text' => get_string('statuserror', 'tool_phpunitchecker'),
                ];

            case self::STATUS_WARNING:
                return [
                    'class' => 'text-warning',
                    'badgeclass' => 'badge bg-warning text-dark',
                    'icon' => '⚠',
                    'text' => get_string('statuswarning', 'tool_phpunitchecker'),
                ];

            case self::STATUS_RISKY:
                return [
                    'class' => 'text-warning',
                    'badgeclass' => 'badge bg-warning text-dark',
                    'icon' => 'R',
                    'text' => get_string('statusrisky', 'tool_phpunitchecker'),
                ];

            case self::STATUS_SKIPPED:
                return [
                    'class' => 'text-muted',
                    'badgeclass' => 'badge bg-secondary',
                    'icon' => 'S',
                    'text' => get_string('statusskipped', 'tool_phpunitchecker'),
                ];

            case self::STATUS_INCOMPLETE:
                return [
                    'class' => 'text-muted',
                    'badgeclass' => 'badge bg-secondary',
                    'icon' => 'I',
                    'text' => get_string('statusincomplete', 'tool_phpunitchecker'),
                ];

            case self::STATUS_DEPRECATED:
                return [
                    'class' => 'text-warning',
                    'badgeclass' => 'badge bg-warning text-dark',
                    'icon' => 'D',
                    'text' => get_string('statusdeprecated', 'tool_phpunitchecker'),
                ];

            default:
                return [
                    'class' => 'text-muted',
                    'badgeclass' => 'badge bg-secondary',
                    'icon' => '?',
                    'text' => get_string('statusunknown', 'tool_phpunitchecker'),
                ];
        }
    }

    /**
     * Finalises one suite after all its tests have been parsed.
     *
     * @param int $suiteindex Suite index.
     * @return void
     */
    protected function finalise_suite(int $suiteindex): void {
        if (!isset($this->suites[$suiteindex])) {
            return;
        }

        $suite = $this->suites[$suiteindex];

        $hasproblems = $suite['failed'] > 0
            || $suite['errors'] > 0
            || $suite['warnings'] > 0
            || $suite['risky'] > 0
            || $suite['skipped'] > 0
            || $suite['incomplete'] > 0
            || $suite['deprecated'] > 0;

        $this->suites[$suiteindex]['hasproblems'] = $hasproblems;
        $this->suites[$suiteindex]['allpassed'] = $suite['total'] > 0 && !$hasproblems;
        $this->suites[$suiteindex]['statusclass'] = $hasproblems ? 'border-danger' : 'border-success';
        $this->suites[$suiteindex]['headerclass'] = $hasproblems ? 'bg-light text-danger' : 'bg-light text-success';
    }

    /**
     * Converts a PHPUnit method name into a readable name.
     *
     * @param string $name Raw test method name.
     * @return string
     */
    protected function humanise_test_name(string $name): string {
        $name = preg_replace('/^test_?/', '', $name);
        $name = str_replace('_', ' ', $name);

        return ucfirst($name);
    }

    /**
     * Returns the short class name from a full namespace.
     *
     * @param string $classname Full class name.
     * @return string
     */
    protected function get_short_classname(string $classname): string {
        $parts = explode('\\', $classname);

        return end($parts) ?: $classname;
    }

    /**
     * Returns the number of passed tests.
     *
     * @return int
     */
    public function get_passed_count(): int {
        return $this->passed;
    }

    /**
     * Returns the number of failed tests.
     *
     * @return int
     */
    public function get_failed_count(): int {
        return $this->failed;
    }

    /**
     * Returns the number of errored tests.
     *
     * @return int
     */
    public function get_error_count(): int {
        return $this->errors;
    }

    /**
     * Returns the number of warning tests.
     *
     * @return int
     */
    public function get_warning_count(): int {
        return $this->warnings;
    }

    /**
     * Returns the total number of tests.
     *
     * @return int
     */
    public function get_total_count(): int {
        return $this->total;
    }

    /**
     * Returns whether all tests passed.
     *
     * @return bool
     */
    public function all_tests_passed(): bool {
        return $this->allpassed;
    }

    /**
     * Exports data for the Moodle Mustache template.
     *
     * @param renderer_base $output Moodle renderer.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->testsuitename = $this->testsuitename;
        $data->hastestsuitename = $this->testsuitename !== '';

        $data->suites = array_values($this->suites);

        $data->passed = $this->passed;
        $data->failed = $this->failed;
        $data->errors = $this->errors;
        $data->warnings = $this->warnings;
        $data->risky = $this->risky;
        $data->skipped = $this->skipped;
        $data->incomplete = $this->incomplete;
        $data->deprecated = $this->deprecated;
        $data->total = $this->total;
        $data->allpassed = $this->allpassed;

        $data->statusclass = $this->allpassed ? 'alert-success' : 'alert-danger';
        $data->statustext = $this->allpassed
            ? get_string('alltestspassed', 'tool_phpunitchecker')
            : get_string('sometestsfailed', 'tool_phpunitchecker');

        $data->summaryitems = [
            [
                'label' => get_string('passedtests', 'tool_phpunitchecker'),
                'value' => $this->passed,
                'class' => 'text-success',
            ],
            [
                'label' => get_string('failedtests', 'tool_phpunitchecker'),
                'value' => $this->failed,
                'class' => 'text-danger',
            ],
            [
                'label' => get_string('errortests', 'tool_phpunitchecker'),
                'value' => $this->errors,
                'class' => 'text-danger',
            ],
            [
                'label' => get_string('warningtests', 'tool_phpunitchecker'),
                'value' => $this->warnings,
                'class' => 'text-warning',
            ],
            [
                'label' => get_string('riskytests', 'tool_phpunitchecker'),
                'value' => $this->risky,
                'class' => 'text-warning',
            ],
            [
                'label' => get_string('skippedtests', 'tool_phpunitchecker'),
                'value' => $this->skipped,
                'class' => 'text-muted',
            ],
            [
                'label' => get_string('incompletetests', 'tool_phpunitchecker'),
                'value' => $this->incomplete,
                'class' => 'text-muted',
            ],
            [
                'label' => get_string('deprecatedtests', 'tool_phpunitchecker'),
                'value' => $this->deprecated,
                'class' => 'text-warning',
            ],
        ];

        return $data;
    }
}