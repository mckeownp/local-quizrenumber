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
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_quizrenumber';
$plugin->version   = 2026081800;
// Moodle 4.5 LTS is the hard floor. Everything below it is end of life, and the CI matrix
// cannot reach it anyway: Moodle 4.0-4.4 top out at PHP 8.0 while the lowest tested PHP is
// 8.1. Claiming a range that is never exercised is a liability for a tool that renames
// question bank records in bulk.
$plugin->requires  = 2024100700;
$plugin->release   = '1.0.0';
// Feature complete and covered by tests on Moodle 4.5 and 5.1, but not yet used in anger on
// a production site. Raise to MATURITY_STABLE once it has been.
$plugin->maturity  = MATURITY_BETA;
$plugin->dependencies = [
    'mod_quiz' => ANY_VERSION,
];
