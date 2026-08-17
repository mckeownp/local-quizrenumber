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

/**
 * Select-all behaviour and a live count on the quiz selection step.
 *
 * @module     local_quizrenumber/selectquizzes
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const SELECTORS = {
    selectAll: '.local-quizrenumber-selectall input[type="checkbox"], input.local-quizrenumber-selectall',
    quiz: '.local-quizrenumber-quiz input[type="checkbox"], input.local-quizrenumber-quiz',
    count: '[data-region="local-quizrenumber-count"]',
};

/**
 * Wire up the select-all checkbox and the selected-quizzes counter.
 *
 * @param {String} formId Id of the quiz selection form.
 */
export const init = (formId) => {
    const form = document.getElementById(formId);
    if (!form) {
        return;
    }

    // moodleform's advcheckbox renders a hidden input alongside the visible one, so the
    // selectors above deliberately pick out checkboxes only.
    const selectAll = form.querySelector(SELECTORS.selectAll);
    const quizBoxes = Array.from(form.querySelectorAll(SELECTORS.quiz))
        .filter((box) => box.type === 'checkbox');
    const countRegion = form.querySelector(SELECTORS.count);

    if (!quizBoxes.length) {
        return;
    }

    const updateCount = async() => {
        const selected = quizBoxes.filter((box) => box.checked).length;
        if (countRegion) {
            countRegion.textContent = await getString('quizzesselected', 'local_quizrenumber', selected);
        }
        if (selectAll) {
            selectAll.checked = selected === quizBoxes.length;
            // Partial selections read as neither on nor off, which is more honest than
            // showing an unticked box next to five ticked quizzes.
            selectAll.indeterminate = selected > 0 && selected < quizBoxes.length;
        }
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            quizBoxes.forEach((box) => {
                if (box.checked !== selectAll.checked) {
                    box.checked = selectAll.checked;
                    // advcheckbox keeps its value in a paired hidden input; dispatching the
                    // event lets moodleform's own handler keep that in step.
                    box.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
            updateCount();
        });
    }

    quizBoxes.forEach((box) => {
        box.addEventListener('change', updateCount);
    });

    updateCount();
};
