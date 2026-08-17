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

namespace local_quizrenumber\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Step 2: choose which quizzes in the course to renumber.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_quizzes_form extends \moodleform {
    /** @var string Fixed DOM id, so the AMD module does not have to guess mform1, mform2 and so on. */
    const FORM_ID = 'local-quizrenumber-selectform';

    /**
     * Build the checkbox list.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $quizzes = $this->_customdata['quizzes'];
        $courseid = $this->_customdata['courseid'];

        $mform->updateAttributes(['id' => self::FORM_ID]);

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'step', 'configure');
        $mform->setType('step', PARAM_ALPHA);

        $mform->addElement('header', 'selectheader', get_string('selectquizzes', 'local_quizrenumber'));
        $mform->setExpanded('selectheader');

        if (empty($quizzes)) {
            $mform->addElement('static', 'noquizzes', '', get_string('noquizzesincourse', 'local_quizrenumber'));
            return;
        }

        // Select all is a convenience only; the real state lives in the individual checkboxes,
        // so no server-side handling is needed for it.
        $mform->addElement(
            'advcheckbox',
            'selectall',
            '',
            get_string('selectall', 'local_quizrenumber'),
            ['class' => 'local-quizrenumber-selectall']
        );

        foreach ($quizzes as $quiz) {
            $label = $this->build_quiz_label($quiz);
            $mform->addElement(
                'advcheckbox',
                'quiz[' . $quiz['id'] . ']',
                '',
                $label,
                ['class' => 'local-quizrenumber-quiz'],
                [0, 1]
            );
            $mform->setType('quiz[' . $quiz['id'] . ']', PARAM_INT);
        }

        $mform->addElement(
            'static',
            'selectedcount',
            '',
            \html_writer::span('', '', ['data-region' => 'local-quizrenumber-count'])
        );

        $this->add_action_buttons(false, get_string('continuetopreview', 'local_quizrenumber'));
    }

    /**
     * Compose the label shown next to a quiz checkbox.
     *
     * @param array $quiz One entry from quiz_finder::get_course_quizzes().
     * @return string
     */
    protected function build_quiz_label(array $quiz): string {
        $parts = [\html_writer::tag('strong', $quiz['name'])];

        if ($quiz['sectionname'] !== '') {
            $parts[] = \html_writer::span(s($quiz['sectionname']), 'text-muted ml-2');
        }

        $counts = get_string('fixedandrandomcount', 'local_quizrenumber', [
            'fixed' => $quiz['fixedcount'],
            'random' => $quiz['randomcount'],
        ]);
        $parts[] = \html_writer::span($counts, 'badge badge-info ml-2');

        if (!$quiz['visible']) {
            $parts[] = \html_writer::span(get_string('hiddenquiz', 'local_quizrenumber'), 'badge badge-secondary ml-2');
        }

        if ($quiz['mostlyrandom']) {
            $parts[] = \html_writer::span(
                get_string('warningallrandomshort', 'local_quizrenumber'),
                'badge badge-warning ml-2'
            );
        }

        return implode('', $parts);
    }

    /**
     * Require at least one quiz.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $selected = array_filter(isset($data['quiz']) ? $data['quiz'] : []);
        if (empty($selected)) {
            $errors['selectall'] = get_string('errornoquizselected', 'local_quizrenumber');
        }

        return $errors;
    }

    /**
     * Quiz ids the user ticked.
     *
     * @return int[] Empty if the form was not submitted or nothing was ticked.
     */
    public function get_selected_quizids(): array {
        $data = $this->get_data();
        if (!$data || empty($data->quiz)) {
            return [];
        }

        $selected = [];
        foreach ($data->quiz as $quizid => $ticked) {
            if ($ticked) {
                $selected[] = (int)$quizid;
            }
        }
        return $selected;
    }
}
