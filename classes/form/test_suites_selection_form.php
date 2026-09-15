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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use core\exception\moodle_exception;
use moodleform;
use tool_phpunitchecker\phpunit;
use tool_phpunitchecker\report_coverage;
use tool_phpunitchecker\report_output;

/**
 * Form with the test suite selection and some options on
 * how to generate the test results.
 *
 * @package tool_phpunitchecker
 * @copyright 2026 Alissa Cenga
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_suites_selection_form extends moodleform {
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
            'autocomplete',
            'testsuites',
            get_string('testsuites', 'tool_phpunitchecker'),
            [],
            [
                'ajax' => 'tool_phpunitchecker/testsuite-selector',
                'multiple' => true,
                'placeholder' => get_string('search'),
            ],
        );
        $mform->setType('testsuites', PARAM_TAGLIST);
        $mform->addRule('testsuites', get_string('required'), 'required', null, 'client');

        $mform->addElement(
            'checkbox',
            'codecoverage',
            get_string('codecoverage', 'tool_phpunitchecker')
        );
        $mform->setType('codecoverage', PARAM_BOOL);

        $this->add_action_buttons(false, get_string('runtestsuites', 'tool_phpunitchecker'));
    }

    /**
     * Execute tests when action button from form was hit.
     * Returns an array <int,string> which is the return code (0 = success)
     * and html with the testreport to display.
     * @return array
     * @throws moodle_exception
     */
    public function run_button_action(): array {
        global $OUTPUT;
        $data = $this->get_data();
        if (!empty($data->testsuites)) {
            $phpunit = phpunit::get_instance();
            if (!$phpunit->is_ready()) {
                return [1, get_string('phpunitnotready', 'tool_phpunitchecker')];
            }
            if (!empty($data->codecoverage)) {
                $phpunit->enable_code_coverage();
            }
            $res = $phpunit->run_suites($data->testsuites);
            if (empty($res->junitxml)) {
                return [1, 'error creating test report'];
            }
            $reportoutput = new report_output($res->junitxml, $res->output);
            $allpassed = $reportoutput->all_tests_passed();
            $html = $OUTPUT->render_from_template(
                'tool_phpunitchecker/report_output',
                $reportoutput->export_for_template($OUTPUT)
            );
            if (!empty($data->codecoverage)) {
                $reportoutput = new report_coverage($res->cloverxml, '');
                $reportoutput->set_selected_suites($data->testsuites);
                $html .= $OUTPUT->render_from_template(
                    'tool_phpunitchecker/code_coverage',
                    $reportoutput->export_for_template($OUTPUT)
                );
            }
            return [$allpassed ? 0 : 1, $html];
        }
        return [1, 'error creating test report'];
    }
}
