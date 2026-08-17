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

use local_quizrenumber\form\renumber_form;

/**
 * Server-side validation of the numbering options.
 *
 * The browser enforces the same ranges for instant feedback, which is why these tests
 * exist: a crafted POST never runs that JavaScript, so the limits have to hold here.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\form\renumber_form
 */
final class renumber_form_test extends \advanced_testcase {
    /**
     * Build a form to validate against.
     *
     * @param bool $hasshared Whether the selection contains a shared question.
     * @return renumber_form
     */
    protected function make_form(bool $hasshared = false): renumber_form {
        $this->resetAfterTest();
        $this->setAdminUser();

        return new renumber_form(new \moodle_url('/local/quizrenumber/index.php'), [
            'courseid' => SITEID,
            'quizids' => [1],
            'hasshared' => $hasshared,
        ]);
    }

    /**
     * Valid submitted data.
     *
     * @param array $overrides
     * @return array
     */
    protected function valid_data(array $overrides = []): array {
        return $overrides + [
            'startnumber' => 10,
            'increment' => 10,
            'padding' => 4,
            'scope' => renumber_settings::SCOPE_PER_QUIZ,
            'stripprefix' => 1,
            'reserverandom' => 0,
        ];
    }

    /**
     * A sane submission passes.
     */
    public function test_valid_options_are_accepted(): void {
        $form = $this->make_form();
        $this->assertSame([], $form->validation($this->valid_data(), []));
    }

    /**
     * The documented maximum increment is accepted, one more is not. This is the boundary
     * a crafted request would aim at.
     */
    public function test_increment_boundary(): void {
        $form = $this->make_form();

        $atlimit = $form->validation($this->valid_data(['increment' => renumber_settings::MAX_INCREMENT]), []);
        $this->assertArrayNotHasKey('increment', $atlimit);

        $overlimit = $form->validation($this->valid_data(['increment' => renumber_settings::MAX_INCREMENT + 1]), []);
        $this->assertArrayHasKey('increment', $overlimit);
    }

    /**
     * An increment below one would never terminate the sequence sensibly.
     */
    public function test_increment_must_be_positive(): void {
        $form = $this->make_form();

        $errors = $form->validation($this->valid_data(['increment' => 0]), []);
        $this->assertArrayHasKey('increment', $errors);

        $errors = $form->validation($this->valid_data(['increment' => -10]), []);
        $this->assertArrayHasKey('increment', $errors);
    }

    /**
     * Start number and padding are range checked too.
     */
    public function test_startnumber_and_padding_ranges(): void {
        $form = $this->make_form();

        $errors = $form->validation($this->valid_data(['startnumber' => -1]), []);
        $this->assertArrayHasKey('startnumber', $errors);

        $errors = $form->validation($this->valid_data(['startnumber' => renumber_settings::MAX_START + 1]), []);
        $this->assertArrayHasKey('startnumber', $errors);

        $errors = $form->validation($this->valid_data(['padding' => 0]), []);
        $this->assertArrayHasKey('padding', $errors);

        $errors = $form->validation($this->valid_data(['padding' => renumber_settings::MAX_PADDING + 1]), []);
        $this->assertArrayHasKey('padding', $errors);
    }

    /**
     * When a shared question is involved the confirmation is genuinely required.
     */
    public function test_shared_confirmation_is_required(): void {
        $form = $this->make_form(true);

        $errors = $form->validation($this->valid_data(['confirmshared' => 0]), []);
        $this->assertArrayHasKey('confirmshared', $errors);

        $errors = $form->validation($this->valid_data(['confirmshared' => 1]), []);
        $this->assertArrayNotHasKey('confirmshared', $errors);
    }

    /**
     * With nothing shared, the confirmation is not demanded.
     */
    public function test_confirmation_not_required_when_nothing_is_shared(): void {
        $form = $this->make_form(false);
        $errors = $form->validation($this->valid_data(), []);
        $this->assertArrayNotHasKey('confirmshared', $errors);
    }
}
