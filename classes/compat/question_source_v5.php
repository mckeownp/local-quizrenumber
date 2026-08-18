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

/**
 * Question source for the Moodle 5.0+ question bank era.
 *
 * The slot -> question_references -> question_bank_entries -> question_versions -> question
 * chain is unchanged from 4.x, so the resolution and renaming inherited from
 * question_source_v4 still holds. What changed is where banks live: they are now mod_qbank
 * course modules, a course can have several, and a quiz can pull from banks in other
 * courses entirely. That affects how a bank is labelled and whether the site is even ready
 * to be read, which is all this subclass overrides.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_source_v5 extends question_source_v4 {
    /** @var array Cache of context id => bank label, per request. */
    protected array $banklabelcache = [];

    #[\Override]
    public function check_ready(int $courseid): void {
        global $DB;

        if (!$this->qbank_module_installed()) {
            // A 5.0+ site should always have mod_qbank. If it does not, something is wrong
            // enough that guessing would be worse than stopping.
            throw new \moodle_exception('errorqbankmissing', 'local_quizrenumber');
        }

        if ($this->course_has_qbank_instance($courseid) || $this->course_has_legacy_categories($courseid)) {
            return;
        }

        // No bank and no leftover categories. That is either an empty course, which the UI
        // handles perfectly well, or a site whose question bank migration has not finished.
        // Only the second case is worth interrupting the user for.
        if ($this->bank_migration_pending()) {
            throw new \moodle_exception('errorbankupgradepending', 'local_quizrenumber');
        }
    }

    /**
     * Label a bank by its mod_qbank instance name rather than its category name.
     *
     * @param int|null $contextid Context the category lives in.
     * @param string $categoryname Name of the question category.
     * @param context_module $quizcontext Context of the quiz being renumbered.
     * @return string
     */
    #[\Override]
    protected function describe_bank(?int $contextid, string $categoryname, context_module $quizcontext): string {
        if ($contextid === null) {
            return get_string('bankunknown', 'local_quizrenumber');
        }

        // Questions authored inline in a quiz never reach a qbank instance; their category
        // sits in the quiz's own context. That is a normal case, not an error.
        if ((int)$contextid === (int)$quizcontext->id) {
            return get_string('bankquizonly', 'local_quizrenumber');
        }

        if (!array_key_exists($contextid, $this->banklabelcache)) {
            $this->banklabelcache[$contextid] = $this->resolve_qbank_name($contextid);
        }

        $bankname = $this->banklabelcache[$contextid];
        if ($bankname === null) {
            // Not a qbank module context, so fall back to the 4.x style category label.
            return parent::describe_bank($contextid, $categoryname, $quizcontext);
        }

        if ($categoryname === '') {
            return $bankname;
        }
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        return get_string(
            'bankandcategory',
            'local_quizrenumber',
            [
                'bank' => $bankname,
                'category' => format_string($categoryname, true, ['context' => $context ?: $quizcontext]),
            ]
        );
    }

    /**
     * Name of the mod_qbank instance owning a context, if that is what the context is.
     *
     * @param int $contextid
     * @return string|null Null if the context is not a qbank module context.
     */
    protected function resolve_qbank_name(int $contextid): ?string {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_MODULE) {
            return null;
        }

        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm || $cm->modname !== 'qbank') {
            return null;
        }

        return format_string($cm->name, true, ['context' => $context]);
    }

    /**
     * Whether mod_qbank is installed on this site.
     *
     * $CFG->branch says 5.0+, but a site part-way through its upgrade can report that
     * before the module is usable, so check for the module itself as well.
     *
     * @return bool
     */
    protected function qbank_module_installed(): bool {
        global $DB;
        return $DB->record_exists('modules', ['name' => 'qbank']);
    }

    /**
     * Whether a course contains at least one qbank instance.
     *
     * @param int $courseid
     * @return bool
     */
    protected function course_has_qbank_instance(int $courseid): bool {
        global $DB;
        return $DB->record_exists('qbank', ['course' => $courseid]);
    }

    /**
     * Whether a course still has question categories hanging off its course context.
     *
     * @param int $courseid
     * @return bool
     */
    protected function course_has_legacy_categories(int $courseid): bool {
        global $DB;
        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return false;
        }
        return $DB->record_exists('question_categories', ['contextid' => $coursecontext->id]);
    }

    /**
     * Whether this site still has queued mod_qbank migration tasks.
     *
     * @return bool
     */
    protected function bank_migration_pending(): bool {
        global $DB;
        $like = $DB->sql_like('classname', ':classname');
        return $DB->record_exists_select('task_adhoc', $like, ['classname' => '%mod_qbank%']);
    }
}
