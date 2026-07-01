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
 * Example tests for producing different PHPUnit JUnit result types.
 *
 * These tests are intentionally mixed: some pass, some fail, some produce
 * errors, warnings, notices, risky tests, incomplete tests and skipped tests.
 *
 * They are useful for checking how the PHPUnit checker renders different
 * JUnit XML result statuses.
 *
 * @package    tool_phpunitchecker
 * @coversNothing
 */
final class report_types_test extends \advanced_testcase {

    /**
     * Example of a passing test.
     *
     * @return void
     */
    public function test_passes(): void {
        $this->assertTrue(true);
    }

    /**
     * Example of a simple failure.
     *
     * This creates a PHPUnit failure, not a PHP error.
     *
     * @return void
     */
    public function test_fails(): void {
        $this->assertSame('expected value', 'actual value');
    }

    /**
     * Example of a simple error.
     *
     * This creates an error entry in the JUnit report.
     *
     * @return void
     */
    public function test_has_error(): void {
        throw new \Error('This is a simple example error.');
    }

    /**
     * Example of a warning.
     *
     * Depending on the PHPUnit configuration, this may be reported as a warning
     * or as an issue in the test output.
     *
     * @return void
     */
    public function test_has_warning(): void {
        trigger_error('This is a simple example warning.', E_USER_WARNING);

        $this->assertTrue(true);
    }

    /**
     * Example of a notice.
     *
     * Depending on the PHPUnit configuration, this may be reported as a notice
     * or as an issue in the test output.
     *
     * @return void
     */
    public function test_has_notice(): void {
        trigger_error('This is a simple example notice.', E_USER_NOTICE);

        $this->assertTrue(true);
    }

    /**
     * Example of a risky test.
     *
     * This test intentionally performs no assertion. With strict PHPUnit
     * settings, PHPUnit marks this as risky.
     *
     * @return void
     */
    public function test_is_risky(): void {
        $value = 1 + 1;
    }

    /**
     * Example of an incomplete test.
     *
     * @return void
     */
    public function test_is_incomplete(): void {
        $this->markTestIncomplete('This is a simple example incomplete test.');
    }

    /**
     * Example of a skipped test.
     *
     * @return void
     */
    public function test_is_skipped(): void {
        $this->markTestSkipped('This is a simple example skipped test.');
    }
}