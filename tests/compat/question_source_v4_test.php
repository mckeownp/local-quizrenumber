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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Tests for the Moodle 4.0 - 4.5 question source, run against the real question bank schema.
 *
 * The numbering rules are tested elsewhere against a stub. What matters here is only that
 * this implementation reads and writes the 4.x question bank correctly, which is the half
 * that cannot be proven without a database.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\compat\question_source_v4
 */
final class question_source_v4_test extends \advanced_testcase {
    /** @var \stdClass The test course. */
    protected $course;

    /** @var \stdClass The question category used for fixed questions. */
    protected $category;

    /** @var \core_question_generator The question generator. */
    protected $questiongenerator;

    /**
     * Set up a course with a question category in its context.
     */
    protected function setUp(): void {
        parent::setUp();

        global $CFG;
        if ((int)$CFG->branch >= 500) {
            $this->markTestSkipped('question_source_v4 targets the Moodle 4.x question bank; ' .
                'this site runs the 5.0+ model, which question_source_v5_test covers.');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->category = $this->questiongenerator->create_question_category([
            'contextid' => \context_course::instance($this->course->id)->id,
        ]);
    }

    /**
     * Create a quiz containing the named questions, in order.
     *
     * @param array $names Question names, in slot order.
     * @return \stdClass The quiz record.
     */
    protected function create_quiz_with_questions(array $names): \stdClass {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        foreach ($names as $name) {
            $question = $this->questiongenerator->create_question('truefalse', null, [
                'category' => $this->category->id,
                'name' => $name,
            ]);
            quiz_add_quiz_question($question->id, $quiz);
        }

        return $quiz;
    }

    /**
     * Add one random question slot to a quiz.
     *
     * Moodle 4.4 moved this from the quiz_add_random_questions() function to a method on
     * structure, and 4.5 emits a deprecation notice for the old one. The plugin itself never
     * adds questions, so this version dance is confined to the test helper.
     *
     * @param \stdClass $quiz
     * @param int $categoryid Category to draw from.
     * @return void
     */
    protected function add_random_question(\stdClass $quiz, int $categoryid): void {
        $structure = \mod_quiz\quiz_settings::create($quiz->id)->get_structure();

        if (method_exists($structure, 'add_random_questions')) {
            $structure->add_random_questions(0, 1, [
                'filter' => [
                    'category' => [
                        'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
                        'values' => [$categoryid],
                        'filteroptions' => ['includesubcategories' => false],
                    ],
                ],
            ]);
            return;
        }

        quiz_add_random_questions($quiz, 0, $categoryid, 1);
    }

    /**
     * Slots come back in slot order with the right names.
     */
    public function test_get_quiz_questions_returns_slots_in_order(): void {
        $quiz = $this->create_quiz_with_questions(['alpha', 'beta', 'gamma']);

        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertCount(3, $slots);

        $names = [];
        $slotnumbers = [];
        foreach ($slots as $slot) {
            $names[] = $slot->name;
            $slotnumbers[] = $slot->slot;
        }

        $this->assertSame(['alpha', 'beta', 'gamma'], $names);
        $this->assertSame([1, 2, 3], $slotnumbers);
        $this->assertSame((int)$quiz->id, $slots[1]->quizid);
        $this->assertTrue($slots[1]->is_renameable());
        $this->assertNotEmpty($slots[1]->questionid);
        $this->assertNotEmpty($slots[1]->bankentryid);
    }

    /**
     * A random slot is flagged, keeps its slot position, and offers no question to rename.
     */
    public function test_get_quiz_questions_flags_random_slots(): void {
        $quiz = $this->create_quiz_with_questions(['alpha']);

        // A random slot draws from a category, so it needs a category with something in it.
        $randomcategory = $this->questiongenerator->create_question_category([
            'contextid' => \context_course::instance($this->course->id)->id,
        ]);
        $this->questiongenerator->create_question('truefalse', null, [
            'category' => $randomcategory->id,
            'name' => 'pool question',
        ]);
        $this->add_random_question($quiz, (int)$randomcategory->id);

        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertCount(2, $slots);
        $this->assertFalse($slots[1]->israndom);
        $this->assertTrue($slots[1]->is_renameable());

        $this->assertTrue($slots[2]->israndom);
        $this->assertFalse($slots[2]->is_renameable());
        $this->assertNull($slots[2]->questionid);
    }

    /**
     * The checklist counts come back for several quizzes in one call.
     */
    public function test_get_quiz_summaries_counts_fixed_and_random(): void {
        $quizone = $this->create_quiz_with_questions(['alpha', 'beta']);
        $quiztwo = $this->create_quiz_with_questions(['gamma']);
        $empty = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        $randomcategory = $this->questiongenerator->create_question_category([
            'contextid' => \context_course::instance($this->course->id)->id,
        ]);
        $this->questiongenerator->create_question('truefalse', null, [
            'category' => $randomcategory->id,
            'name' => 'pool question',
        ]);
        $this->add_random_question($quiztwo, (int)$randomcategory->id);

        $source = new question_source_v4();
        $summaries = $source->get_quiz_summaries([(int)$quizone->id, (int)$quiztwo->id, (int)$empty->id]);

        $this->assertSame(['fixed' => 2, 'random' => 0, 'total' => 2], $summaries[(int)$quizone->id]);
        $this->assertSame(['fixed' => 1, 'random' => 1, 'total' => 2], $summaries[(int)$quiztwo->id]);
        // A quiz with no slots contributes no row, which callers treat as zeroes.
        $this->assertArrayNotHasKey((int)$empty->id, $summaries);
    }

    /**
     * The course listing survives a quiz with no questions and reports it honestly.
     *
     * @covers \local_quizrenumber\quiz_finder
     */
    public function test_quiz_finder_lists_course_quizzes(): void {
        $this->create_quiz_with_questions(['alpha', 'beta']);
        $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        $quizzes = \local_quizrenumber\quiz_finder::get_course_quizzes(
            (int)$this->course->id,
            new question_source_v4()
        );

        $this->assertCount(2, $quizzes);
        $counts = [];
        foreach ($quizzes as $quiz) {
            $counts[] = $quiz['fixedcount'];
        }
        sort($counts);
        $this->assertSame([0, 2], $counts);
    }

    /**
     * Renaming writes to the question the slot actually resolves to.
     */
    public function test_rename_question_persists(): void {
        global $DB;

        $quiz = $this->create_quiz_with_questions(['alpha']);
        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quiz->id);
        $questionid = $slots[1]->questionid;

        $source->rename_question($questionid, '0010_alpha');

        $this->assertSame('0010_alpha', $DB->get_field('question', 'name', ['id' => $questionid]));

        // And the change is visible through the same read path the preview uses.
        $reread = $source->get_quiz_questions((int)$quiz->id);
        $this->assertSame('0010_alpha', $reread[1]->name);
    }

