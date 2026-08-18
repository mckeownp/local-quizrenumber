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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Site-wide defaults for local_quizrenumber.
 *
 * These only seed the form. Every value is still validated per request, so setting an
 * out-of-range default here cannot produce an out-of-range renumbering.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_quizrenumber\renumber_settings;

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_quizrenumber',
        get_string('settingsheading', 'local_quizrenumber')
    );

    $settings->add(new admin_setting_configtext(
        'local_quizrenumber/defaultstartnumber',
        get_string('defaultstartnumber', 'local_quizrenumber'),
        get_string('defaultstartnumber_desc', 'local_quizrenumber'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizrenumber/defaultincrement',
        get_string('defaultincrement', 'local_quizrenumber'),
        get_string('defaultincrement_desc', 'local_quizrenumber', renumber_settings::MAX_INCREMENT),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_quizrenumber/defaultpadding',
        get_string('defaultpadding', 'local_quizrenumber'),
        get_string('defaultpadding_desc', 'local_quizrenumber'),
        4,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
