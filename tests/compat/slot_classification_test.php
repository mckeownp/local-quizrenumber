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
 * Tests that slots are classified correctly whichever way core marks them.
 *
 * These run against hand-built slot records rather than a live quiz, precisely so every
 * supported Moodle version's marker shape is exercised on every version. The DB-backed tests
 * can only ever see the shape of the Moodle they happen to run on, which is how a change in
 * Moodle 5.2 went unnoticed until CI reached that branch:
 *
 *   - 4.5 to 5.1 marked a random slot with qtype = 'random'.
 *   - 5.2 sets qtype = null and adds an explicit `random` boolean.
 *
 * Matching on qtype alone made 5.2 treat random slots as ordinary questions with an
 * unusable id, which surfaced as "Question missing" in the preview.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\compat\question_source_v4
 */
final class slot_classification_test extends \advanced_testcase {
    /**
     * Call one of the protected classification helpers.
     *
     * @param string $method
     * @param array $slotfields
     * @return bool
     */
    protected function classify(string $method, array $slotfields): bool {
        $reflection = new \ReflectionMethod(question_source_v4::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, (object)$slotfields);
    }

    /**
     * Random slots are recognised on every supported marker shape.
     *
     * @dataProvider random_slot_provider
     * @param array $slotfields
     * @param bool $expected
     * @param string $because
     */
    public function test_slot_is_random(array $slotfields, bool $expected, string $because): void {
        $this->assertSame($expected, $this->classify('slot_is_random', $slotfields), $because);
    }

    /**
     * Slot shapes as the supported Moodle versions actually produce them.
     *
     * @return array
     */
    public static function random_slot_provider(): array {
        return [
            'Moodle 4.5 to 5.1 random slot' => [
                ['qtype' => 'random', 'questionid' => 's42', 'questionbankentryid' => null],
                true,
                'Older versions mark a random slot only by setting qtype to the string random.',
            ],
            'Moodle 5.2 random slot' => [
                ['qtype' => null, 'random' => true, 'questionid' => 's42', 'questionbankentryid' => null],
                true,
                'Moodle 5.2 sets qtype to null and flags the slot with an explicit random boolean.',
            ],
            'Moodle 4.5 to 5.1 fixed slot' => [
                ['qtype' => 'truefalse', 'questionid' => 123, 'questionbankentryid' => 45],
                false,
                'A real question must never be treated as random.',
            ],
            'Moodle 5.2 fixed slot' => [
                ['qtype' => 'truefalse', 'random' => false, 'questionid' => 123, 'questionbankentryid' => 45],
                false,
                'Moodle 5.2 sets random to false on every slot, so a false flag must not read as random.',
            ],
            'missing question' => [
                ['qtype' => 'missingtype', 'questionid' => 's42', 'questionbankentryid' => null],
                false,
                'A missing question is skipped for a different reason and must not be called random.',
            ],
        ];
    }

    /**
     * Only slots backed by a real question row are renameable.
     *
     * @dataProvider has_question_provider
     * @param array $slotfields
     * @param bool $expected
     * @param string $because
     */
    public function test_slot_has_question(array $slotfields, bool $expected, string $because): void {
        $this->assertSame($expected, $this->classify('slot_has_question', $slotfields), $because);
    }

    /**
     * Slot shapes for the renameability check.
     *
     * @return array
     */
    public static function has_question_provider(): array {
        return [
            'fixed question' => [
                ['qtype' => 'truefalse', 'questionid' => 123, 'questionbankentryid' => 45],
                true,
                'A slot with a numeric question id and a bank entry is renameable.',
            ],
            'random slot placeholder id' => [
                ['qtype' => null, 'random' => true, 'questionid' => 's42', 'questionbankentryid' => null],
                false,
                'Core uses placeholder ids like s42; casting that to an int would give a bogus zero.',
            ],
            'missing question row' => [
                ['qtype' => 'missingtype', 'questionid' => null, 'questionbankentryid' => 45],
                false,
                'The bank entry survives but the question row has gone, so there is nothing to rename.',
            ],
            'no bank entry' => [
                ['qtype' => 'truefalse', 'questionid' => 123, 'questionbankentryid' => null],
                false,
                'Without a bank entry the usage count cannot be resolved, so treat it as missing.',
            ],
            'zero question id' => [
                ['qtype' => 'truefalse', 'questionid' => 0, 'questionbankentryid' => 45],
                false,
                'A zero id is never a real question.',
            ],
        ];
    }
}
