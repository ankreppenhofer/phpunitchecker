<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace tool_phpunitchecker;

/**
 * Tests for the PHPUnit checker clover code coverage parser.
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_phpunitchecker\report_coverage
 */
final class report_coverage_test extends \advanced_testcase {

    /**
     * Wraps package XML fragments into a complete clover document.
     *
     * @param string $packages Package XML fragments.
     * @return string
     */
    private function clover(string $packages): string {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<coverage generated="1789460135">
  <project timestamp="1789460135" name="Clover Coverage">
$packages
  </project>
</coverage>
XML;
    }

    /**
     * A single-class file should expose the package, file, class and method metrics.
     *
     * @return void
     */
    public function test_packages_files_classes_and_methods_are_parsed(): void {
        $report = new report_coverage($this->clover(<<<XML
    <package name="tool_phpunitchecker\\sample">
      <file name="/var/www/html/sample.php">
        <class name="tool_phpunitchecker\\sample\\thing" namespace="tool_phpunitchecker\\sample">
          <metrics complexity="3" methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="4" coveredstatements="2" elements="6" coveredelements="3"/>
        </class>
        <line num="10" type="method" name="covered_method" visibility="public" complexity="1" crap="1" count="5"/>
        <line num="12" type="stmt" count="5"/>
        <line num="20" type="method" name="uncovered_method" visibility="protected" complexity="2" crap="6" count="0"/>
        <line num="22" type="stmt" count="0"/>
        <metrics loc="30" ncloc="20" classes="1" methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="4" coveredstatements="2" elements="6" coveredelements="3"/>
      </file>
    </package>
XML));

        $packages = $report->get_packages();

        $this->assertCount(1, $packages);

        $package = $packages[0];
        $this->assertSame('tool_phpunitchecker\\sample', $package['name']);
        $this->assertSame('tool_phpunitchecker', $package['component']);
        $this->assertCount(1, $package['files']);

        // Package metrics are aggregated from the files.
        $this->assertSame(1, $package['metrics']['files']);
        $this->assertSame(6, $package['metrics']['elements']);
        $this->assertSame(3, $package['metrics']['coveredelements']);
        $this->assertSame(50.0, $package['metrics']['coverage']);

        $file = $package['files'][0];
        $this->assertSame('/var/www/html/sample.php', $file['name']);
        $this->assertSame('sample.php', $file['shortname']);
        $this->assertSame(6, $file['metrics']['elements']);
        $this->assertSame(3, $file['metrics']['coveredelements']);
        $this->assertSame(50.0, $file['metrics']['coverage']);
        $this->assertCount(1, $file['classes']);

        $class = $file['classes'][0];
        $this->assertSame('tool_phpunitchecker\\sample\\thing', $class['name']);
        $this->assertSame('thing', $class['shortname']);
        $this->assertSame('tool_phpunitchecker\\sample', $class['namespace']);
        $this->assertSame(2, $class['metrics']['methods']);
        $this->assertSame(1, $class['metrics']['coveredmethods']);
        $this->assertSame(50.0, $class['metrics']['coverage']);

        // Methods of a single-class file are attached to that class.
        $this->assertTrue($class['hasmethods']);
        $this->assertCount(2, $class['methods']);

        $covered = $class['methods'][0];
        $this->assertSame('covered_method', $covered['name']);
        $this->assertSame(10, $covered['line']);
        $this->assertSame('public', $covered['visibility']);
        $this->assertSame(5, $covered['count']);
        $this->assertTrue($covered['covered']);

        $uncovered = $class['methods'][1];
        $this->assertSame('uncovered_method', $uncovered['name']);
        $this->assertSame(0, $uncovered['count']);
        $this->assertFalse($uncovered['covered']);
    }

    /**
     * A file declaring more than one class keeps its methods at file level.
     *
     * @return void
     */
    public function test_multiclass_file_keeps_methods_at_file_level(): void {
        $report = new report_coverage($this->clover(<<<XML
    <package name="tool_phpunitchecker\\multi">
      <file name="/var/www/html/multi.php">
        <class name="tool_phpunitchecker\\multi\\first" namespace="tool_phpunitchecker\\multi">
          <metrics complexity="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
        </class>
        <class name="tool_phpunitchecker\\multi\\second" namespace="tool_phpunitchecker\\multi">
          <metrics complexity="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
        </class>
        <line num="5" type="method" name="one" visibility="public" complexity="1" crap="2" count="0"/>
        <line num="9" type="method" name="two" visibility="public" complexity="1" crap="2" count="0"/>
        <metrics loc="12" ncloc="8" classes="2" methods="2" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="2" coveredstatements="0" elements="4" coveredelements="0"/>
      </file>
    </package>
XML));

        $file = $report->get_packages()[0]['files'][0];

        $this->assertCount(2, $file['classes']);
        $this->assertFalse($file['classes'][0]['hasmethods']);
        $this->assertSame([], $file['classes'][0]['methods']);
        $this->assertFalse($file['classes'][1]['hasmethods']);

        // The methods are still available on the file itself.
        $this->assertTrue($file['hasmethods']);
        $this->assertCount(2, $file['methods']);
        $this->assertSame('one', $file['methods'][0]['name']);
        $this->assertSame('two', $file['methods'][1]['name']);
    }

