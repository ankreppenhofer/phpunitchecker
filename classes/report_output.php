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

defined('MOODLE_INTERNAL') || die();

use core\exception\moodle_exception;
use renderable;
use stdClass;
use templatable;

/**
 * Renderable output class for PHPUnit JUnit XML reports.
 *
 * The expected JUnit XML structure is:
 * - Report suite, for example /var/www/html/phpunit.xml.
 * - Component suite, for example mod_grouptool_testsuite.
 * - Test class suite, for example mod_grouptool\locallib_test.
 * - Testcases.
 *
 * The optional PHPUnit console output is only used to update existing testcases
 * with information which is not always available in the JUnit XML report.
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 MoodleMootDACH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    /** @var string Notice test status. */
    protected const STATUS_NOTICE = 'notice';

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
     * Raw PHPUnit console output.
     *
     * @var string
     */
    protected string $consoleoutput = '';

    /**
     * Name of the outer report suite, for example /var/www/html/phpunit.xml.
     *
     * @var string
     */
    protected string $reportsuitename = '';

    /**
     * Parsed component suites, for example mod_grouptool_testsuite.
     *
     * @var array
     */
    protected array $componentsuites = [];

    /**
     * Number of passed tests over the whole report.
     *
     * @var int
     */
    protected int $passed = 0;

    /**
     * Number of failed tests over the whole report.
     *
     * @var int
     */
    protected int $failed = 0;

    /**
     * Number of errored tests over the whole report.
     *
     * @var int
     */
    protected int $errors = 0;

    /**
     * Number of warning tests over the whole report.
     *
     * @var int
     */
    protected int $warnings = 0;

    /**
     * Number of notice tests over the whole report.
     *
     * @var int
     */
    protected int $notices = 0;

    /**
     * Number of skipped tests over the whole report.
     *
     * @var int
     */
    protected int $skipped = 0;

    /**
     * Number of incomplete tests over the whole report.
     *
     * @var int
     */
    protected int $incomplete = 0;

    /**
     * Number of deprecated tests over the whole report.
     *
     * @var int
     */
    protected int $deprecated = 0;

    /**
     * Total number of tests over the whole report.
     *
     * @var int
     */
    protected int $total = 0;

    /**
     * Whether all tests in the report passed.
     *
     * @var bool
     */
    protected bool $allpassed = false;

    /**
     * Creates a new report output instance.
     *
     * @param string $report Raw JUnit XML report content.
     * @param string $consoleoutput Optional raw PHPUnit console output.
     * @throws moodle_exception
     */
    public function __construct(string $report, string $consoleoutput = '') {
        if ($report === '') {
            throw new moodle_exception('Report content cannot be empty.');
        }

        $this->report = $report;
        $this->consoleoutput = str_replace(["\r\n", "\r"], "\n", $consoleoutput);
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
        $this->reportsuitename = $this->get_report_suite_name($xpath);

        $componentsuitenodes = $this->find_component_suites($xpath);

        foreach ($componentsuitenodes as $componentsuitenode) {
            $this->parse_component_suite($xpath, $componentsuitenode);
        }

        if ($this->consoleoutput !== '') {
            $this->parse_console_output();
        }

        $this->finalise_all_suites();
        $this->finalise_overall_summary();
    }

    /**
     * Gets the outer report suite name.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @return string
     */
    protected function get_report_suite_name(\DOMXPath $xpath): string {
        $node = $xpath->query('/testsuites/testsuite')->item(0);

        if ($node instanceof \DOMElement) {
            return $node->attributes->getNamedItem('name')?->nodeValue ?? '';
        }

        $node = $xpath->query('/testsuite')->item(0);

        if ($node instanceof \DOMElement) {
            return $node->attributes->getNamedItem('name')?->nodeValue ?? '';
        }

        return '';
    }

    /**
     * Finds all component suites.
     *
     * Component suites are the plugin/component-level suites, for example:
     * - mod_grouptool_testsuite
     * - mod_url_testsuite
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @return \DOMElement[]
     */
    protected function find_component_suites(\DOMXPath $xpath): array {
        $componentsuites = [];

        foreach ($xpath->query('//testsuite') as $testsuite) {
            if (!$testsuite instanceof \DOMElement) {
                continue;
            }

            $name = $testsuite->attributes->getNamedItem('name')?->nodeValue ?? '';

            if (
                $this->is_component_suite_name($name)
                && $testsuite->getElementsByTagName('testcase')->length > 0
            ) {
                $componentsuites[] = $testsuite;
            }
        }

        return $componentsuites;
    }

    /**
     * Checks whether a suite name is a Moodle component suite name.
     *
     * @param string $name Suite name.
     * @return bool
     */
    protected function is_component_suite_name(string $name): bool {
        return preg_match('/^[a-z]+_[a-z0-9_]+_testsuite$/', $name) === 1;
    }

    /**
     * Parses one component suite.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param \DOMElement $componentsuitenode Component suite node.
     * @return void
     */
    protected function parse_component_suite(\DOMXPath $xpath, \DOMElement $componentsuitenode): void {
        $name = $componentsuitenode->attributes->getNamedItem('name')?->nodeValue ?? '';

        if ($name === '') {
            $name = get_string('unknownsuite', 'tool_phpunitchecker');
        }

        $componentindex = $this->add_component_suite($name);

        foreach ($xpath->query('.//testsuite[testcase]', $componentsuitenode) as $testsuitenode) {
            if (!$testsuitenode instanceof \DOMElement) {
                continue;
            }

            $suitename = $testsuitenode->attributes->getNamedItem('name')?->nodeValue ?? '';

            // Do not parse the component suite itself as a test class suite.
            if ($this->is_component_suite_name($suitename)) {
                continue;
            }

            $this->parse_test_suite($xpath, $componentindex, $testsuitenode);
        }

        // Fallback for unusual JUnit files where the component suite contains
        // testcases directly without a nested class/file suite.
        foreach ($xpath->query('./testcase', $componentsuitenode) as $testcase) {
            if (!$testcase instanceof \DOMElement) {
                continue;
            }

            $suiteindex = $this->add_test_suite(
                $componentindex,
                $name,
                $name,
                ''
            );

            $this->parse_testcase($componentindex, $suiteindex, $testcase, '', $name);
        }
    }

    /**
     * Adds a component suite and returns its index.
     *
     * @param string $name Component suite name.
     * @return int Component suite index.
     */
    protected function add_component_suite(string $name): int {
        $this->componentsuites[] = [
            'uniqid' => clean_param(md5($name . count($this->componentsuites)), PARAM_ALPHANUMEXT),
            'name' => $name,
            'testsuites' => [],
            'passed' => 0,
            'failed' => 0,
            'errors' => 0,
            'warnings' => 0,
            'notices' => 0,
            'skipped' => 0,
            'incomplete' => 0,
            'deprecated' => 0,
            'total' => 0,
            'allpassed' => false,
            'hasproblems' => false,
            'statusclass' => '',
            'headerclass' => '',
            'summaryitems' => [],
        ];

        return count($this->componentsuites) - 1;
    }

    /**
     * Parses one test class/file suite.
     *
     * @param \DOMXPath $xpath XML xpath instance.
     * @param int $componentindex Component suite index.
     * @param \DOMElement $testsuitenode Test class suite node.
     * @return void
     */
    protected function parse_test_suite(
        \DOMXPath $xpath,
        int $componentindex,
        \DOMElement $testsuitenode
    ): void {
        $classname = $testsuitenode->attributes->getNamedItem('name')?->nodeValue ?? '';
        $file = $testsuitenode->attributes->getNamedItem('file')?->nodeValue ?? '';

        if ($classname === '') {
            $classname = get_string('unknownsuite', 'tool_phpunitchecker');
        }

        $suiteindex = $this->add_test_suite(
            $componentindex,
            $this->get_short_classname($classname),
            $classname,
            $file
        );

        foreach ($xpath->query('./testcase', $testsuitenode) as $testcase) {
            if (!$testcase instanceof \DOMElement) {
                continue;
            }

            $this->parse_testcase($componentindex, $suiteindex, $testcase, $file, $classname);
        }
    }

    /**
     * Adds a test class/file suite to a component suite.
     *
     * @param int $componentindex Component suite index.
     * @param string $name Suite display name.
     * @param string $classname Full class name.
     * @param string $file Optional file path.
     * @return int Test suite index.
     */
    protected function add_test_suite(
        int $componentindex,
        string $name,
        string $classname,
        string $file = ''
    ): int {
        $this->componentsuites[$componentindex]['testsuites'][] = [
            'uniqid' => clean_param(
                md5($classname . $file . count($this->componentsuites[$componentindex]['testsuites'])),
                PARAM_ALPHANUMEXT
            ),
            'name' => $name,
            'classname' => $classname,
            'file' => $file,
            'hasfile' => $file !== '',
            'testcases' => [],
            'passed' => 0,
            'failed' => 0,
            'errors' => 0,
            'warnings' => 0,
            'notices' => 0,
            'skipped' => 0,
            'incomplete' => 0,
            'deprecated' => 0,
            'total' => 0,
            'allpassed' => false,
            'hasproblems' => false,
            'statusclass' => '',
            'headerclass' => '',
        ];

        return count($this->componentsuites[$componentindex]['testsuites']) - 1;
    }

    /**
     * Parses one testcase node.
     *
     * @param int $componentindex Component suite index.
     * @param int $suiteindex Test suite index.
     * @param \DOMElement $testcase Testcase node.
     * @param string $defaultfile Default file from the parent test suite.
     * @param string $defaultclassname Default class name from the parent test suite.
     * @return void
     */
    protected function parse_testcase(
        int $componentindex,
        int $suiteindex,
        \DOMElement $testcase,
        string $defaultfile = '',
        string $defaultclassname = ''
    ): void {
        $testname = $testcase->attributes->getNamedItem('name')?->nodeValue ?? '';
        $classname = $testcase->attributes->getNamedItem('class')?->nodeValue ?? $defaultclassname;
        $file = $testcase->attributes->getNamedItem('file')?->nodeValue ?? $defaultfile;
        $line = $testcase->attributes->getNamedItem('line')?->nodeValue ?? '';
        $time = $testcase->attributes->getNamedItem('time')?->nodeValue ?? '';
        $assertions = $testcase->attributes->getNamedItem('assertions')?->nodeValue ?? '';

        $statusinfo = $this->get_status_from_junit_testcase($testcase);

        $this->add_testcase(
            $componentindex,
            $suiteindex,
            $this->humanise_test_name($testname),
            $statusinfo['status'],
            $statusinfo['type'],
            $statusinfo['details'],
            $file,
            $line,
            $time,
            $assertions,
            $classname,
            $testname
        );
    }

    /**
     * Adds a parsed testcase.
     *
     * @param int $componentindex Component suite index.
     * @param int $suiteindex Test suite index.
     * @param string $name Testcase name.
     * @param string $status Testcase status.
     * @param string $type Optional problem type.
     * @param string $details Optional problem details.
     * @param string $file Optional testcase file.
     * @param string $line Optional testcase line.
     * @param string $time Optional testcase runtime.
     * @param string $assertions Optional number of assertions.
     * @param string $classname Testcase class name.
     * @param string $methodname Testcase method name.
     * @return void
     */
    protected function add_testcase(
        int $componentindex,
        int $suiteindex,
        string $name,
        string $status,
        string $type = '',
        string $details = '',
        string $file = '',
        string $line = '',
        string $time = '',
        string $assertions = '',
        string $classname = '',
        string $methodname = ''
    ): void {
        $statusdata = $this->get_status_data($status);

        $testcase = [
            'name' => $name,
            'classname' => $classname,
            'methodname' => $methodname,
            'fulltestname' => $classname !== '' && $methodname !== '' ? $classname . '::' . $methodname : '',
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

        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][] = $testcase;

        $this->increase_counter($this->componentsuites[$componentindex], $status);
        $this->increase_counter($this->componentsuites[$componentindex]['testsuites'][$suiteindex], $status);
        $this->increase_counter_for_report($status);
    }

    /**
     * Increases a status counter in a suite array.
     *
     * @param array $suite Suite data.
     * @param string $status Test status.
     * @return void
     */
    protected function increase_counter(array &$suite, string $status): void {
        $suite['total']++;

        switch ($status) {
            case self::STATUS_PASSED:
                $suite['passed']++;
                break;

            case self::STATUS_FAILED:
                $suite['failed']++;
                break;

            case self::STATUS_ERROR:
                $suite['errors']++;
                break;

            case self::STATUS_WARNING:
                $suite['warnings']++;
                break;

            case self::STATUS_NOTICE:
                $suite['notices']++;
                break;

            case self::STATUS_SKIPPED:
                $suite['skipped']++;
                break;

            case self::STATUS_INCOMPLETE:
                $suite['incomplete']++;
                break;

            case self::STATUS_DEPRECATED:
                $suite['deprecated']++;
                break;
        }
    }

    /**
     * Decreases a status counter in a suite array.
     *
     * @param array $suite Suite data.
     * @param string $status Test status.
     * @return void
     */
    protected function decrease_counter(array &$suite, string $status): void {
        $suite['total'] = max(0, $suite['total'] - 1);

        switch ($status) {
            case self::STATUS_PASSED:
                $suite['passed'] = max(0, $suite['passed'] - 1);
                break;

            case self::STATUS_FAILED:
                $suite['failed'] = max(0, $suite['failed'] - 1);
                break;

            case self::STATUS_ERROR:
                $suite['errors'] = max(0, $suite['errors'] - 1);
                break;

            case self::STATUS_WARNING:
                $suite['warnings'] = max(0, $suite['warnings'] - 1);
                break;

            case self::STATUS_NOTICE:
                $suite['notices'] = max(0, $suite['notices'] - 1);
                break;

            case self::STATUS_SKIPPED:
                $suite['skipped'] = max(0, $suite['skipped'] - 1);
                break;

            case self::STATUS_INCOMPLETE:
                $suite['incomplete'] = max(0, $suite['incomplete'] - 1);
                break;

            case self::STATUS_DEPRECATED:
                $suite['deprecated'] = max(0, $suite['deprecated'] - 1);
                break;
        }
    }

    /**
     * Increases a status counter for the overall report.
     *
     * @param string $status Test status.
     * @return void
     */
    protected function increase_counter_for_report(string $status): void {
        switch ($status) {
            case self::STATUS_PASSED:
                $this->passed++;
                break;

            case self::STATUS_FAILED:
                $this->failed++;
                break;

            case self::STATUS_ERROR:
                $this->errors++;
                break;

            case self::STATUS_WARNING:
                $this->warnings++;
                break;

            case self::STATUS_NOTICE:
                $this->notices++;
                break;

            case self::STATUS_SKIPPED:
                $this->skipped++;
                break;

            case self::STATUS_INCOMPLETE:
                $this->incomplete++;
                break;

            case self::STATUS_DEPRECATED:
                $this->deprecated++;
                break;
        }
    }

    /**
     * Decreases a status counter for the overall report.
     *
     * @param string $status Test status.
     * @return void
     */
    protected function decrease_counter_for_report(string $status): void {
        switch ($status) {
            case self::STATUS_PASSED:
                $this->passed = max(0, $this->passed - 1);
                break;

            case self::STATUS_FAILED:
                $this->failed = max(0, $this->failed - 1);
                break;

            case self::STATUS_ERROR:
                $this->errors = max(0, $this->errors - 1);
                break;

            case self::STATUS_WARNING:
                $this->warnings = max(0, $this->warnings - 1);
                break;

            case self::STATUS_NOTICE:
                $this->notices = max(0, $this->notices - 1);
                break;

            case self::STATUS_SKIPPED:
                $this->skipped = max(0, $this->skipped - 1);
                break;

            case self::STATUS_INCOMPLETE:
                $this->incomplete = max(0, $this->incomplete - 1);
                break;

            case self::STATUS_DEPRECATED:
                $this->deprecated = max(0, $this->deprecated - 1);
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
            'notice' => self::STATUS_NOTICE,
            'skipped' => self::STATUS_SKIPPED,
            'incomplete' => self::STATUS_INCOMPLETE,
            'deprecation' => self::STATUS_DEPRECATED,
            'deprecated' => self::STATUS_DEPRECATED,
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
     * Parses PHPUnit console output and updates existing testcases.
     *
     * @return void
     */
    protected function parse_console_output(): void {
        $this->update_testcases_from_console_section(
            '/There was \d+ incomplete test:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_INCOMPLETE
        );

        $this->update_testcases_from_console_section(
            '/There were \d+ incomplete tests:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_INCOMPLETE
        );

        $this->update_testcases_from_console_section(
            '/\d+ test triggered \d+ warning:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_WARNING
        );

        $this->update_testcases_from_console_section(
            '/\d+ tests triggered \d+ warnings:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_WARNING
        );

        $this->update_testcases_from_console_section(
            '/\d+ test triggered \d+ notice:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_NOTICE
        );

        $this->update_testcases_from_console_section(
            '/\d+ tests triggered \d+ notices:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_NOTICE
        );

        $this->update_testcases_from_console_section(
            '/There was \d+ PHPUnit test runner deprecation:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_DEPRECATED
        );

        $this->update_testcases_from_console_section(
            '/There were \d+ PHPUnit test runner deprecations:(.*?)(?=\n--|\nERRORS!|\nFAILURES!|\z)/s',
            self::STATUS_DEPRECATED
        );
    }

    /**
     * Updates existing testcases from a matching PHPUnit console output section.
     *
     * @param string $pattern Regex pattern.
     * @param string $status New status.
     * @return void
     */
    protected function update_testcases_from_console_section(string $pattern, string $status): void {
        if (!preg_match_all($pattern, $this->consoleoutput, $matches)) {
            return;
        }

        foreach ($matches[1] as $section) {
            $items = preg_split('/\n(?=\d+\)\s+)/', trim($section));

            foreach ($items as $item) {
                $item = trim($item);

                if ($item === '') {
                    continue;
                }

                $this->update_matching_testcase_from_console_item($item, $status);
            }
        }
    }

    /**
     * Updates one matching testcase from a console output item.
     *
     * @param string $item Console output item.
     * @param string $status New status.
     * @return void
     */
    protected function update_matching_testcase_from_console_item(string $item, string $status): void {
        $fulltestname = $this->get_console_issue_full_test_name($item);
        $classname = $this->get_console_issue_class_name($item);
        $file = $this->get_console_issue_file($item);
        $line = $this->get_console_issue_line($item);

        if ($this->update_testcase_by_full_test_name($fulltestname, $status, $item, $file, $line)) {
            return;
        }

        if ($this->update_testcase_by_file_and_line($file, $line, $status, $item)) {
            return;
        }

        if ($this->update_nearest_testcase_by_file_and_line($file, $line, $status, $item)) {
            return;
        }

        if ($this->update_first_testcase_by_classname($classname, $status, $item)) {
            return;
        }
    }

    /**
     * Updates a testcase by its full test name.
     *
     * @param string $fulltestname Full test name.
     * @param string $status New status.
     * @param string $details Console details.
     * @param string $file Optional file.
     * @param string $line Optional line.
     * @return bool
     */
    protected function update_testcase_by_full_test_name(
        string $fulltestname,
        string $status,
        string $details,
        string $file = '',
        string $line = ''
    ): bool {
        if ($fulltestname === '') {
            return false;
        }

        foreach ($this->componentsuites as $componentindex => $componentsuite) {
            foreach ($componentsuite['testsuites'] as $suiteindex => $testsuite) {
                foreach ($testsuite['testcases'] as $testcaseindex => $testcase) {
                    if (!empty($testcase['fulltestname']) && $testcase['fulltestname'] === $fulltestname) {
                        $this->update_testcase_status(
                            $componentindex,
                            $suiteindex,
                            $testcaseindex,
                            $status,
                            $details,
                            $file,
                            $line
                        );

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Updates a testcase by exact file and line.
     *
     * @param string $file File path.
     * @param string $line Line number.
     * @param string $status New status.
     * @param string $details Console details.
     * @return bool
     */
    protected function update_testcase_by_file_and_line(
        string $file,
        string $line,
        string $status,
        string $details
    ): bool {
        if ($file === '' || $line === '') {
            return false;
        }

        foreach ($this->componentsuites as $componentindex => $componentsuite) {
            foreach ($componentsuite['testsuites'] as $suiteindex => $testsuite) {
                foreach ($testsuite['testcases'] as $testcaseindex => $testcase) {
                    if (
                        !empty($testcase['file'])
                        && !empty($testcase['line'])
                        && $testcase['file'] === $file
                        && (string)$testcase['line'] === (string)$line
                    ) {
                        $this->update_testcase_status(
                            $componentindex,
                            $suiteindex,
                            $testcaseindex,
                            $status,
                            $details,
                            $file,
                            $line
                        );

                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Updates the nearest testcase by file and line.
     *
     * This is useful for warnings and notices where PHPUnit prints the line of
     * the trigger_error call, not necessarily the method declaration line.
     *
     * @param string $file File path.
     * @param string $line Line number.
     * @param string $status New status.
     * @param string $details Console details.
     * @return bool
     */
    protected function update_nearest_testcase_by_file_and_line(
        string $file,
        string $line,
        string $status,
        string $details
    ): bool {
        if ($file === '' || $line === '') {
            return false;
        }

        $bestmatch = null;
        $bestline = 0;
        $targetline = (int)$line;

        foreach ($this->componentsuites as $componentindex => $componentsuite) {
            foreach ($componentsuite['testsuites'] as $suiteindex => $testsuite) {
                foreach ($testsuite['testcases'] as $testcaseindex => $testcase) {
                    if (empty($testcase['file']) || empty($testcase['line'])) {
                        continue;
                    }

                    if ($testcase['file'] !== $file) {
                        continue;
                    }

                    $testcaseline = (int)$testcase['line'];

                    if ($testcaseline <= $targetline && $testcaseline >= $bestline) {
                        $bestline = $testcaseline;
                        $bestmatch = [
                            'componentindex' => $componentindex,
                            'suiteindex' => $suiteindex,
                            'testcaseindex' => $testcaseindex,
                        ];
                    }
                }
            }
        }

        if ($bestmatch === null) {
            return false;
        }

        $this->update_testcase_status(
            $bestmatch['componentindex'],
            $bestmatch['suiteindex'],
            $bestmatch['testcaseindex'],
            $status,
            $details,
            $file,
            $line
        );

        return true;
    }

    /**
     * Updates the first testcase of a matching class.
     *
     * This is only used for class-level PHPUnit issues, for example metadata
     * deprecations that mention the test class but not a concrete method.
     *
     * @param string $classname Class name.
     * @param string $status New status.
     * @param string $details Console details.
     * @return bool
     */
    protected function update_first_testcase_by_classname(string $classname, string $status, string $details): bool {
        if ($classname === '') {
            return false;
        }

        foreach ($this->componentsuites as $componentindex => $componentsuite) {
            foreach ($componentsuite['testsuites'] as $suiteindex => $testsuite) {
                if ($testsuite['classname'] !== $classname) {
                    continue;
                }

                if (empty($testsuite['testcases'])) {
                    continue;
                }

                $this->update_testcase_status(
                    $componentindex,
                    $suiteindex,
                    0,
                    $status,
                    $details
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Updates an existing testcase status and adjusts all counters.
     *
     * @param int $componentindex Component suite index.
     * @param int $suiteindex Test suite index.
     * @param int $testcaseindex Testcase index.
     * @param string $newstatus New status.
     * @param string $details Details from console output.
     * @param string $file Optional file.
     * @param string $line Optional line.
     * @return void
     */
    protected function update_testcase_status(
        int $componentindex,
        int $suiteindex,
        int $testcaseindex,
        string $newstatus,
        string $details = '',
        string $file = '',
        string $line = ''
    ): void {
        $oldstatus = $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['status'];

        if ($oldstatus === $newstatus) {
            $this->update_testcase_details($componentindex, $suiteindex, $testcaseindex, $details, $file, $line);
            return;
        }

        $this->decrease_counter($this->componentsuites[$componentindex], $oldstatus);
        $this->decrease_counter($this->componentsuites[$componentindex]['testsuites'][$suiteindex], $oldstatus);
        $this->decrease_counter_for_report($oldstatus);

        $this->increase_counter($this->componentsuites[$componentindex], $newstatus);
        $this->increase_counter($this->componentsuites[$componentindex]['testsuites'][$suiteindex], $newstatus);
        $this->increase_counter_for_report($newstatus);

        $statusdata = $this->get_status_data($newstatus);

        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['status'] = $newstatus;
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['passed'] =
            $newstatus === self::STATUS_PASSED;
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['hasproblem'] =
            $newstatus !== self::STATUS_PASSED;
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['statusclass'] =
            $statusdata['class'];
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['badgeclass'] =
            $statusdata['badgeclass'];
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['statusicon'] =
            $statusdata['icon'];
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['statustext'] =
            $statusdata['text'];

        $this->update_testcase_details($componentindex, $suiteindex, $testcaseindex, $details, $file, $line);
    }

    /**
     * Updates testcase details.
     *
     * @param int $componentindex Component suite index.
     * @param int $suiteindex Test suite index.
     * @param int $testcaseindex Testcase index.
     * @param string $details Details.
     * @param string $file File.
     * @param string $line Line.
     * @return void
     */
    protected function update_testcase_details(
        int $componentindex,
        int $suiteindex,
        int $testcaseindex,
        string $details = '',
        string $file = '',
        string $line = ''
    ): void {
        if ($details !== '') {
            $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['details'] =
                $details;
            $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['hasdetails'] =
                true;
        }

        if ($file !== '') {
            $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['file'] =
                $file;
            $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['hasfile'] =
                true;
        }

        if ($line !== '') {
            $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['line'] =
                $line;
            $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['testcases'][$testcaseindex]['hasline'] =
                true;
        }
    }

    /**
     * Extracts a full testcase name from a console issue item.
     *
     * @param string $item Console issue item.
     * @return string
     */
    protected function get_console_issue_full_test_name(string $item): string {
        if (preg_match('/[a-zA-Z0-9_\\\\]+::[a-zA-Z0-9_]+/', $item, $matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * Extracts a class name from a console issue item.
     *
     * @param string $item Console issue item.
     * @return string
     */
    protected function get_console_issue_class_name(string $item): string {
        if (preg_match('/class\s+([a-zA-Z0-9_\\\\]+)/', $item, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extracts a file path from a console issue item.
     *
     * @param string $item Console issue item.
     * @return string
     */
    protected function get_console_issue_file(string $item): string {
        if (preg_match('/(\/[^:\n]+\.php):\d+/', $item, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extracts a line number from a console issue item.
     *
     * @param string $item Console issue item.
     * @return string
     */
    protected function get_console_issue_line(string $item): string {
        if (preg_match('/\/[^:\n]+\.php:(\d+)/', $item, $matches)) {
            return $matches[1];
        }

        return '';
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

            case self::STATUS_NOTICE:
                return [
                    'class' => 'text-info',
                    'badgeclass' => 'badge bg-info text-dark',
                    'icon' => 'N',
                    'text' => get_string('statusnotice', 'tool_phpunitchecker'),
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
     * Finalises all suites after XML and console output parsing.
     *
     * @return void
     */
    protected function finalise_all_suites(): void {
        foreach ($this->componentsuites as $componentindex => $componentsuite) {
            foreach ($componentsuite['testsuites'] as $suiteindex => $testsuite) {
                $this->finalise_test_suite($componentindex, $suiteindex);
            }

            $this->finalise_component_suite($componentindex);
        }
    }

    /**
     * Finalises one test class/file suite.
     *
     * @param int $componentindex Component suite index.
     * @param int $suiteindex Test suite index.
     * @return void
     */
    protected function finalise_test_suite(int $componentindex, int $suiteindex): void {
        $suite = $this->componentsuites[$componentindex]['testsuites'][$suiteindex];
        $hasproblems = $this->has_problems($suite);

        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['hasproblems'] = $hasproblems;
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['allpassed'] =
            $suite['total'] > 0 && !$hasproblems;
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['statusclass'] =
            $hasproblems ? 'border-danger' : 'border-success';
        $this->componentsuites[$componentindex]['testsuites'][$suiteindex]['headerclass'] =
            $hasproblems ? 'text-danger' : 'text-success';
    }

    /**
     * Finalises one component suite.
     *
     * @param int $componentindex Component suite index.
     * @return void
     */
    protected function finalise_component_suite(int $componentindex): void {
        $suite = $this->componentsuites[$componentindex];
        $hasproblems = $this->has_problems($suite);

        $this->componentsuites[$componentindex]['hasproblems'] = $hasproblems;
        $this->componentsuites[$componentindex]['allpassed'] = $suite['total'] > 0 && !$hasproblems;
        $this->componentsuites[$componentindex]['statusclass'] = $hasproblems ? 'border-danger' : 'border-success';
        $this->componentsuites[$componentindex]['headerclass'] = $hasproblems ? 'text-danger' : 'text-success';
        $this->componentsuites[$componentindex]['summaryitems'] = $this->build_summary_items($suite);
    }

    /**
     * Finalises the overall report summary.
     *
     * @return void
     */
    protected function finalise_overall_summary(): void {
        $this->total = $this->passed
            + $this->failed
            + $this->errors
            + $this->warnings
            + $this->notices
            + $this->skipped
            + $this->incomplete
            + $this->deprecated;

        $this->allpassed = $this->total > 0
            && $this->failed === 0
            && $this->errors === 0
            && $this->warnings === 0
            && $this->notices === 0
            && $this->skipped === 0
            && $this->incomplete === 0
            && $this->deprecated === 0;
    }

    /**
     * Checks whether a summary array has problems.
     *
     * @param array $summary Summary data.
     * @return bool
     */
    protected function has_problems(array $summary): bool {
        return $summary['failed'] > 0
            || $summary['errors'] > 0
            || $summary['warnings'] > 0
            || $summary['notices'] > 0
            || $summary['skipped'] > 0
            || $summary['incomplete'] > 0
            || $summary['deprecated'] > 0;
    }

    /**
     * Builds summary item data for the template.
     *
     * @param array $summary Summary data.
     * @return array
     */
    protected function build_summary_items(array $summary): array {
        return [
            [
                'label' => get_string('passedtests', 'tool_phpunitchecker'),
                'value' => $summary['passed'],
                'class' => 'text-success',
            ],
            [
                'label' => get_string('failedtests', 'tool_phpunitchecker'),
                'value' => $summary['failed'],
                'class' => 'text-danger',
            ],
            [
                'label' => get_string('errortests', 'tool_phpunitchecker'),
                'value' => $summary['errors'],
                'class' => 'text-danger',
            ],
            [
                'label' => get_string('warningtests', 'tool_phpunitchecker'),
                'value' => $summary['warnings'],
                'class' => 'text-warning',
            ],
            [
                'label' => get_string('noticetests', 'tool_phpunitchecker'),
                'value' => $summary['notices'],
                'class' => 'text-info',
            ],
            [
                'label' => get_string('skippedtests', 'tool_phpunitchecker'),
                'value' => $summary['skipped'],
                'class' => 'text-muted',
            ],
            [
                'label' => get_string('incompletetests', 'tool_phpunitchecker'),
                'value' => $summary['incomplete'],
                'class' => 'text-muted',
            ],
            [
                'label' => get_string('deprecatedtests', 'tool_phpunitchecker'),
                'value' => $summary['deprecated'],
                'class' => 'text-warning',
            ],
        ];
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
     * @param mixed $output Moodle renderer.
     * @return stdClass
     */
    public function export_for_template($output): stdClass {
        $data = new stdClass();

        $data->reportsuitename = $this->reportsuitename;
        $data->hasreportsuitename = $this->reportsuitename !== '';

        $data->componentsuites = array_values($this->componentsuites);

        $data->passed = $this->passed;
        $data->failed = $this->failed;
        $data->errors = $this->errors;
        $data->warnings = $this->warnings;
        $data->notices = $this->notices;
        $data->skipped = $this->skipped;
        $data->incomplete = $this->incomplete;
        $data->deprecated = $this->deprecated;
        $data->total = $this->total;
        $data->allpassed = $this->allpassed;

        $data->statusclass = $this->allpassed ? 'alert-success' : 'alert-danger';
        $data->statustext = $this->allpassed
            ? get_string('alltestspassed', 'tool_phpunitchecker')
            : get_string('sometestsfailed', 'tool_phpunitchecker');

        $data->summaryitems = $this->build_summary_items([
            'passed' => $this->passed,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'notices' => $this->notices,
            'skipped' => $this->skipped,
            'incomplete' => $this->incomplete,
            'deprecated' => $this->deprecated,
        ]);

        return $data;
    }
}