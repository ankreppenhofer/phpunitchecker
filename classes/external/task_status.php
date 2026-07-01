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

use core\task\manager;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_phpunitchecker\task\init_phpunit as init_phpunit_task;

/**
 * External API for fetching PHPUnit test suites.
 *
 * @package     tool_phpunitchecker
 * @copyright   2026 Alissa Cenga
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_status extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_TEXT, 'Task ID', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Gets the status of a specific PHPUnit task.
     *
     * @param string $id Task ID.
     * @return array
     */
    public static function execute(string $id = ''): array {
        [
            'id' => $id,
        ] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);
        self::validate_context(\context_system::instance());

        $tasks = manager::get_adhoc_tasks(init_phpunit_task::class);
        foreach ($tasks as $task) {
            $customdata = $task->get_custom_data();
            if (isset($customdata->id) && $customdata->id === $id) {
                return [
                    'status' => empty($task->get_timestarted()) ? 'queued' : 'running',
                ];
            }
        }
        return [
            'status' => 'done',
        ];
    }

    /**
     * Describes the returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status of the task'),
        ]);
    }
}
