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

namespace tool_phpunitchecker\form;

use core\task\manager;
use moodleform;
use tool_phpunitchecker\task\init_phpunit as init_phpunit_task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form with one button to initialize the phpunit tests.
 *
 * @package tool_phpunitchecker
 * @copyright 2026 MoodleMoot DACH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class init_phpunit extends moodleform {
    /**
     * Form definition.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'header',
            'formsection',
            get_string('rununittests', 'tool_phpunitchecker')
        );

        $mform->addElement(
            'submit',
            'makephpunitready',
            get_string('makephpunitready', 'tool_phpunitchecker')
        );
    }

    /**
     * Initialize the phpunit test when the action button from the form was hit.
     * Output is automatically handled via the noticifaction messages.
     * Returns an array <int,string> which is the return code (0 = success)
     * and empty html to display.
     * @return array
     */
    public function run_button_action(): array {
        $data = $this->get_data();
        if (!empty($data->makephpunitready)) {
            $id = uniqid();
            $task = new init_phpunit_task();
            $task->set_component('tool_phpunitchecker');
            $task->set_custom_data(['id' => $id]);
            manager::queue_adhoc_task($task, true);
            return [0, $id];
        }
        return [0, ''];
    }
}
