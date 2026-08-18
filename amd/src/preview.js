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
 * Live preview of the new question names.
 *
 * Every name already on the page is enough to compute the result, so the preview is
 * recomputed locally on each keystroke rather than round-tripping to the server. The server
 * recomputes the whole plan from scratch on submit regardless, so nothing here is trusted.
 *
 * @module     local_quizrenumber/preview
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const SELECTORS = {
    preview: '[data-region="local-quizrenumber-preview"]',
    row: '[data-region="preview-row"]',
    stripped: '[data-region="strippedname"]',
    newname: '[data-region="newname"]',
    summary: '[data-region="local-quizrenumber-summary"]',
};

/** Mirrors renumber_service::PREFIX_REGEX. Kept in step with the PHP by the unit tests. */
const PREFIX_REGEX = /^\d{3,6}_+/;

/** Mirrors renumber_service::NAME_MAX_LENGTH. */
const NAME_MAX_LENGTH = 255;

/** Mirrors renumber_service::SEPARATOR. */
const SEPARATOR = '_';

/**
 * Remove a leading number prefix of the kind this plugin writes.
 *
 * @param {String} name The current question name.
 * @returns {String} The name with at most one prefix removed.
 */
const stripPrefix = (name) => name.replace(PREFIX_REGEX, '');

/**
 * Zero-pad a number to the requested width, never truncating.
 *
 * @param {Number} number The number to format.
 * @param {Number} padding Minimum number of digits.
 * @returns {String} The padded number.
 */
const formatNumber = (number, padding) => String(number).padStart(padding, '0');

/**
 * Read the current values out of the options form.
 *
 * @param {HTMLFormElement} form The renumber options form.
 * @returns {Object} The settings, coerced to the right types.
 */
const readSettings = (form) => {
    const value = (name, fallback) => {
        // The advcheckbox element in moodleform renders a hidden input carrying the
        // unchecked value *and* a checkbox, both under the same name. The hidden one comes
        // first in the DOM, so asking for the name alone would always return a non-empty
        // string and every checkbox would read as on. Ask for the checkbox specifically.
        const checkbox = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        if (checkbox) {
            return checkbox.checked;
        }

        const element = form.querySelector(`[name="${name}"]`);
        if (!element) {
            return fallback;
        }
        return element.value;
    };

    return {
        startnumber: parseInt(value('startnumber', 10), 10) || 0,
        increment: parseInt(value('increment', 10), 10) || 1,
        padding: parseInt(value('padding', 4), 10) || 1,
        scope: value('scope', 'perquiz'),
        stripprefix: value('stripprefix', true),
        reserverandom: value('reserverandom', false),
    };
};

/**
 * Recompute every preview row from the current settings.
 *
 * @param {HTMLElement} preview The preview container.
 * @param {Object} settings The current numbering options.
 * @returns {Number} How many rows will actually be renamed.
 */
const recompute = (preview, settings) => {
    const rows = preview.querySelectorAll(SELECTORS.row);
    let counter = settings.startnumber;
    let currentQuizId = null;
    let applied = 0;

    rows.forEach((row) => {
        const quizid = row.dataset.quizid;
        if (currentQuizId !== quizid) {
            if (currentQuizId !== null && settings.scope === 'perquiz') {
                counter = settings.startnumber;
            }
            currentQuizId = quizid;
        }

        const strippedCell = row.querySelector(SELECTORS.stripped);
        const newNameCell = row.querySelector(SELECTORS.newname);
        if (!strippedCell || !newNameCell) {
            return;
        }

        const isRandom = row.dataset.random === '1';
        // 'unchanged' rows are still renamed candidates; only these two are structurally
        // impossible to rename, so they never take part in the sequence.
        const isUnrenameable = row.dataset.skip === 'missing' || row.dataset.skip === 'nopermission';

        if (isRandom) {
            strippedCell.textContent = '';
            newNameCell.textContent = '';
            if (settings.reserverandom) {
                counter += settings.increment;
            }
            return;
        }

        if (isUnrenameable) {
            strippedCell.textContent = '';
            newNameCell.textContent = '';
            return;
        }

        const currentName = row.dataset.name || '';
        const stripped = settings.stripprefix ? stripPrefix(currentName) : currentName;
        let newName = formatNumber(counter, settings.padding) + SEPARATOR + stripped;
        if (newName.length > NAME_MAX_LENGTH) {
            newName = newName.substring(0, NAME_MAX_LENGTH);
        }

        strippedCell.textContent = stripped;
        newNameCell.textContent = newName;
        counter += settings.increment;
        applied += 1;
    });

    return applied;
};

/**
 * Wire the options form to the preview table.
 *
 * @param {String} formId Id of the renumber options form.
 */
export const init = (formId) => {
    const form = document.getElementById(formId);
    const preview = document.querySelector(SELECTORS.preview);

    if (!form || !preview) {
        return;
    }

    const update = async() => {
        const applied = recompute(preview, readSettings(form));
        const summary = preview.querySelector(SELECTORS.summary);
        if (summary) {
            summary.textContent = await getString('previewsummary', 'local_quizrenumber', applied);
        }
    };

    form.addEventListener('input', update);
    form.addEventListener('change', update);

    update();
};
