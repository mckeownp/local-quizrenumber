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

namespace local_quizrenumber\compat;

/**
 * The contract between the plugin and whichever question bank era Moodle is running.
 *
 * Everything the plugin needs to know about question banks is expressed here. Adding
 * support for a future Moodle version should mean writing one more implementation and
 * teaching question_source_factory about it, and changing nothing else.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_source_interface {
    /**
     * Ordered list of the questions in a quiz, whichever bank or context they live in.
     *
     * @param int $quizid Id of the quiz.
     * @return question_slot[] Indexed by slot number, ordered by slot number ascending.
     */
    public function get_quiz_questions(int $quizid): array;

    /**
     * Count the fixed and random slots in many quizzes at once.
     *
     * The quiz selection page needs nothing more than these counts, and a course can easily
     * hold a couple of hundred quizzes, so this exists to answer the whole page in one query
     * rather than resolving every slot of every quiz.
     *
     * @param array $quizids Quiz ids to summarise.
     * @return array Quiz id => ['fixed' => int, 'random' => int, 'total' => int]. Quizzes with
     *               no slots at all may be absent; callers should treat a missing id as zeroes.
     */
    public function get_quiz_summaries(array $quizids): array;

    /**
     * Rename a question, respecting the versioning rules of this Moodle era.
     *
     * Implementations must rename the version the caller asked about rather than
     * silently creating a new one, and must invalidate the question bank caches.
     *
     * @param int $questionid Id of the {question} row to rename.
     * @param string $newname The new name, already validated and length-capped by the caller.
     * @return void
     */
    public function rename_question(int $questionid, string $newname): void;

    /**
     * How many quiz slots anywhere on the site reference this question's bank entry.
     *
     * Used for the "also used elsewhere" badge, so it deliberately counts across all
     * courses rather than only the one being worked on: renaming affects them all.
     *
     * @param int $questionid Id of a {question} row.
     * @return int One if the question is used exactly once, more if it is shared.
     */
    public function get_usage_count(int $questionid): int;

    /**
     * Describe where else a question is used, for the badge tooltip.
     *
     * @param int $questionid Id of a {question} row.
     * @param int $excludequizid Quiz to leave out of the list, normally the one being renumbered.
     * @return array List of ['coursename' => string, 'quizname' => string, 'samecourse' => bool].
     */
    public function get_usage_details(int $questionid, int $excludequizid = 0): array;

    /**
     * Check that this site is in a state where the question bank can be read meaningfully.
     *
     * Exists for Moodle 5.0 sites whose question bank migration adhoc tasks have not yet
     * finished, where the banks look empty rather than broken. Implementations that have
     * nothing to check should do nothing.
     *
     * @param int $courseid Course the user is working in.
     * @throws \moodle_exception If the site is not ready to be renumbered.
     * @return void
     */
    public function check_ready(int $courseid): void;
}
