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

use context_module;
use mod_quiz\question\bank\qbank_helper;
use question_bank;

/**
 * Question source for the Moodle 4.5 question bank era.
 *
 * In this era a quiz slot reaches its question through
 * quiz_slots -> question_references -> question_bank_entries -> question_versions -> question,
 * and the category tree that owns the question lives in the course context. Random slots go
 * through question_set_references instead and resolve to a category, not a question.
 *
 * Resolving a slot to its current version is genuinely fiddly (a slot may pin a version, or
 * float to the latest non-draft one), so this class leans on mod_quiz's own qbank_helper
 * rather than reimplementing that join. Only the queries core has no API for - counting how
 * many slots share a bank entry - are written out here.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_source_v4 implements question_source_interface {
    /** @var array Cache of context id => whether $USER can edit questions there, per request. */
    protected array $editablecache = [];

    #[\Override]
    public function get_quiz_questions(int $quizid): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id, name, course', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
        $quizcontext = context_module::instance($cm->id);

        $structure = qbank_helper::get_question_structure($quizid, $quizcontext);
        $quizname = format_string($quiz->name, true, ['context' => $quizcontext]);

        $slots = [];
        $bankentryids = [];
        $categoryids = [];
        $slotcategories = [];

        foreach ($structure as $slotdata) {
            $slot = new question_slot($quizid, $quizname, (int)$slotdata->slot, (int)$slotdata->slotid);
            $slot->name = (string)$slotdata->name;
            $slot->bankcontextid = empty($slotdata->contextid) ? null : (int)$slotdata->contextid;

            if ($slotdata->qtype === 'random') {
                $slot->israndom = true;
            } else if ($slotdata->qtype === 'missingtype' && empty($slotdata->questionbankentryid)) {
                $slot->ismissing = true;
            } else {
                $slot->questionid = (int)$slotdata->questionid;
                $slot->bankentryid = (int)$slotdata->questionbankentryid;
                $bankentryids[] = $slot->bankentryid;
            }

            if (!empty($slotdata->category)) {
                $categoryids[(int)$slotdata->category] = (int)$slotdata->category;
                $slotcategories[$slot->slot] = (int)$slotdata->category;
            }

            $slots[$slot->slot] = $slot;
        }

        $categorynames = $this->get_category_names($categoryids);
        $usagecounts = $this->get_usage_counts_for_entries($bankentryids);

        foreach ($slots as $slotnumber => $slot) {
            $categoryid = $slotcategories[$slotnumber] ?? 0;
            $slot->bankname = $this->describe_bank(
                $slot->bankcontextid,
                $categorynames[$categoryid] ?? '',
                $quizcontext
            );
            if ($slot->bankentryid !== null && isset($usagecounts[$slot->bankentryid])) {
                $slot->usagecount = $usagecounts[$slot->bankentryid];
            }
            $slot->editable = $this->can_edit_in_context($slot->bankcontextid);
        }

        return $slots;
    }

    #[\Override]
    public function get_quiz_summaries(array $quizids): array {
        global $DB;

        if (empty($quizids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'qz');

        // A slot is random when it carries a question_set_reference (it resolves to a
        // category) and fixed when it carries a question_reference (it names one entry).
        // Counting the two reference tables is enough for the checklist; slots are only
        // fully resolved later, when the user has actually chosen some quizzes.
        $records = $DB->get_records_sql("
                SELECT slot.quizid,
                       COUNT(slot.id) AS total,
                       COUNT(qsr.id) AS randomcount,
                       COUNT(qr.id) AS fixedcount
                  FROM {quiz_slots} slot
             LEFT JOIN {question_references} qr
                       ON qr.itemid = slot.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
             LEFT JOIN {question_set_references} qsr
                       ON qsr.itemid = slot.id AND qsr.component = 'mod_quiz' AND qsr.questionarea = 'slot'
                 WHERE slot.quizid $insql
              GROUP BY slot.quizid
                ", $params);

        $summaries = [];
        foreach ($records as $record) {
            $summaries[(int)$record->quizid] = [
                'fixed' => (int)$record->fixedcount,
                'random' => (int)$record->randomcount,
                'total' => (int)$record->total,
            ];
        }
        return $summaries;
    }

    #[\Override]
    public function rename_question(int $questionid, string $newname): void {
        global $DB, $USER;

        // Deliberately a direct update of the current version rather than a new question
        // version. A bulk rename that forked every question would fill the version history
        // with noise, and slots pinned to a specific version would keep showing the old name.
        $record = new \stdClass();
        $record->id = $questionid;
        $record->name = $newname;
        $record->timemodified = time();
        $record->modifiedby = $USER->id;
        $DB->update_record('question', $record);

        // Core owns the question caches; tell it the row changed rather than purging by hand.
        question_bank::notify_question_edited($questionid);
    }

    #[\Override]
    public function get_usage_count(int $questionid): int {
        $bankentryid = $this->get_bank_entry_id($questionid);
        if ($bankentryid === null) {
            return 0;
        }
        $counts = $this->get_usage_counts_for_entries([$bankentryid]);
        return $counts[$bankentryid] ?? 0;
    }

    #[\Override]
    public function get_usage_details(
        int $questionid,
        int $excludequizid = 0,
        int $limit = 0,
        int $comparecourseid = 0
    ): array {
        global $DB;

        $bankentryid = $this->get_bank_entry_id($questionid);
        if ($bankentryid === null) {
            return ['places' => [], 'total' => 0];
        }

        $params = ['bankentryid' => $bankentryid];
        $exclude = '';
        if ($excludequizid) {
            $params['excludequizid'] = $excludequizid;
            $exclude = ' AND q.id <> :excludequizid';
        }

        $from = "  FROM {question_references} qr
                  JOIN {quiz_slots} slot ON slot.id = qr.itemid
                  JOIN {quiz} q ON q.id = slot.quizid
                  JOIN {course} c ON c.id = q.course
                 WHERE qr.component = 'mod_quiz'
                       AND qr.questionarea = 'slot'
                       AND qr.questionbankentryid = :bankentryid
                       $exclude";

        // The total is counted separately rather than inferred from the returned rows,
        // because the caller may have asked for only the first few. Counting distinct quizzes
        // (not slots) matters: a quiz that uses the same question in two slots is one place.
        $total = (int)$DB->get_field_sql("SELECT COUNT(DISTINCT q.id) $from", $params);

        $records = $DB->get_records_sql("
                SELECT DISTINCT q.id AS quizid, q.name AS quizname, c.id AS courseid, c.fullname AS coursename
                $from
              ORDER BY c.fullname, q.name
                ", $params, 0, $limit > 0 ? $limit : 0);

        // An explicit compare course wins. Falling back to the excluded quiz's course only
        // works when there is one, which is why listing every place needs the explicit value.
        $currentcourseid = $comparecourseid ?: $this->get_course_id_for_quiz($excludequizid);

        $places = [];
        foreach ($records as $record) {
            $coursecontext = \context_course::instance((int)$record->courseid, IGNORE_MISSING);
            $places[] = [
                'courseid' => (int)$record->courseid,
                'coursename' => format_string($record->coursename, true, ['context' => $coursecontext]),
                'quizid' => (int)$record->quizid,
                'quizname' => format_string($record->quizname, true, ['context' => $coursecontext]),
                'samecourse' => ((int)$record->courseid === $currentcourseid),
            ];
        }

        return ['places' => $places, 'total' => $total];
    }

    #[\Override]
    public function check_ready(int $courseid): void {
        // Nothing to check on 4.x: the course question bank always exists.
    }

    /**
     * Resolve a question id to the bank entry that owns it.
     *
     * @param int $questionid
     * @return int|null Null if the question has no bank entry, which should not happen on 4.x.
     */
    protected function get_bank_entry_id(int $questionid): ?int {
        global $DB;
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $questionid]);
        return $entryid ? (int)$entryid : null;
    }

    /**
     * Count how many quiz slots reference each of the given bank entries, site-wide.
     *
     * Site-wide is intentional: renaming a question changes it everywhere it is used, so the
     * warning has to reflect everywhere, not just the course in front of the user.
     *
     * @param array $bankentryids
     * @return array Bank entry id => number of slots using it.
     */
    protected function get_usage_counts_for_entries(array $bankentryids): array {
        global $DB;

        if (empty($bankentryids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_unique($bankentryids), SQL_PARAMS_NAMED, 'bqe');

        $counts = $DB->get_records_sql("
                SELECT qr.questionbankentryid, COUNT(DISTINCT slot.id) AS usages
                  FROM {question_references} qr
                  JOIN {quiz_slots} slot ON slot.id = qr.itemid
                 WHERE qr.component = 'mod_quiz'
                       AND qr.questionarea = 'slot'
                       AND qr.questionbankentryid $insql
              GROUP BY qr.questionbankentryid
                ", $params);

        $result = [];
        foreach ($counts as $count) {
            $result[(int)$count->questionbankentryid] = (int)$count->usages;
        }
        return $result;
    }

    /**
     * Fetch question category names in one query.
     *
     * @param array $categoryids
     * @return array Category id => name.
     */
    protected function get_category_names(array $categoryids): array {
        global $DB;

        if (empty($categoryids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        $records = $DB->get_records_select('question_categories', "id $insql", $params, '', 'id, name, contextid');

        $names = [];
        foreach ($records as $record) {
            $names[(int)$record->id] = (string)$record->name;
        }
        return $names;
    }

    /**
     * Human readable label for where a question comes from.
     *
     * On 4.x every bank is a category tree in the course context, so the category name is
     * the most useful thing to show. Overridden on 5.0+ where banks are real modules.
     *
     * @param int|null $contextid Context the category lives in.
     * @param string $categoryname Name of the question category.
     * @param context_module $quizcontext Context of the quiz being renumbered.
     * @return string
     */
    protected function describe_bank(?int $contextid, string $categoryname, context_module $quizcontext): string {
        if ($contextid !== null && (int)$contextid === (int)$quizcontext->id) {
            return get_string('bankquizonly', 'local_quizrenumber');
        }
        if ($categoryname === '' || $contextid === null) {
            return get_string('bankunknown', 'local_quizrenumber');
        }
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        return format_string($categoryname, true, ['context' => $context ?: $quizcontext]);
    }

    /**
     * Whether the current user may rename questions in a context.
     *
     * @param int|null $contextid
     * @return bool
     */
    protected function can_edit_in_context(?int $contextid): bool {
        if ($contextid === null) {
            return false;
        }
        if (!array_key_exists($contextid, $this->editablecache)) {
            $context = \context::instance_by_id($contextid, IGNORE_MISSING);
            $this->editablecache[$contextid] = $context && has_capability('moodle/question:editall', $context);
        }
        return $this->editablecache[$contextid];
    }

    /**
     * Course id that owns a quiz.
     *
     * @param int $quizid
     * @return int Zero if not found or not asked for.
     */
    protected function get_course_id_for_quiz(int $quizid): int {
        global $DB;
        if (!$quizid) {
            return 0;
        }
        return (int)$DB->get_field('quiz', 'course', ['id' => $quizid]);
    }
}
