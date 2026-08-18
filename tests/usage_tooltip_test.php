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
use local_quizrenumber\output\preview;
use local_quizrenumber\tests\fixtures\stub_question_source;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/stub_question_source.php');

/**
 * Tests for the "used elsewhere" badge tooltip and its cap.
 *
 * The cap exists because of a real course where one exam question was reused in 83 quizzes,
 * producing a 2,771 character title attribute. Two-quiz fixtures never showed that, so these
 * tests deliberately build a question with far more places than the cap allows.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\output\preview
 */
final class usage_tooltip_test extends \advanced_testcase {
    /**
     * Build a shared slot plus a stub that reports it used in the given number of quizzes.
     *
     * @param int $places How many other quizzes use the question.
     * @return array [$slot, $source]
     */
    protected function make_shared_question(int $places): array {
        $slot = new question_slot(1, 'Final exam', 1, 1);
        $slot->name = 'shared question';
        $slot->questionid = 500;
        $slot->bankentryid = 500;
        $slot->bankcontextid = 1;
        $slot->usagecount = $places + 1;

        $source = new stub_question_source();
        $details = [];
        for ($i = 1; $i <= $places; $i++) {
            $details[] = [
                'coursename' => 'Course 1',
                'quizname' => 'Quiz ' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
                'samecourse' => true,
            ];
        }
        $source->usagedetails[500] = $details;

        return [$slot, $source];
    }

    /**
     * Render the preview and return the tooltip for the single row.
     *
     * @param question_slot $slot
     * @param stub_question_source $source
     * @return string
     */
    protected function render_tooltip(question_slot $slot, stub_question_source $source): string {
        global $PAGE;

        $plan = (new renumber_service())->build_plan([$slot], new renumber_settings(10, 10, 4));
        $renderable = new preview($plan, new renumber_settings(10, 10, 4), $source, false, 2, [1]);
        $context = $renderable->export_for_template($PAGE->get_renderer('core'));

        return $context['quizzes'][0]['rows'][0]['usagetooltip'];
    }

    /**
     * A handful of places are all named outright, with no summary tail.
     */
    public function test_short_usage_lists_are_named_in_full(): void {
        $this->resetAfterTest();

        [$slot, $source] = $this->make_shared_question(3);
        $tooltip = $this->render_tooltip($slot, $source);

        $this->assertStringContainsString('Quiz 01', $tooltip);
        $this->assertStringContainsString('Quiz 02', $tooltip);
        $this->assertStringContainsString('Quiz 03', $tooltip);
        $this->assertStringNotContainsString('others', $tooltip);
    }

    /**
     * Exactly at the cap, everything is still named and nothing is summarised.
     */
    public function test_usage_list_at_the_cap_is_named_in_full(): void {
        $this->resetAfterTest();

        [$slot, $source] = $this->make_shared_question(preview::TOOLTIP_MAX_PLACES);
        $tooltip = $this->render_tooltip($slot, $source);

        $this->assertStringNotContainsString('others', $tooltip);
        $this->assertSame(preview::TOOLTIP_MAX_PLACES, substr_count($tooltip, 'Quiz '));
    }

    /**
     * Past the cap, the tooltip names the first few and counts the rest.
     */
    public function test_long_usage_lists_are_capped_and_summarised(): void {
        $this->resetAfterTest();

        [$slot, $source] = $this->make_shared_question(82);
        $tooltip = $this->render_tooltip($slot, $source);

        // Only the capped number of quizzes is named.
        $this->assertSame(preview::TOOLTIP_MAX_PLACES, substr_count($tooltip, 'Quiz '));
        $this->assertStringContainsString('Quiz 01', $tooltip);
        $this->assertStringNotContainsString('Quiz 82', $tooltip);

        // The remainder is accounted for rather than silently dropped.
        $remaining = 82 - preview::TOOLTIP_MAX_PLACES;
        $this->assertStringContainsString((string)$remaining, $tooltip);
        $this->assertStringContainsString('others', $tooltip);
    }

    /**
     * The capped tooltip stays a sane length for a title attribute.
     *
     * The uncapped version of this exact case was 2,771 characters.
     */
    public function test_capped_tooltip_is_short_enough_to_be_readable(): void {
        $this->resetAfterTest();

        [$slot, $source] = $this->make_shared_question(82);
        $tooltip = $this->render_tooltip($slot, $source);

        $this->assertLessThan(500, strlen($tooltip));
    }

    /**
     * The badge links through to the full list when the question is shared.
     */
    public function test_shared_questions_get_a_usage_link(): void {
        global $PAGE;
        $this->resetAfterTest();

        [$slot, $source] = $this->make_shared_question(20);
        $plan = (new renumber_service())->build_plan([$slot], new renumber_settings(10, 10, 4));
        $renderable = new preview($plan, new renumber_settings(10, 10, 4), $source, false, 2, [1]);
        $row = $renderable->export_for_template($PAGE->get_renderer('core'))['quizzes'][0]['rows'][0];

        $this->assertNotEmpty($row['usageurl']);
        $this->assertStringContainsString('usage.php', $row['usageurl']);
        $this->assertStringContainsString('questionid=500', $row['usageurl']);
        // The selection travels with the link so the page can offer a way back to this preview.
        $this->assertStringContainsString('quizids=1', $row['usageurl']);
    }

    /**
     * A question used only once gets no badge, no tooltip and no link.
     */
    public function test_unshared_questions_get_no_usage_link(): void {
        global $PAGE;
        $this->resetAfterTest();

        $slot = new question_slot(1, 'Quiz A', 1, 1);
        $slot->name = 'lonely question';
        $slot->questionid = 700;
        $slot->bankentryid = 700;
        $slot->usagecount = 1;

        $plan = (new renumber_service())->build_plan([$slot], new renumber_settings(10, 10, 4));
        $renderable = new preview(
            $plan,
            new renumber_settings(10, 10, 4),
            new stub_question_source(),
            false,
            2,
            [1]
        );
        $row = $renderable->export_for_template($PAGE->get_renderer('core'))['quizzes'][0]['rows'][0];

        $this->assertFalse($row['shared']);
        $this->assertSame('', $row['usagetooltip']);
        $this->assertSame('', $row['usageurl']);
    }

    /**
     * The source is asked for only as many places as the tooltip can show.
     *
     * Fetching 83 rows to render eight was the other half of the problem.
     *
     * @covers \local_quizrenumber\tests\fixtures\stub_question_source
     */
    public function test_only_the_capped_number_of_places_is_fetched(): void {
        $this->resetAfterTest();

        [, $source] = $this->make_shared_question(82);
        $details = $source->get_usage_details(500, 0, preview::TOOLTIP_MAX_PLACES);

        $this->assertCount(preview::TOOLTIP_MAX_PLACES, $details['places']);
        $this->assertSame(82, $details['total']);
    }
}
