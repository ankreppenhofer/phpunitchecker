<?php
// This file is part of Moodle - http://moodle.org/

/**
 * External functions for the tool_phpunitchecker plugin.
 *
 * @package     tool_phpunitchecker
 * @copyright   2026 Alissa Cenga
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_phpunitchecker_get_testsuites' => [
        'classname' => 'tool_phpunitchecker\external\testsuites',
        'methodname' => 'execute',
        'description' => 'Get available test suites from PHPUnit',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
