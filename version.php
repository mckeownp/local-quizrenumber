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
 * Version details for local_quizrenumber.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_quizrenumber';
$plugin->version   = 2026081700;
// Moodle 4.0 is the hard floor: the plugin only ships question bank compatibility
// implementations for the 4.0-4.5 and 5.0+ eras. See the plan, sections 2 and 13.
$plugin->requires  = 2022041900;
$plugin->release   = '1.0.0';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->dependencies = [
    'mod_quiz' => ANY_VERSION,
];
