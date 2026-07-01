<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_phpunitchecker;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the PHPUnit checker report output parser.
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 Alissa Cenga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_phpunitchecker\report_output
 */
final class report_output_test extends \advanced_testcase {

    /**
     * Reports with only passing tests should be marked as fully passed.
     *
     * @return void
     */
    public function test_passed_report_is_counted_as_all_passed(): void {
        $report = new report_output(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="/var/www/html/phpunit.xml" tests="2" assertions="2" errors="0" failures="0" skipped="0">
    <testsuite name="tool_phpunitchecker_testsuite" tests="2" assertions="2" errors="0" failures="0" skipped="0">
      <testsuite name="tool_phpunitchecker\\sample_test" file="/var/www/html/sample_test.php" tests="2" assertions="2">
        <testcase name="test_first_passes" file="/var/www/html/sample_test.php" line="10" assertions="1" time="0.01"/>
        <testcase name="test_second_passes" file="/var/www/html/sample_test.php" line="20" assertions="1" time="0.02"/>
      </testsuite>
    </testsuite>
  </testsuite>
</testsuites>
XML);

        $this->assertSame(2, $report->get_total_count());
        $this->assertSame(2, $report->get_passed_count());
        $this->assertSame(0, $report->get_failed_count());
        $this->assertSame(0, $report->get_error_count());
        $this->assertSame(0, $report->get_warning_count());
        $this->assertTrue($report->all_tests_passed());
    }

    /**
     * Standard JUnit problem nodes should be counted separately.
     *
     * @return void
     */
    public function test_problem_statuses_are_counted_from_junit_nodes(): void {
        global $OUTPUT;

        $report = new report_output(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="/var/www/html/phpunit.xml" tests="4" assertions="2" errors="1" failures="1" skipped="1">
    <testsuite name="tool_phpunitchecker_testsuite" tests="4" assertions="2" errors="1" failures="1" skipped="1">
      <testsuite name="tool_phpunitchecker\\sample_test" file="/var/www/html/sample_test.php" tests="4" assertions="2">
        <testcase name="test_passes" file="/var/www/html/sample_test.php" line="10" assertions="1" time="0.01"/>
        <testcase name="test_fails" file="/var/www/html/sample_test.php" line="20" assertions="1" time="0.02">
          <failure type="PHPUnit\\Framework\\ExpectationFailedException">Failed asserting that false is true.</failure>
        </testcase>
        <testcase name="test_errors" file="/var/www/html/sample_test.php" line="30" assertions="0" time="0.03">
          <error type="Error">Example error.</error>
        </testcase>
        <testcase name="test_is_skipped" file="/var/www/html/sample_test.php" line="60" assertions="0" time="0.06">
          <skipped>Example skipped test.</skipped>
        </testcase>
      </testsuite>
    </testsuite>
  </testsuite>
</testsuites>
XML);

        $data = $report->export_for_template($OUTPUT);

        $this->assertSame(4, $report->get_total_count());
        $this->assertSame(1, $report->get_passed_count());
        $this->assertSame(1, $report->get_failed_count());
        $this->assertSame(1, $report->get_error_count());
        $this->assertSame(0, $report->get_warning_count());
        $this->assertSame(1, $data->skipped);
        $this->assertSame(0, $data->incomplete);
        $this->assertSame(0, $data->deprecated);
        $this->assertFalse($report->all_tests_passed());
    }

    /**
     * Exported data should keep component, class and testcase details for the template.
     *
     * @return void
     */
    public function test_export_contains_component_suite_and_testcase_details(): void {
        global $OUTPUT;

        $report = new report_output(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="/var/www/html/phpunit.xml" tests="1" assertions="1" errors="0" failures="1" skipped="0">
    <testsuite name="tool_phpunitchecker_testsuite" tests="1" assertions="1" errors="0" failures="1" skipped="0">
      <testsuite name="tool_phpunitchecker\\sample_test" file="/var/www/html/public/admin/tool/phpunitchecker/tests/sample_test.php" tests="1" assertions="1">
        <testcase name="test_failure_is_rendered" file="/var/www/html/public/admin/tool/phpunitchecker/tests/sample_test.php" line="42" assertions="1" time="0.12">
          <failure type="PHPUnit\\Framework\\ExpectationFailedException">Expected value did not match.</failure>
        </testcase>
      </testsuite>
    </testsuite>
  </testsuite>
</testsuites>
XML);

        $data = $report->export_for_template($OUTPUT);
        $componentsuite = $data->componentsuites[0];
        $testsuite = $componentsuite['testsuites'][0];
        $testcase = $testsuite['testcases'][0];

        $this->assertSame('tool_phpunitchecker_testsuite', $componentsuite['name']);
        $this->assertSame('sample_test', $testsuite['name']);
        $this->assertSame('Failure is rendered', $testcase['name']);
        $this->assertSame('failed', $testcase['status']);
        $this->assertTrue($testcase['hasdetails']);
        $this->assertStringContainsString('Expected value did not match.', $testcase['details']);
        $this->assertSame('42', $testcase['line']);
        $this->assertSame('0.12', $testcase['time']);
        $this->assertFalse($data->allpassed);
    }

    /**
     * Empty report content should be rejected.
     *
     * @return void
     */
    public function test_empty_report_content_is_rejected(): void {
        $this->expectException(\core\exception\moodle_exception::class);

        new report_output('');
    }
}
