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
 * Deliberately a plain value object with untyped public properties: the plugin supports
 * Moodle 4.0, which supports PHP 7.3, so typed properties and constructor promotion are
 * not available.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_slot {
    /** @var int Id of the quiz this slot belongs to. */
    public $quizid;

    /** @var string Name of the quiz, for display and grouping. */
    public $quizname;

    /** @var int Slot number within the quiz, 1-based. */
    public $slot;

    /** @var int Id of the {quiz_slots} row. */
    public $slotid;

    /** @var int|null Id of the {question} row for the version this slot resolves to. Null for random/missing slots. */
    public $questionid;

    /** @var int|null Id of the owning {question_bank_entries} row. Null for random/missing slots. */
    public $bankentryid;

    /** @var string Current question name, or a placeholder for random/missing slots. */
    public $name;

    /** @var int|null Context id of the category the question lives in. */
    public $bankcontextid;

    /** @var string Human readable label for where the question comes from, e.g. a qbank instance name. */
    public $bankname;

    /** @var bool True if this slot draws a random question from a category rather than naming one question. */
    public $israndom;

    /** @var bool True if the question this slot points at could not be loaded. */
    public $ismissing;

    /** @var int Number of quiz slots across the site that use this question's bank entry. */
    public $usagecount;

    /** @var bool True if the current user may rename this question in its own context. */
    public $editable;

    /**
     * Build a slot.
     *
     * @param int $quizid
     * @param string $quizname
     * @param int $slot
     * @param int $slotid
     */
    public function __construct(int $quizid, string $quizname, int $slot, int $slotid) {
        $this->quizid = $quizid;
        $this->quizname = $quizname;
        $this->slot = $slot;
        $this->slotid = $slotid;
        $this->questionid = null;
        $this->bankentryid = null;
        $this->name = '';
        $this->bankcontextid = null;
        $this->bankname = '';
        $this->israndom = false;
        $this->ismissing = false;
        $this->usagecount = 1;
        $this->editable = true;
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
