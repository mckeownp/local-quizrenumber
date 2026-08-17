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
 * Tests for the Moodle 5.0+ question source, run against the real mod_qbank schema.
 *
 * The parts that behave the same as 4.x are covered by question_source_v4_test. What this
 * class exists to prove is the bit that genuinely changed: questions now live in mod_qbank
 * module contexts rather than the course context, a course can hold several banks, and a
 * quiz can pull from a bank in another course entirely.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\compat\question_source_v5
 */
final class question_source_v5_test extends \advanced_testcase {
    /** @var \stdClass The test course. */
    protected $course;

    /** @var \core_question_generator The question generator. */
    protected $questiongenerator;

    /**
     * Skip unless this site actually runs the 5.0+ question bank model.
     */
    protected function setUp(): void {
        parent::setUp();

        global $CFG;
        if ((int)$CFG->branch < 500) {
            $this->markTestSkipped('question_source_v5 needs the Moodle 5.0+ question bank; ' .
                'this site runs the 4.x model, which question_source_v4_test covers.');
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
    }

    /**
     * Create a question bank module instance and a category inside it.
     *
     * @param string $bankname Name for the qbank instance.
     * @param \stdClass|null $course Course to create it in, defaulting to the test course.
     * @return array [$qbankcm, $category]
     */
    protected function create_bank(string $bankname, ?\stdClass $course = null): array {
        $course = $course ?? $this->course;

        $qbank = $this->getDataGenerator()->create_module('qbank', [
            'course' => $course->id,
            'name' => $bankname,
        ]);

        $category = $this->questiongenerator->create_question_category([
            'contextid' => \context_module::instance($qbank->cmid)->id,
        ]);

        return [$qbank, $category];
    }

    /**
     * Create a quiz holding the named questions, drawn from the given category.
     *
     * @param array $names Question names in slot order.
     * @param \stdClass $category Category to create the questions in.
     * @param \stdClass|null $course Course for the quiz, defaulting to the test course.
     * @return \stdClass The quiz record.
     */
    protected function create_quiz_with_questions(
        array $names,
        \stdClass $category,
        ?\stdClass $course = null
    ): \stdClass {
        $course = $course ?? $this->course;
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        foreach ($names as $name) {
            $question = $this->questiongenerator->create_question('truefalse', null, [
                'category' => $category->id,
                'name' => $name,
            ]);
            quiz_add_quiz_question($question->id, $quiz);
        }

        return $quiz;
    }

    /**
     * Slots resolve normally even though the questions live in a module context.
     */
    public function test_get_quiz_questions_reads_from_a_qbank_instance(): void {
        [, $category] = $this->create_bank('Course bank');
        $quiz = $this->create_quiz_with_questions(['alpha', 'beta'], $category);

        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertCount(2, $slots);
        $this->assertSame('alpha', $slots[1]->name);
        $this->assertSame('beta', $slots[2]->name);
        $this->assertTrue($slots[1]->is_renameable());
        $this->assertNotEmpty($slots[1]->questionid);
    }

    /**
     * The bank label names the qbank instance, not just the category.
     *
     * This is the whole reason v5 exists as a separate class: on 4.x the useful label was
     * the category name, because there was only ever one bank per course.
     */
    public function test_bank_label_names_the_qbank_instance(): void {
        [, $category] = $this->create_bank('Physics question bank');
        $quiz = $this->create_quiz_with_questions(['alpha'], $category);

        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertStringContainsString('Physics question bank', $slots[1]->bankname);
    }

    /**
     * A course can hold several banks, and each question is labelled with its own.
     */
    public function test_a_course_can_have_several_banks(): void {
        [, $categoryone] = $this->create_bank('First bank');
        [, $categorytwo] = $this->create_bank('Second bank');

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        foreach ([['First', $categoryone], ['Second', $categorytwo]] as $spec) {
            $question = $this->questiongenerator->create_question('truefalse', null, [
                'category' => $spec[1]->id,
                'name' => $spec[0],
            ]);
            quiz_add_quiz_question($question->id, $quiz);
        }

        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertStringContainsString('First bank', $slots[1]->bankname);
        $this->assertStringContainsString('Second bank', $slots[2]->bankname);
    }

    /**
     * Renaming persists and does not fork a new version.
     */
    public function test_rename_question_persists_without_a_new_version(): void {
        global $DB;

        [, $category] = $this->create_bank('Course bank');
        $quiz = $this->create_quiz_with_questions(['alpha'], $category);

        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quiz->id);
        $entryid = $slots[1]->bankentryid;
        $versionsbefore = $DB->count_records('question_versions', ['questionbankentryid' => $entryid]);

        $source->rename_question($slots[1]->questionid, '0010_alpha');

        $this->assertSame('0010_alpha', $DB->get_field('question', 'name', ['id' => $slots[1]->questionid]));
        $this->assertSame(
            $versionsbefore,
            $DB->count_records('question_versions', ['questionbankentryid' => $entryid])
        );

        $reread = $source->get_quiz_questions((int)$quiz->id);
        $this->assertSame('0010_alpha', $reread[1]->name);
    }

