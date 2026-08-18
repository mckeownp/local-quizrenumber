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

namespace local_quizrenumber\tests\fixtures;

use local_quizrenumber\compat\question_source_v4;

/**
 * A question source that overrides how slots are classified.
 *
 * Stands in for a future question_source_vN that needs to classify slots differently,
 * which is the entire reason the compatibility layer is built as a class hierarchy. Used to
 * prove that get_quiz_questions() dispatches to the subclass rather than binding to the
 * class it happens to be written in.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overriding_question_source extends question_source_v4 {
    /**
     * Declare every slot random, however core has marked it.
     *
     * @param \stdClass $slotdata
     * @return bool
     */
    protected static function slot_is_random(\stdClass $slotdata): bool {
        return true;
    }
}
