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

namespace tool_phpunitchecker\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_phpunitchecker\phpunit;

/**
 * External API for fetching PHPUnit test suites.
 *
 * @package     tool_phpunitchecker
 * @copyright   2026 Alissa Cenga
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testsuites extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Search name', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Gets fixed PHPUnit test suite options.
     *
     * @param string $name Search name.
     * @return array
     */
    public static function execute(string $name = ''): array {
        [
            'name' => $name,
        ] = self::validate_parameters(self::execute_parameters(), [
            'name' => $name,
        ]);
        self::validate_context(\context_system::instance());

        $phpunit = phpunit::get_instance();
        $testsuites = $phpunit->list_suites();

        $results = [];
        foreach ($testsuites as $testsuitename => $testcount) {
            if ($name === '' || stripos($testsuitename, $name) !== false) {
                $results[] = [
                    'name' => $testsuitename,
                    'testcount' => $testcount,
                ];
            }
        }

        return $results;
    }

    /**
     * Describes the returned data.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Test suite name'),
                'testcount' => new external_value(PARAM_INT, 'Test suite tests count'),
            ])
        );
    }
}
