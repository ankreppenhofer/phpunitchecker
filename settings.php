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
 * PHPunitChecker integration
 *
 * @package    tool_phpunitchecker
 * @copyright  2026 Alissa Cenga
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    $settings = new admin_settingpage(
        'toolphpunitcheckersettings',
        get_string('pluginsettings', 'tool_phpunitchecker')
    );

    $settings->add(new admin_setting_configcheckbox(
        'tool_phpunitchecker/enableconfetti',
        get_string('enableconfetti', 'tool_phpunitchecker'),
        get_string('enableconfetti_desc', 'tool_phpunitchecker'),
        0,
    ));

    $ADMIN->add('development', $settings);

    $ADMIN->add(
        'development',
        new admin_externalpage(
            'toolphpunitchecker',
            get_string('pluginname', 'tool_phpunitchecker'),
            "$CFG->wwwroot/$CFG->admin/tool/phpunitchecker/index.php"
        )
    );

}
