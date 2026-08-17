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

use local_quizrenumber\renumber_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Steps 3 and 4: choose the numbering options, then confirm and apply.
 *
 * The min and max attributes on the numeric fields exist to give the live preview instant
 * feedback. They are not a validation mechanism: everything is re-checked in validation()
 * below, and again in renumber_settings, because a crafted POST never touches the JavaScript.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renumber_form extends \moodleform {
    /** @var string Fixed DOM id, so the AMD module does not have to guess mform1, mform2 and so on. */
    const FORM_ID = 'local-quizrenumber-optionsform';

    /**
     * Build the options form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->updateAttributes(['id' => self::FORM_ID]);
        $courseid = $this->_customdata['courseid'];
        $quizids = $this->_customdata['quizids'];
        $hasshared = !empty($this->_customdata['hasshared']);
        $defaults = $this->get_site_defaults();

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'step', 'apply');
        $mform->setType('step', PARAM_ALPHA);

        $mform->addElement('hidden', 'quizids', implode(',', $quizids));
        $mform->setType('quizids', PARAM_SEQUENCE);

        $mform->addElement('header', 'optionsheader', get_string('numberingoptions', 'local_quizrenumber'));
        $mform->setExpanded('optionsheader');

        $mform->addElement('text', 'startnumber', get_string('startnumber', 'local_quizrenumber'), [
            'type' => 'number',
            'min' => 0,
            'max' => renumber_settings::MAX_START,
            'data-region' => 'local-quizrenumber-input',
        ]);
        $mform->setType('startnumber', PARAM_INT);
        $mform->setDefault('startnumber', $defaults['startnumber']);
        $mform->addHelpButton('startnumber', 'startnumber', 'local_quizrenumber');

        $mform->addElement('text', 'increment', get_string('increment', 'local_quizrenumber'), [
            'type' => 'number',
            'min' => renumber_settings::MIN_INCREMENT,
            'max' => renumber_settings::MAX_INCREMENT,
            'data-region' => 'local-quizrenumber-input',
        ]);
        $mform->setType('increment', PARAM_INT);
        $mform->setDefault('increment', $defaults['increment']);
        $mform->addHelpButton('increment', 'increment', 'local_quizrenumber');

        $mform->addElement('text', 'padding', get_string('padding', 'local_quizrenumber'), [
            'type' => 'number',
            'min' => renumber_settings::MIN_PADDING,
            'max' => renumber_settings::MAX_PADDING,
            'data-region' => 'local-quizrenumber-input',
        ]);
        $mform->setType('padding', PARAM_INT);
        $mform->setDefault('padding', $defaults['padding']);
        $mform->addHelpButton('padding', 'padding', 'local_quizrenumber');

        $mform->addElement(
            'select',
            'scope',
            get_string('scope', 'local_quizrenumber'),
            renumber_settings::get_scope_options(),
            ['data-region' => 'local-quizrenumber-input']
        );
        $mform->setDefault('scope', renumber_settings::SCOPE_PER_QUIZ);
        $mform->addHelpButton('scope', 'scope', 'local_quizrenumber');

        $mform->addElement(
            'advcheckbox',
            'stripprefix',
            get_string('stripprefix', 'local_quizrenumber'),
            get_string('stripprefixlabel', 'local_quizrenumber'),
            ['data-region' => 'local-quizrenumber-input']
        );
        $mform->setDefault('stripprefix', 1);
        $mform->addHelpButton('stripprefix', 'stripprefix', 'local_quizrenumber');

        $mform->addElement(
            'advcheckbox',
            'reserverandom',
            get_string('reserverandom', 'local_quizrenumber'),
            get_string('reserverandomlabel', 'local_quizrenumber'),
            ['data-region' => 'local-quizrenumber-input']
        );
        $mform->setDefault('reserverandom', 0);
        $mform->addHelpButton('reserverandom', 'reserverandom', 'local_quizrenumber');

        if ($hasshared) {
            $mform->addElement('header', 'confirmheader', get_string('confirmheader', 'local_quizrenumber'));
            $mform->setExpanded('confirmheader');
            $mform->addElement(
                'static',
                'sharedwarning',
                '',
                \html_writer::div(get_string('warningsharedquestions', 'local_quizrenumber'), 'alert alert-warning')
            );
            $mform->addElement(
                'advcheckbox',
                'confirmshared',
                '',
                get_string('confirmsharedlabel', 'local_quizrenumber')
            );
        }

        $this->add_action_buttons(true, get_string('applyrenumbering', 'local_quizrenumber'));
    }

    /**
     * Re-check everything the browser was trusted with.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $increment = (int)$data['increment'];
        if ($increment < renumber_settings::MIN_INCREMENT) {
            $errors['increment'] = get_string(
                'errorincrementtoosmall',
                'local_quizrenumber',
                renumber_settings::MIN_INCREMENT
            );
        } else if ($increment > renumber_settings::MAX_INCREMENT) {
            // Rejected outright rather than clamped: silently applying a different increment
            // than the one submitted would be a surprising result, not a helpful one.
            $errors['increment'] = get_string(
                'errorincrementtoolarge',
                'local_quizrenumber',
                renumber_settings::MAX_INCREMENT
            );
        }

        $startnumber = (int)$data['startnumber'];
        if ($startnumber < 0 || $startnumber > renumber_settings::MAX_START) {
            $errors['startnumber'] = get_string(
                'errorstartnumberrange',
                'local_quizrenumber',
                renumber_settings::MAX_START
            );
        }

        $padding = (int)$data['padding'];
        if ($padding < renumber_settings::MIN_PADDING || $padding > renumber_settings::MAX_PADDING) {
            $errors['padding'] = get_string('errorpaddingrange', 'local_quizrenumber', [
                'min' => renumber_settings::MIN_PADDING,
                'max' => renumber_settings::MAX_PADDING,
            ]);
        }

        if (!empty($this->_customdata['hasshared']) && empty($data['confirmshared'])) {
            $errors['confirmshared'] = get_string('errorconfirmrequired', 'local_quizrenumber');
        }

        return $errors;
    }

    /**
     * Site-wide defaults an administrator can set in settings.php.
     *
     * @return array
     */
    protected function get_site_defaults(): array {
        $config = get_config('local_quizrenumber');
        return [
            'startnumber' => isset($config->defaultstartnumber) ? (int)$config->defaultstartnumber : 10,
            'increment' => isset($config->defaultincrement) ? (int)$config->defaultincrement : 10,
            'padding' => isset($config->defaultpadding) ? (int)$config->defaultpadding : 4,
        ];
    }
}
