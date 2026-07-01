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
class inspect_file extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'file' => new external_value(PARAM_TEXT, 'File path', VALUE_DEFAULT, ''),
            'line' => new external_value(PARAM_INT, 'Line number', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Gets the snippet of a file with the current line highlighted.
     *
     * @param string $file File path.
     * @param int $line Line number.
     * @return array
     */
    public static function execute(string $file = '', int $line = 0): array {
        [
            'file' => $file,
            'line' => $line,
        ] = self::validate_parameters(self::execute_parameters(), [
            'file' => $file,
            'line' => $line,
        ]);
        file_exists($file) || throw new \moodle_exception('filenotfound', 'core', '', $file);
        $content = explode("\n", file_get_contents($file));
        if ($line < 1 || $line > count($content)) {
            throw new \moodle_exception('linenumberoutofrange', 'tool_phpunitchecker', '', $line);
        }
        $content = array_slice($content, max(0, $line - 6), 11, true);
        $html = '';
        foreach ($content as $lineno => $linecontent) {
            $lineno++;
            $highlight = ($lineno === $line) ? 'highlight' : '';
            $html .= '<div class="file-inspector-line ' . $highlight . '">';
            $html .= '<span class="file-inspector-lineno">' . $lineno . '</span>';
            $html .= '<span class="file-inspector-content">' . htmlspecialchars($linecontent) . '</span>';
            $html .= '</div>';
        }
        return [
            'content' => $html,
        ];
    }

    /**
     * Describes the returned data.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'content' => new external_value(PARAM_RAW, 'File snippet formated as HTML'),
        ]);
    }
}
