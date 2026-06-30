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
 * @copyright  2026 Alissa Cenga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_phpunitchecker\form\test_suites_selection_form;
use tool_phpunitchecker\phpunit;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('toolphpunitchecker');

$mform = new test_suites_selection_form();
$phpunit = phpunit::get_instance();

if ($data = $mform->get_data()) {
    if (!empty($data->makephpunitready)) {
        if ($phpunit->make_ready()) {
            \core\notification::success(get_string('phpunitready', 'tool_phpunitchecker'));
        } else {
            \core\notification::error(get_string('phpunitreadinessfailed', 'tool_phpunitchecker'));
            \core\notification::error(s($phpunit->get_output()));
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_phpunitchecker'));
echo $OUTPUT->box_start();

$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