    /**
     * Exported template data should contain the coverage summary and drill-down.
     *
     * @return void
     */
    public function test_export_contains_coverage_summary_and_details(): void {
        global $OUTPUT;

        $report = new report_coverage($this->clover(<<<XML
    <package name="tool_phpunitchecker\\sample">
      <file name="/var/www/html/sample.php">
        <class name="tool_phpunitchecker\\sample\\thing" namespace="tool_phpunitchecker\\sample">
          <metrics complexity="3" methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="4" coveredstatements="2" elements="6" coveredelements="3"/>
        </class>
        <line num="10" type="method" name="covered_method" visibility="public" complexity="1" crap="1" count="5"/>
        <line num="12" type="stmt" count="5"/>
        <line num="20" type="method" name="uncovered_method" visibility="protected" complexity="2" crap="6" count="0"/>
        <line num="22" type="stmt" count="0"/>
        <metrics loc="30" ncloc="20" classes="1" methods="2" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="4" coveredstatements="2" elements="6" coveredelements="3"/>
      </file>
    </package>
XML));

        $data = $report->export_for_template($OUTPUT);

        $this->assertTrue($data->hasdata);
        $this->assertCount(1, $data->packages);
        $this->assertSame(1, $data->filecount);
        $this->assertSame(1, $data->classcount);
        $this->assertSame(1, $data->coveredmethods);
        $this->assertSame(2, $data->totalmethods);

        // Overall coverage is the covered/total elements ratio.
        $this->assertSame(50.0, $data->percentage);
        $this->assertSame('bg-warning', $data->barclass);

        // The summary exposes the element, method and statement bars.
        $this->assertCount(3, $data->summaryitems);
        [$elements, $methods, $statements] = $data->summaryitems;

        $this->assertSame(3, $elements['covered']);
        $this->assertSame(6, $elements['total']);
        $this->assertSame(50.0, $elements['percentage']);
        $this->assertTrue($elements['hasdata']);
        $this->assertSame('bg-warning', $elements['barclass']);

        $this->assertSame(1, $methods['covered']);
        $this->assertSame(2, $methods['total']);
        $this->assertSame(2, $statements['covered']);
        $this->assertSame(4, $statements['total']);

        // Exported method rows carry display helpers.
        $class = $data->packages[0]['files'][0]['classes'][0];
        $this->assertSame('✓', $class['methods'][0]['icon']);
        $this->assertSame('badge bg-success', $class['methods'][0]['badgeclass']);
        $this->assertSame('✗', $class['methods'][1]['icon']);
        $this->assertSame('badge bg-danger', $class['methods'][1]['badgeclass']);
    }

    /**
     * A fully covered class should be reported with the success colour.
     *
     * @return void
     */
    public function test_full_coverage_uses_success_colour(): void {
        $report = new report_coverage($this->clover(<<<XML
    <package name="tool_phpunitchecker\\done">
      <file name="/var/www/html/done.php">
        <class name="tool_phpunitchecker\\done\\thing" namespace="tool_phpunitchecker\\done">
          <metrics complexity="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="1" elements="2" coveredelements="2"/>
        </class>
        <line num="10" type="method" name="run" visibility="public" complexity="1" crap="1" count="3"/>
        <line num="12" type="stmt" count="3"/>
        <metrics loc="15" ncloc="10" classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="1" elements="2" coveredelements="2"/>
      </file>
    </package>
XML));

        $data = $report->export_for_template(null);

        $this->assertSame(100.0, $data->percentage);
        $this->assertSame('bg-success', $data->barclass);
        $this->assertSame('text-success', $data->packages[0]['textclass']);
    }

    /**
     * The component filter should keep only packages of the selected components.
     *
     * @return void
     */
    public function test_selected_suites_filter_packages_by_component(): void {
        $report = new report_coverage($this->clover(<<<XML
    <package name="tool_phpunitchecker\\sample">
      <file name="/var/www/html/sample.php">
        <class name="tool_phpunitchecker\\sample\\thing" namespace="tool_phpunitchecker\\sample">
          <metrics complexity="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
        </class>
        <line num="10" type="method" name="run" visibility="public" complexity="1" crap="2" count="0"/>
        <metrics loc="12" ncloc="8" classes="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
      </file>
    </package>
    <package name="other_plugin\\thing">
      <file name="/var/www/html/other.php">
        <class name="other_plugin\\thing\\widget" namespace="other_plugin\\thing">
          <metrics complexity="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
        </class>
        <line num="10" type="method" name="run" visibility="public" complexity="1" crap="2" count="0"/>
        <metrics loc="12" ncloc="8" classes="1" methods="1" coveredmethods="0" conditionals="0" coveredconditionals="0" statements="1" coveredstatements="0" elements="2" coveredelements="0"/>
      </file>
    </package>
XML));

        // Without a filter every package is parsed.
        $this->assertCount(2, $report->get_packages());

        // A suite name is reduced to its component before filtering.
        $report->set_selected_suites(['tool_phpunitchecker_testsuite']);
        $packages = $report->get_packages();
        $this->assertCount(1, $packages);
        $this->assertSame('tool_phpunitchecker\\sample', $packages[0]['name']);

        // A plain component name is accepted as well.
        $report->set_selected_suites(['other_plugin']);
        $packages = $report->get_packages();
        $this->assertCount(1, $packages);
        $this->assertSame('other_plugin\\thing', $packages[0]['name']);
    }

    /**
     * Invalid XML should yield no packages and be reported as empty.
     *
     * @return void
     */
    public function test_invalid_report_yields_no_packages(): void {
        $report = new report_coverage('this is not valid xml');

        $this->assertSame([], $report->get_packages());
        $this->assertFalse($report->export_for_template(null)->hasdata);
    }

    /**
     * Empty report content should be rejected.
     *
     * @return void
     */
    public function test_empty_report_content_is_rejected(): void {
        $this->expectException(\core\exception\moodle_exception::class);

        new report_coverage('');
    }
}
