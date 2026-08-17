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

use local_quizrenumber\compat\question_slot;

/**
 * One row of a renumbering plan: what the tool intends to do with a single slot.
 *
 * Rows exist for slots that will be skipped too, so the preview and the results page can
 * both account for every slot in the quiz rather than quietly dropping the ones the tool
 * does not touch.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renumber_row {
    /** @var string Slot draws a random question from a category, so there is nothing to rename. */
    const SKIP_RANDOM = 'random';

    /** @var string The question this slot points at could not be loaded. */
    const SKIP_MISSING = 'missing';

    /** @var string The user lacks moodle/question:editall in the context owning the question. */
    const SKIP_NOPERMISSION = 'nopermission';

    /** @var string The new name is identical to the current one. */
    const SKIP_UNCHANGED = 'unchanged';

    /** @var question_slot The slot this row describes. */
    public $slot;

    /** @var string Name before any change. */
    public $currentname;

    /** @var string Name after prefix stripping, before the new prefix is applied. */
    public $strippedname;

    /** @var string Name the tool will write, or an empty string for skipped rows. */
    public $newname;

    /** @var int|null Number allocated to this row, or null if it was not given one. */
    public $number;

    /** @var string|null One of the SKIP_* constants, or null if this row will be applied. */
    public $skipreason;

    /**
     * Build a row.
     *
     * @param question_slot $slot
     */
    public function __construct(question_slot $slot) {
        $this->slot = $slot;
        $this->currentname = $slot->name;
        $this->strippedname = $slot->name;
        $this->newname = '';
        $this->number = null;
        $this->skipreason = null;
    }

    /**
     * Whether this row will actually be written.
     *
     * @return bool
     */
    public function will_apply(): bool {
        return $this->skipreason === null && $this->newname !== '';
    }

    /**
     * Translated explanation of why this row is being skipped.
     *
     * @return string Empty string if the row is not skipped.
     */
    public function get_skip_label(): string {
        if ($this->skipreason === null) {
            return '';
        }
        return get_string('skip' . $this->skipreason, 'local_quizrenumber');
    }
}
