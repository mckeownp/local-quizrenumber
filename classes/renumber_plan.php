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

/**
 * The complete set of renames the tool intends to perform, plus anything worth warning about.
 *
 * A plan is computed twice: once to render the preview, and again from scratch on submit.
 * The second computation is what gets written, so a tampered or stale browser cannot talk
 * the server into applying names it did not derive itself.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renumber_plan {
    /** @var renumber_row[] Every slot considered, in quiz then slot order. */
    protected $rows = [];

    /** @var array Warnings as ['key' => string, 'a' => mixed]. */
    protected $warnings = [];

    /**
     * Add a row to the plan.
     *
     * @param renumber_row $row
     * @return void
     */
    public function add_row(renumber_row $row): void {
        $this->rows[] = $row;
    }

    /**
     * Record a warning to show above the preview.
     *
     * @param string $key Lang string identifier in local_quizrenumber.
     * @param mixed $a Optional lang string parameter.
     * @return void
     */
    public function add_warning(string $key, $a = null): void {
        $this->warnings[] = ['key' => $key, 'a' => $a];
    }

    /**
     * All rows, including skipped ones.
     *
     * @return renumber_row[]
     */
    public function get_rows(): array {
        return $this->rows;
    }

    /**
     * Only the rows that will be written.
     *
     * @return renumber_row[]
     */
    public function get_applicable_rows(): array {
        $applicable = [];
        foreach ($this->rows as $row) {
            if ($row->will_apply()) {
                $applicable[] = $row;
            }
        }
        return $applicable;
    }

    /**
     * Only the rows that will be left alone.
     *
     * @return renumber_row[]
     */
    public function get_skipped_rows(): array {
        $skipped = [];
        foreach ($this->rows as $row) {
            if (!$row->will_apply()) {
                $skipped[] = $row;
            }
        }
        return $skipped;
    }

    /**
     * Rows grouped by quiz, preserving quiz and slot order.
     *
     * @return array Quiz id => ['quizid' => int, 'quizname' => string, 'rows' => renumber_row[]].
     */
    public function get_rows_by_quiz(): array {
        $grouped = [];
        foreach ($this->rows as $row) {
            $quizid = $row->slot->quizid;
            if (!isset($grouped[$quizid])) {
                $grouped[$quizid] = [
                    'quizid' => $quizid,
                    'quizname' => $row->slot->quizname,
                    'rows' => [],
                ];
            }
            $grouped[$quizid]['rows'][] = $row;
        }
        return $grouped;
    }

    /**
     * Whether any question in this plan is used by more than one quiz slot.
     *
     * Drives the mandatory confirmation checkbox, since renaming a shared question changes
     * it for every quiz that uses it.
     *
     * @return bool
     */
    public function has_shared_questions(): bool {
        foreach ($this->get_applicable_rows() as $row) {
            if ($row->slot->is_shared()) {
                return true;
            }
        }
        return false;
    }

    /**
     * The warnings recorded while building this plan.
     *
     * @return array
     */
    public function get_warnings(): array {
        return $this->warnings;
    }

    /**
     * Translated warning messages, ready to display.
     *
     * @return string[]
     */
    public function get_warning_messages(): array {
        $messages = [];
        foreach ($this->warnings as $warning) {
            $messages[] = get_string($warning['key'], 'local_quizrenumber', $warning['a']);
        }
        return $messages;
    }

    /**
     * Whether there is anything at all to do.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->get_applicable_rows());
    }
}
