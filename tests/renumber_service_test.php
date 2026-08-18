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
use local_quizrenumber\tests\fixtures\stub_question_source;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/stub_question_source.php');

/**
 * Tests for the version-blind numbering rules.
 *
 * These never touch a question bank: slots are built by hand and renames go to a stub, so
 * the same assertions hold on every supported Moodle version.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\renumber_service
 */
final class renumber_service_test extends \advanced_testcase {
    /**
     * Build a fixed-question slot.
     *
     * @param int $quizid
     * @param int $slotnumber
     * @param string $name
     * @param int $questionid
     * @return question_slot
     */
    protected function make_slot(int $quizid, int $slotnumber, string $name, int $questionid = 0): question_slot {
        $slot = new question_slot($quizid, 'Quiz ' . $quizid, $slotnumber, $slotnumber);
        $slot->name = $name;
        $slot->questionid = $questionid ?: ($quizid * 100 + $slotnumber);
        $slot->bankentryid = $slot->questionid;
        $slot->bankcontextid = 1;
        return $slot;
    }

    /**
     * Build a random slot.
     *
     * @param int $quizid
     * @param int $slotnumber
     * @return question_slot
     */
    protected function make_random_slot(int $quizid, int $slotnumber): question_slot {
        $slot = new question_slot($quizid, 'Quiz ' . $quizid, $slotnumber, $slotnumber);
        $slot->name = 'Random';
        $slot->israndom = true;
        $slot->bankcontextid = 1;
        return $slot;
    }

    /**
     * Collect the new names from a plan, for compact assertions.
     *
     * @param renumber_plan $plan
     * @return string[]
     */
    protected function new_names(renumber_plan $plan): array {
        $names = [];
        foreach ($plan->get_rows() as $row) {
            $names[] = $row->newname;
        }
        return $names;
    }

    /**
     * Names with no existing prefix get one.
     */
    public function test_build_plan_fresh_names(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_slot(1, 2, 'beta'),
            $this->make_slot(1, 3, 'gamma'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4));

