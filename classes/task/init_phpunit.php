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

namespace tool_phpunitchecker\task;

use core\task\adhoc_task;
use tool_phpunitchecker\phpunit;

/**
 * Adhoc task that processes the initialization of phpunit tests.
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 MoodleMoot DACH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class init_phpunit extends adhoc_task {
    /**
     * Run the task to initiate the phpunit testsuites.
     */
    public function execute() {
        $customdata = $this->get_custom_data();
        if (!isset($customdata->id)) {
            throw new \coding_exception('Custom data must contain an id.');
        }

        phpunit::get_instance()->make_ready();
    }
}
