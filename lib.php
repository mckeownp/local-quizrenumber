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
 * Callbacks for local_quizrenumber.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No MOODLE_INTERNAL check here: this file only declares a function and has no side
// effects, which is the case the Moodle standard wants the check left out of.

/**
 * Add the renumbering tool to the course administration menu.
 *
 * @param navigation_node $navigation The course settings navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 * @return void
 */
function local_quizrenumber_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
) {

    if (!has_capability('local/quizrenumber:manage', $context)) {
        return;
    }

    $url = new moodle_url('/local/quizrenumber/index.php', ['id' => $course->id]);
    $node = navigation_node::create(
        get_string('pluginname', 'local_quizrenumber'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_quizrenumber',
        new pix_icon('i/settings', '')
    );

    $navigation->add_node($node);
}