        $this->assertSame(['0010_alpha', '0020_beta', '0030_gamma'], $this->new_names($plan));
        $this->assertCount(3, $plan->get_applicable_rows());
    }

    /**
     * A prefix this plugin wrote is removed rather than stacked on.
     */
    public function test_build_plan_strips_existing_prefix(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, '0010_alpha'),
            $this->make_slot(1, 2, '0020_beta'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(100, 100, 4));

        $this->assertSame(['0100_alpha', '0200_beta'], $this->new_names($plan));
    }

    /**
     * Stripping can be turned off for titles where the leading number is real.
     */
    public function test_build_plan_can_keep_existing_prefix(): void {
        $service = new renumber_service();
        $slots = [$this->make_slot(1, 1, '0010_alpha')];

        $plan = $service->build_plan($slots, new renumber_settings(20, 10, 4, renumber_settings::SCOPE_PER_QUIZ, false));

        $this->assertSame(['0020_0010_alpha'], $this->new_names($plan));
    }

    /**
     * Short leading numbers are not a prefix this plugin wrote, so they survive.
     *
     * @dataProvider strip_prefix_provider
     * @param string $input
     * @param string $expected
     */
    public function test_strip_prefix(string $input, string $expected): void {
        $this->assertSame($expected, renumber_service::strip_prefix($input));
    }

    /**
     * Cases for test_strip_prefix.
     *
     * @return array
     */
    public static function strip_prefix_provider(): array {
        return [
            'four digit prefix' => ['0010_alpha', 'alpha'],
            'three digit prefix' => ['010_alpha', 'alpha'],
            'six digit prefix' => ['000010_alpha', 'alpha'],
            'seven digits is not a prefix' => ['0000010_alpha', '0000010_alpha'],
            'two digits is not a prefix' => ['10_alpha', '10_alpha'],
            'year like name kept' => ['1999 exam question', '1999 exam question'],
            'only one prefix removed per run' => ['0010_0020_alpha', '0020_alpha'],
            'repeated underscores consumed' => ['0010__alpha', 'alpha'],
            'no prefix at all' => ['alpha_beta', 'alpha_beta'],
            'underscores elsewhere untouched' => ['0010_alpha_beta_0020', 'alpha_beta_0020'],
            'multibyte name preserved' => ['0010_Frage_über_Bäume', 'Frage_über_Bäume'],
            'multibyte name without prefix' => ['Frage über Bäume', 'Frage über Bäume'],
        ];
    }

    /**
     * Each quiz starts again at the start number by default.
     */
    public function test_build_plan_restarts_per_quiz(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_slot(1, 2, 'beta'),
            $this->make_slot(2, 1, 'gamma'),
            $this->make_slot(2, 2, 'delta'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4, renumber_settings::SCOPE_PER_QUIZ));

        $this->assertSame(['0010_alpha', '0020_beta', '0010_gamma', '0020_delta'], $this->new_names($plan));
    }

    /**
     * Continuous scope runs one sequence across every quiz.
     */
    public function test_build_plan_continuous_across_quizzes(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_slot(1, 2, 'beta'),
            $this->make_slot(2, 1, 'gamma'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4, renumber_settings::SCOPE_CONTINUOUS));

        $this->assertSame(['0010_alpha', '0020_beta', '0030_gamma'], $this->new_names($plan));
    }

    /**
     * By default a random slot is skipped and does not consume a number.
     */
    public function test_build_plan_random_slots_do_not_consume_a_number(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_random_slot(1, 2),
            $this->make_slot(1, 3, 'gamma'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4));
        $rows = $plan->get_rows();

        $this->assertSame('0010_alpha', $rows[0]->newname);
        $this->assertSame(renumber_row::SKIP_RANDOM, $rows[1]->skipreason);
        $this->assertSame('', $rows[1]->newname);
        $this->assertNull($rows[1]->number);
        // The number after a random slot is the next one in sequence, not one that leaves a gap.
        $this->assertSame('0020_gamma', $rows[2]->newname);
        $this->assertCount(2, $plan->get_applicable_rows());
    }

    /**
     * With the toggle on, a random slot reserves its number so the numbering tracks slot order.
     */
    public function test_build_plan_random_slots_can_reserve_a_number(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_random_slot(1, 2),
            $this->make_slot(1, 3, 'gamma'),
        ];

        $settings = new renumber_settings(10, 10, 4, renumber_settings::SCOPE_PER_QUIZ, true, true);
        $plan = $service->build_plan($slots, $settings);
        $rows = $plan->get_rows();

        $this->assertSame('0010_alpha', $rows[0]->newname);
        $this->assertSame(20, $rows[1]->number);
        $this->assertSame('', $rows[1]->newname);
        $this->assertSame(renumber_row::SKIP_RANDOM, $rows[1]->skipreason);
        $this->assertSame('0030_gamma', $rows[2]->newname);
    }

    /**
     * Running past the padding width lengthens the number and warns rather than truncating it.
     */
    public function test_build_plan_padding_overflow_warns(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_slot(1, 2, 'beta'),
            $this->make_slot(1, 3, 'gamma'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(9990, 10, 4));

        $this->assertSame(['9990_alpha', '10000_beta', '10010_gamma'], $this->new_names($plan));
        $warnings = $plan->get_warnings();
        $this->assertSame('warningpaddingoverflow', $warnings[0]['key']);
    }

    /**
     * Names are capped at the length the question table allows.
     */
    public function test_build_plan_truncates_overlong_names(): void {
        $service = new renumber_service();
        $longname = str_repeat('a', 260);
        $slots = [$this->make_slot(1, 1, $longname)];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4));
        $rows = $plan->get_rows();

        $this->assertSame(renumber_service::NAME_MAX_LENGTH, \core_text::strlen($rows[0]->newname));
        $this->assertStringStartsWith('0010_', $rows[0]->newname);
        $warnings = $plan->get_warnings();
        $this->assertSame('warningnametruncated', $warnings[0]['key']);
    }

    /**
     * A question already named the way the tool would name it is left alone.
     */
    public function test_build_plan_marks_unchanged_rows(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, '0010_alpha'),
            $this->make_slot(1, 2, 'beta'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4));
        $rows = $plan->get_rows();

        $this->assertSame(renumber_row::SKIP_UNCHANGED, $rows[0]->skipreason);
        // The unchanged row still consumes its number, so the next question is not pulled back.
        $this->assertSame('0020_beta', $rows[1]->newname);
        $this->assertCount(1, $plan->get_applicable_rows());
    }

    /**
     * Questions the user cannot edit are reported as skipped rather than silently dropped.
     */
    public function test_build_plan_skips_questions_without_permission(): void {
        $service = new renumber_service();
        $editable = $this->make_slot(1, 1, 'alpha');
        $locked = $this->make_slot(1, 2, 'beta');
        $locked->editable = false;

        $plan = $service->build_plan([$editable, $locked], new renumber_settings(10, 10, 4));
        $rows = $plan->get_rows();

        $this->assertSame(renumber_row::SKIP_NOPERMISSION, $rows[1]->skipreason);
        $this->assertCount(1, $plan->get_applicable_rows());
        $this->assertCount(1, $plan->get_skipped_rows());
        // A locked question does not consume a number either.
        $this->assertSame('0010_alpha', $rows[0]->newname);
    }

    /**
     * Duplicate names within a quiz are flagged, since they usually mean copy-paste.
     */
    public function test_build_plan_warns_about_duplicate_names(): void {
        $service = new renumber_service();
        $slots = [
            $this->make_slot(1, 1, 'alpha'),
            $this->make_slot(1, 2, 'alpha'),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4));

        $keys = array_column($plan->get_warnings(), 'key');
        $this->assertContains('warningduplicatenames', $keys);
    }

    /**
     * A shared question makes the confirmation checkbox mandatory.
     */
    public function test_plan_detects_shared_questions(): void {
        $service = new renumber_service();
        $shared = $this->make_slot(1, 1, 'alpha');
        $shared->usagecount = 3;

        $plan = $service->build_plan([$shared], new renumber_settings(10, 10, 4));
        $this->assertTrue($plan->has_shared_questions());

        $plan = $service->build_plan([$this->make_slot(1, 1, 'beta')], new renumber_settings(10, 10, 4));
        $this->assertFalse($plan->has_shared_questions());
    }

    /**
     * Applying a plan renames exactly the applicable rows and nothing else.
     */
    public function test_apply_writes_only_applicable_rows(): void {
        $this->resetAfterTest();

        $service = new renumber_service();
        $source = new stub_question_source();

        $slots = [
            $this->make_slot(1, 1, 'alpha', 501),
            $this->make_random_slot(1, 2),
            $this->make_slot(1, 3, 'gamma', 503),
        ];

        $plan = $service->build_plan($slots, new renumber_settings(10, 10, 4));
        $applied = $service->apply($plan, $source, \context_system::instance());

        $this->assertSame(2, $applied);
        $this->assertSame(['0010_alpha', '0020_gamma'], array_values($source->names));
        $this->assertArrayNotHasKey(502, $source->names);
    }

    /**
     * Applying fires one event carrying the full rename mapping, which is the audit trail.
     */
    public function test_apply_triggers_event(): void {
        $this->resetAfterTest();

        $service = new renumber_service();
        $source = new stub_question_source();
        $plan = $service->build_plan([$this->make_slot(1, 1, 'alpha', 601)], new renumber_settings(10, 10, 4));

        $sink = $this->redirectEvents();
        $service->apply($plan, $source, \context_system::instance());
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\local_quizrenumber\event\questions_renumbered::class, $event);
        $this->assertSame(1, $event->other['count']);
        $this->assertSame('alpha', $event->other['renames'][0]['oldname']);
        $this->assertSame('0010_alpha', $event->other['renames'][0]['newname']);
    }

    /**
     * Nothing to do means no event and no writes.
     */
    public function test_apply_does_nothing_for_an_empty_plan(): void {
        $this->resetAfterTest();

        $service = new renumber_service();
        $source = new stub_question_source();
        $plan = $service->build_plan([$this->make_random_slot(1, 1)], new renumber_settings(10, 10, 4));

        $sink = $this->redirectEvents();
        $applied = $service->apply($plan, $source, \context_system::instance());
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(0, $applied);
        $this->assertSame([], $source->names);
        $this->assertCount(0, $events);
    }

    /**
     * Out of range options are refused where the numbering happens, not only in the form.
     *
     * @covers \local_quizrenumber\renumber_settings
     */
    public function test_settings_reject_out_of_range_values(): void {
        $this->expectException(\invalid_parameter_exception::class);
        new renumber_settings(10, renumber_settings::MAX_INCREMENT + 1, 4);
    }

    /**
     * The documented maximum increment is itself accepted.
     *
     * @covers \local_quizrenumber\renumber_settings
     */
    public function test_settings_accept_the_maximum_increment(): void {
        $settings = new renumber_settings(10, renumber_settings::MAX_INCREMENT, 4);
        $this->assertSame(renumber_settings::MAX_INCREMENT, $settings->increment);
    }
}