    /**
     * Renaming updates the current version rather than creating a new one.
     */
    public function test_rename_question_does_not_create_a_new_version(): void {
        global $DB;

        $quiz = $this->create_quiz_with_questions(['alpha']);
        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $entryid = $slots[1]->bankentryid;
        $versionsbefore = $DB->count_records('question_versions', ['questionbankentryid' => $entryid]);

        $source->rename_question($slots[1]->questionid, '0010_alpha');

        $this->assertSame(
            $versionsbefore,
            $DB->count_records('question_versions', ['questionbankentryid' => $entryid])
        );
    }

    /**
     * A question used by one quiz counts once.
     */
    public function test_get_usage_count_for_an_unshared_question(): void {
        $quiz = $this->create_quiz_with_questions(['alpha']);
        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertSame(1, $source->get_usage_count($slots[1]->questionid));
        $this->assertFalse($slots[1]->is_shared());
    }

    /**
     * A question added to two quizzes counts twice, and the slots say so.
     */
    public function test_get_usage_count_for_a_shared_question(): void {
        $quizone = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $quiztwo = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        $question = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->category->id,
            'name' => 'shared question',
        ]);
        quiz_add_quiz_question($question->id, $quizone);
        quiz_add_quiz_question($question->id, $quiztwo);

        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quizone->id);

        $this->assertSame(2, $slots[1]->usagecount);
        $this->assertTrue($slots[1]->is_shared());
        $this->assertSame(2, $source->get_usage_count($slots[1]->questionid));

        // The tooltip should name the other quiz, not the one being worked on.
        $details = $source->get_usage_details($slots[1]->questionid, (int)$quizone->id);
        $this->assertCount(1, $details);
        $this->assertSame($quiztwo->name, $details[0]['quizname']);
        $this->assertTrue($details[0]['samecourse']);
    }

    /**
     * Sharing across courses is reported as such, since the user may not have rights there.
     */
    public function test_get_usage_details_flags_other_courses(): void {
        $othercourse = $this->getDataGenerator()->create_course();

        $quizhere = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $quizthere = $this->getDataGenerator()->create_module('quiz', ['course' => $othercourse->id]);

        $question = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $this->category->id,
            'name' => 'widely shared',
        ]);
        quiz_add_quiz_question($question->id, $quizhere);
        quiz_add_quiz_question($question->id, $quizthere);

        $source = new question_source_v4();
        $slots = $source->get_quiz_questions((int)$quizhere->id);

        $details = $source->get_usage_details($slots[1]->questionid, (int)$quizhere->id);
        $this->assertCount(1, $details);
        $this->assertFalse($details[0]['samecourse']);
        $this->assertSame($othercourse->fullname, $details[0]['coursename']);
    }

    /**
     * A 4.x site is always ready; there is no bank migration to wait for.
     */
    public function test_check_ready_passes_on_4x(): void {
        $source = new question_source_v4();
        $source->check_ready((int)$this->course->id);
        $this->assertTrue(true);
    }

    /**
     * The factory picks this implementation on a 4.x site.
     *
     * @covers \local_quizrenumber\compat\question_source_factory
     */
    public function test_factory_selects_the_v4_implementation(): void {
        $this->assertInstanceOf(question_source_v4::class, question_source_factory::create());
    }
}
