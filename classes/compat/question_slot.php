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
 * One quiz slot, described in a way that is identical on every supported Moodle version.
 *
 * This is the only shape of question bank data the rest of the plugin ever sees. Every
 * question_source_* implementation is responsible for producing these, so that forms,
 * the service and the output layer never need a version check of their own.
 *
 * A plain mutable value object rather than a constructor-injected immutable one: the
 * question source discovers the fields in stages (identity first, then the resolved
 * question, then usage counts and capability checks in batches), so forcing everything
 * through the constructor would mean assembling parallel arrays just to satisfy it.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_slot {
    /** @var int|null Id of the {question} row for the version this slot resolves to. Null for random/missing slots. */
    public ?int $questionid = null;

    /** @var int|null Id of the owning {question_bank_entries} row. Null for random/missing slots. */
    public ?int $bankentryid = null;

    /** @var string Current question name, or a placeholder for random/missing slots. */
    public string $name = '';

    /** @var int|null Context id of the category the question lives in. */
    public ?int $bankcontextid = null;

    /** @var string Human readable label for where the question comes from, e.g. a qbank instance name. */
    public string $bankname = '';

    /** @var bool True if this slot draws a random question from a category rather than naming one question. */
    public bool $israndom = false;

    /** @var bool True if the question this slot points at could not be loaded. */
    public bool $ismissing = false;

    /** @var int Number of quiz slots across the site that use this question's bank entry. */
    public int $usagecount = 1;

    /** @var bool True if the current user may rename this question in its own context. */
    public bool $editable = true;

    /**
     * Build a slot.
     *
     * The four identifying values are readonly: which quiz and which slot this describes is
     * settled at construction. Everything else is filled in by the question source as it
     * resolves the slot, so those stay writable.
     *
     * @param int $quizid Id of the quiz this slot belongs to.
     * @param string $quizname Name of the quiz, for display and grouping.
     * @param int $slot Slot number within the quiz, 1-based.
     * @param int $slotid Id of the {quiz_slots} row.
     */
    public function __construct(
        /** @var int Id of the quiz this slot belongs to */
        public readonly int $quizid,
        /** @var string Name of the quiz, for display and grouping */
        public readonly string $quizname,
        /** @var int Slot number within the quiz, 1-based */
        public readonly int $slot,
        /** @var int Id of the {quiz_slots} row */
        public readonly int $slotid,
    ) {
    }

    /**
     * Whether this slot is one the tool can actually rename.
     *
     * Random slots resolve to a category rather than a single question, and missing
     * questions have no row to rename. Both are shown in the preview but never touched.
     *
     * @return bool
     */
    public function is_renameable(): bool {
        return !$this->israndom && !$this->ismissing && !empty($this->questionid);
    }

    /**
     * Whether this question is used by more than one quiz slot anywhere on the site.
     *
     * @return bool
     */
    public function is_shared(): bool {
        return $this->usagecount > 1;
    }
}
