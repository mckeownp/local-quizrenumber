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

namespace local_quizrenumber;

use local_quizrenumber\compat\question_source_interface;

/**
 * Finds the quizzes in a course and summarises what is in them.
 *
 * Uses course modinfo rather than querying {quiz} directly so that availability, visibility
 * and section naming all come from the same place the course page uses.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_finder {
    /**
     * List the quizzes in a course that the current user may work on.
     *
     * @param int $courseid
     * @param question_source_interface $source Used to count fixed against random slots.
     * @return array List of ['id', 'name', 'sectionname', 'fixedcount', 'randomcount', 'visible'].
     */
    public static function get_course_quizzes(int $courseid, question_source_interface $source): array {
        $modinfo = get_fast_modinfo($courseid);
        $coursecontext = \context_course::instance($courseid);
        $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $coursecontext);

        $cms = [];
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            if (!$cm->visible && !$canviewhidden) {
                continue;
            }
            $cms[(int)$cm->instance] = $cm;
        }

        if (empty($cms)) {
            return [];
        }

        // One query for the whole course. Courses with a couple of hundred quizzes are real,
        // and resolving every slot of every quiz just to draw a checklist does not scale.
        $summaries = $source->get_quiz_summaries(array_keys($cms));

        $quizzes = [];
        foreach ($cms as $quizid => $cm) {
            $summary = isset($summaries[$quizid]) ? $summaries[$quizid] : ['fixed' => 0, 'random' => 0, 'total' => 0];

            $section = $cm->get_section_info();
            $quizzes[] = [
                'id' => $quizid,
                'cmid' => (int)$cm->id,
                'name' => format_string($cm->name),
                'sectionname' => $section ? get_section_name($courseid, $section) : '',
                'fixedcount' => $summary['fixed'],
                'randomcount' => $summary['random'],
                'total' => $summary['total'],
                'visible' => (bool)$cm->visible,
                // Flagged so the UI can warn before the user reaches the preview and wonders
                // why nothing changed.
                'mostlyrandom' => ($summary['fixed'] === 0 && $summary['random'] > 0),
            ];
        }

        return $quizzes;
    }

    /**
     * Keep only the quiz ids that really are quizzes in this course and visible to this user.
     *
     * Quiz ids arrive in the request as a hidden field, so they are checked against the
     * course before anything is read or written through them.
     *
     * @param array $quizids Ids as they arrived in the request.
     * @param int $courseid Course being worked on.
     * @param question_source_interface $source
     * @return int[] The ids that survived.
     */
    public static function filter_quizids(array $quizids, int $courseid, question_source_interface $source): array {
        if (empty($quizids)) {
            return [];
        }

        $allowed = [];
        foreach (self::get_course_quizzes($courseid, $source) as $quiz) {
            $allowed[$quiz['id']] = true;
        }

        $filtered = [];
        foreach ($quizids as $quizid) {
            if (isset($allowed[(int)$quizid])) {
                $filtered[] = (int)$quizid;
            }
        }
        return $filtered;
    }

    /**
     * Load the slots for several quizzes at once, in quiz then slot order.
     *
     * @param array $quizids Quiz ids, in the order the user selected them.
     * @param question_source_interface $source
     * @return \local_quizrenumber\compat\question_slot[] Flat list, ready for renumber_service::build_plan().
     */
    public static function get_slots_for_quizzes(array $quizids, question_source_interface $source): array {
        $slots = [];
        foreach ($quizids as $quizid) {
            foreach ($source->get_quiz_questions((int)$quizid) as $slot) {
                $slots[] = $slot;
            }
        }
        return $slots;
    }
}
