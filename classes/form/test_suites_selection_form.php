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

use moodleform;

/**
 * Enrol users form.
 *
 * Simple form to search for users and add them using a manual enrolment to this course.
 *
 * @package tool_phpunitchecker
 * @copyright 2026 Alissa Cenga
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

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

        $mform->addElement(
            'header',
            'displayoptionssection',
            get_string('displayoptions', 'tool_phpunitchecker')
        );

        $mform->addElement(
            'advcheckbox',
            'displaydeprecations',
            get_string('displaydeprecations', 'tool_phpunitchecker')
        );

        $mform->addElement(
            'advcheckbox',
            'displaywarnings',
            get_string('displaywarnings', 'tool_phpunitchecker')
        );
        $mform->setDefault('displaywarnings', 1);

        $mform->addElement(
            'advcheckbox',
            'displayerrors',
            get_string('displayerrors', 'tool_phpunitchecker')
        );
        $mform->setDefault('displayerrors', 1);

        $mform->addElement(
            'advcheckbox',
            'displaynotices',
            get_string('displaynotices', 'tool_phpunitchecker')
        );
        $mform->setDefault('displaynotices', 1);

        $mform->addElement(
            'submit',
            'makephpunitready',
            get_string('makephpunitready', 'tool_phpunitchecker')
        );

        $this->add_action_buttons(false, get_string('runtestsuites', 'tool_phpunitchecker'));


    }

    /**
     * Validate the submitted form data.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        return [];
    }
}
