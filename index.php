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

use tool_phpunitchecker\form\init_phpunit;
use tool_phpunitchecker\form\test_suites_selection_form;
use tool_phpunitchecker\phpunit;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('toolphpunitchecker');

$mform = !phpunit::get_instance()->is_ready() ? new init_phpunit() : new test_suites_selection_form();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_phpunitchecker'));
echo $OUTPUT->box_start();

$mform->display();

if ($mform->is_submitted()) {
    [$res, $html] = $mform->run_button_action();
    if ($mform instanceof init_phpunit && $res === 0 && $html !== '') {
        // The init form returns the id of the queued adhoc task. Render a live
        // status region and let JavaScript poll the task until it has finished.
        $containerid = 'tool_phpunitchecker-taskstatus';
        echo html_writer::div(
            get_string('phpunitpreparing', 'tool_phpunitchecker'),
            'alert alert-info',
            ['id' => $containerid, 'role' => 'status', 'aria-live' => 'polite']
        );
        $PAGE->requires->js_call_amd(
            'tool_phpunitchecker/task-status',
            'init',
            [$html, $containerid]
        );
    } else {
        if ($res === 0 && get_config('tool_phpunitchecker', 'enableconfetti')) {
            $PAGE->requires->js_call_amd('local_confetti/confetti', 'init', [[
                'preset' => get_config('local_confetti', 'confettipreset') ?: 'realistic',
                'text' => get_string('testspassed', 'tool_phpunitchecker'),
            ]]);
        }
        echo $html;
    }

    echo $html;
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
