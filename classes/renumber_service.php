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

use core_text;
use local_quizrenumber\compat\question_slot;
use local_quizrenumber\compat\question_source_interface;
use local_quizrenumber\event\questions_renumbered;

/**
 * Turns a list of quiz slots plus a set of options into a plan, and applies it.
 *
 * Everything here is deliberately version-blind: it never touches $DB for question bank
 * data and never looks at $CFG->branch. Question bank access happens exclusively through
 * question_source_interface, which is what makes the numbering rules testable against a
 * stub rather than against a live Moodle of a particular vintage.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renumber_service {
    /** @var int The question table's name column is varchar(255). */
    const NAME_MAX_LENGTH = 255;

    /**
     * @var string Matches a prefix this plugin would itself have written.
     *
     * Three to six digits, so a legitimate title starting with a short number such as
     * "1_introduction" or a year is not eaten. Applied once, never recursively, so
     * "0010_0020_thing" loses one prefix per run rather than collapsing unexpectedly.
     */
    const PREFIX_REGEX = '/^\d{3,6}_+/u';

    /** @var string Separator between the number and the rest of the name. */
    const SEPARATOR = '_';

    /**
     * Remove a leading number prefix of the kind this plugin writes.
     *
     * @param string $name
     * @return string
     */
    public static function strip_prefix(string $name): string {
        $stripped = preg_replace(self::PREFIX_REGEX, '', $name, 1);
        // Null comes back on failure, for example malformed UTF-8. Leaving the name
        // untouched is the safe outcome there.
        return $stripped === null ? $name : $stripped;
    }

    /**
     * Format a number as a zero-padded prefix.
     *
     * Numbers wider than the padding are never truncated; they simply come out longer, and
     * build_plan raises a warning so the user knows the padding was outgrown.
     *
     * @param int $number
     * @param int $padding
     * @return string
     */
    public static function format_number(int $number, int $padding): string {
        return str_pad((string)$number, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Work out what renaming the given slots would do.
     *
     * @param question_slot[] $slots Slots in quiz then slot order. Grouping by quiz is derived
     *                               from the order they appear in, so callers should not interleave quizzes.
     * @param renumber_settings $settings
     * @return renumber_plan
     */
    public function build_plan(array $slots, renumber_settings $settings): renumber_plan {
        $plan = new renumber_plan();

        $counter = $settings->startnumber;
        $currentquizid = null;
        $overflowed = false;
        $truncated = 0;
        $seennames = [];
        $duplicates = [];

        foreach ($slots as $slot) {
            if ($currentquizid !== $slot->quizid) {
                if ($currentquizid !== null && $settings->scope === renumber_settings::SCOPE_PER_QUIZ) {
                    $counter = $settings->startnumber;
                }
                $currentquizid = $slot->quizid;
                $seennames = [];
            }

            $row = new renumber_row($slot);

            // Duplicate names within a quiz are legal and renumbering fixes them, but they are
            // worth pointing out since they often mean the quiz was built by copy-paste.
            if ($slot->is_renameable()) {
                $namekey = core_text::strtolower($slot->name);
                if (isset($seennames[$namekey])) {
                    $duplicates[$slot->name] = $slot->name;
                }
                $seennames[$namekey] = true;
            }

            if ($slot->israndom) {
                $row->skipreason = renumber_row::SKIP_RANDOM;
                // By default the sequence does not advance for a random slot, so the fixed
                // questions stay consecutively numbered. Reserving instead keeps the numbers
                // lined up with on-screen slot positions at the cost of gaps.
                if ($settings->reserverandom) {
                    $row->number = $counter;
                    $counter += $settings->increment;
                }
                $plan->add_row($row);
                continue;
            }

            if ($slot->ismissing || !$slot->is_renameable()) {
                $row->skipreason = renumber_row::SKIP_MISSING;
                $plan->add_row($row);
                continue;
            }

            if (!$slot->editable) {
                $row->skipreason = renumber_row::SKIP_NOPERMISSION;
                $plan->add_row($row);
                continue;
            }

            $row->strippedname = $settings->stripprefix ? self::strip_prefix($slot->name) : $slot->name;

            $prefix = self::format_number($counter, $settings->padding);
            if (core_text::strlen($prefix) > $settings->padding) {
                $overflowed = true;
            }

            $newname = $prefix . self::SEPARATOR . $row->strippedname;
            if (core_text::strlen($newname) > self::NAME_MAX_LENGTH) {
                $newname = core_text::substr($newname, 0, self::NAME_MAX_LENGTH);
                $truncated++;
            }

            $row->number = $counter;
            $row->newname = $newname;

            if ($newname === $slot->name) {
                $row->skipreason = renumber_row::SKIP_UNCHANGED;
            }

            $counter += $settings->increment;
            $plan->add_row($row);
        }

        if ($overflowed) {
            $plan->add_warning('warningpaddingoverflow', $settings->padding);
        }
        if ($truncated) {
            $plan->add_warning('warningnametruncated', $truncated);
        }
        if (!empty($duplicates)) {
            $plan->add_warning('warningduplicatenames', implode(', ', $duplicates));
        }

        return $plan;
    }

    /**
     * Write a plan to the question bank.
     *
     * The whole batch runs in one transaction: a quiz left half renumbered would be worse
     * than one not renumbered at all, because the user would have no way to tell how far it
     * got without reading every name.
     *
     * @param renumber_plan $plan
     * @param question_source_interface $source
     * @param \context $context Context to log the operation against, normally the course.
     * @return int Number of questions renamed.
     */
    public function apply(renumber_plan $plan, question_source_interface $source, \context $context): int {
        global $DB;

        $rows = $plan->get_applicable_rows();
        if (empty($rows)) {
            return 0;
        }

        $renames = [];
        $quizids = [];

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($rows as $row) {
                $source->rename_question($row->slot->questionid, $row->newname);
                $renames[] = [
                    'questionid' => $row->slot->questionid,
                    'oldname' => $row->currentname,
                    'newname' => $row->newname,
                ];
                $quizids[$row->slot->quizid] = $row->slot->quizid;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Rolling back rethrows, so nothing after this line runs.
            $transaction->rollback($e);
        }

        questions_renumbered::create([
            'context' => $context,
            'other' => [
                'count' => count($renames),
                'quizids' => array_values($quizids),
                'renames' => $renames,
            ],
        ])->trigger();

        return count($renames);
    }
}
