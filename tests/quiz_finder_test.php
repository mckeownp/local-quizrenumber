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
 * Tests for linking to the quizzes that use a shared question.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\quiz_finder
 */
final class quiz_finder_test extends \advanced_testcase {
    /**
     * A visible quiz in a course the user can reach gets a link to its view page.
     */
    public function test_quiz_url_for_an_accessible_quiz(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        $url = quiz_finder::get_quiz_url((int)$course->id, (int)$quiz->id);

        $this->assertStringContainsString('/mod/quiz/view.php', $url);
        $this->assertStringContainsString('id=' . $quiz->cmid, $url);
    }

    /**
     * A course the user is not in yields no link rather than one that would bounce them.
     */
    public function test_no_url_for_a_course_the_user_cannot_access(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // A user with no enrolment anywhere.
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame('', quiz_finder::get_quiz_url((int)$course->id, (int)$quiz->id));
    }

    /**
     * A hidden quiz is not linked for someone who cannot see hidden activities.
     */
    public function test_no_url_for_a_hidden_quiz(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'visible' => 0,
        ]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->assertSame('', quiz_finder::get_quiz_url((int)$course->id, (int)$quiz->id));

        // A teacher can see hidden activities, so for them it is linkable.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertStringContainsString(
            '/mod/quiz/view.php',
            quiz_finder::get_quiz_url((int)$course->id, (int)$quiz->id)
        );
    }

    /**
     * A quiz id that does not belong to the course yields nothing.
     */
    public function test_no_url_when_the_quiz_is_not_in_that_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $courseone = $this->getDataGenerator()->create_course();
        $coursetwo = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $coursetwo->id]);

        $this->assertSame('', quiz_finder::get_quiz_url((int)$courseone->id, (int)$quiz->id));
    }

    /**
     * A course that no longer exists is handled rather than fatal.
     */
    public function test_no_url_for_a_missing_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame('', quiz_finder::get_quiz_url(-1, 1));
    }
}