    /**
     * Cross-course sharing is first class in 5.x, so usage must be counted site-wide.
     */
    public function test_usage_count_spans_courses(): void {
        $othercourse = $this->getDataGenerator()->create_course();

        [, $category] = $this->create_bank('Shared bank');
        $quizhere = $this->create_quiz_with_questions(['shared question'], $category);

        // A quiz in a different course, pulling from the same bank.
        $quizthere = $this->getDataGenerator()->create_module('quiz', ['course' => $othercourse->id]);
        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quizhere->id);
        $questionid = $slots[1]->questionid;
        quiz_add_quiz_question($questionid, $quizthere);

        $refreshed = $source->get_quiz_questions((int)$quizhere->id);

        $this->assertSame(2, $refreshed[1]->usagecount);
        $this->assertTrue($refreshed[1]->is_shared());

        $details = $source->get_usage_details($questionid, (int)$quizhere->id);
        $this->assertCount(1, $details);
        $this->assertFalse($details[0]['samecourse']);
        $this->assertSame($othercourse->fullname, $details[0]['coursename']);
    }

    /**
     * Random slots are flagged here exactly as they are on 4.x.
     */
    public function test_random_slots_are_flagged(): void {
        [, $category] = $this->create_bank('Course bank');
        $quiz = $this->create_quiz_with_questions(['alpha'], $category);

        [, $randomcategory] = $this->create_bank('Random pool');
        $this->questiongenerator->create_question('truefalse', null, [
            'category' => $randomcategory->id,
            'name' => 'pool question',
        ]);

        $structure = \mod_quiz\quiz_settings::create($quiz->id)->get_structure();
        $structure->add_random_questions(0, 1, [
            'filter' => [
                'category' => [
                    'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
                    'values' => [$randomcategory->id],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ]);

        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertCount(2, $slots);
        $this->assertTrue($slots[2]->israndom);
        $this->assertFalse($slots[2]->is_renameable());
        $this->assertNull($slots[2]->questionid);
    }

    /**
     * A quiz whose questions were authored inline is labelled as quiz-only, not as an error.
     */
    public function test_quiz_only_questions_are_labelled(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);

        $quizcategory = $this->questiongenerator->create_question_category([
            'contextid' => \context_module::instance($quiz->cmid)->id,
        ]);
        $question = $this->questiongenerator->create_question('truefalse', null, [
            'category' => $quizcategory->id,
            'name' => 'inline question',
        ]);
        quiz_add_quiz_question($question->id, $quiz);

        $source = new question_source_v5();
        $slots = $source->get_quiz_questions((int)$quiz->id);

        $this->assertSame(get_string('bankquizonly', 'local_quizrenumber'), $slots[1]->bankname);
        $this->assertTrue($slots[1]->is_renameable());
    }

    /**
     * A course with a bank is considered ready.
     */
    public function test_check_ready_passes_when_a_bank_exists(): void {
        $this->create_bank('Course bank');

        $source = new question_source_v5();
        $source->check_ready((int)$this->course->id);
        $this->assertTrue(true);
    }

    /**
     * The batched checklist counts work against the 5.x schema too.
     */
    public function test_get_quiz_summaries_counts_fixed_slots(): void {
        [, $category] = $this->create_bank('Course bank');
        $quizone = $this->create_quiz_with_questions(['alpha', 'beta'], $category);
        $quiztwo = $this->create_quiz_with_questions(['gamma'], $category);

        $source = new question_source_v5();
        $summaries = $source->get_quiz_summaries([(int)$quizone->id, (int)$quiztwo->id]);

        $this->assertSame(['fixed' => 2, 'random' => 0, 'total' => 2], $summaries[(int)$quizone->id]);
        $this->assertSame(['fixed' => 1, 'random' => 0, 'total' => 1], $summaries[(int)$quiztwo->id]);
    }

    /**
     * The factory picks this implementation on a 5.x site.
     *
     * @covers \local_quizrenumber\compat\question_source_factory
     */
    public function test_factory_selects_the_v5_implementation(): void {
        $this->assertInstanceOf(question_source_v5::class, question_source_factory::create());
    }
}
