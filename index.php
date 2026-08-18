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

/**
 * Entry point and step router for the quiz question renumbering tool.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_quizrenumber\compat\question_source_factory;
use local_quizrenumber\form\renumber_form;
use local_quizrenumber\form\select_quizzes_form;
use local_quizrenumber\output\preview;
use local_quizrenumber\quiz_finder;
use local_quizrenumber\renumber_service;
use local_quizrenumber\renumber_settings;

$courseid = required_param('id', PARAM_INT);
$step = optional_param('step', 'select', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);

$coursecontext = context_course::instance($courseid);
require_capability('local/quizrenumber:manage', $coursecontext);

$pageurl = new moodle_url('/local/quizrenumber/index.php', ['id' => $courseid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_quizrenumber'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $coursecontext]));
$PAGE->navbar->add(get_string('pluginname', 'local_quizrenumber'), $pageurl);

$source = question_source_factory::create();
$service = new renumber_service();

// A site part-way through the Moodle 5 question bank migration looks like a site with no
// questions, which would send the user hunting for a problem that is not theirs.
try {
    $source->check_ready($courseid);
} catch (moodle_exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'local_quizrenumber'));
    echo $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    exit;
}

$quizids = [];
$selectform = null;

// Step 2 posts here with step=configure; work out whether it actually gave us a selection.
if ($step === 'configure' || $step === 'apply') {
    $quizidsparam = optional_param('quizids', '', PARAM_SEQUENCE);

    if ($step === 'apply' || $quizidsparam !== '') {
        // The apply step always carries its selection in a hidden field, and the usage page
        // links back here with the same list so the preview can be rebuilt without re-ticking.
        $quizids = array_values(array_filter(array_map('intval', explode(',', $quizidsparam))));
    } else {
        $quizzes = quiz_finder::get_course_quizzes($courseid, $source);
        $selectform = new select_quizzes_form($pageurl, ['quizzes' => $quizzes, 'courseid' => $courseid]);
        $quizids = $selectform->get_selected_quizids();
    }

    // Never trust a quiz id that arrived in a request: confirm each one really is a quiz in
    // this course before reading or writing anything through it.
    $quizids = quiz_finder::filter_quizids($quizids, $courseid, $source);

    if (empty($quizids)) {
        $step = 'select';
    }
}

if ($step === 'select') {
    if ($selectform === null) {
        $quizzes = quiz_finder::get_course_quizzes($courseid, $source);
        $selectform = new select_quizzes_form($pageurl, ['quizzes' => $quizzes, 'courseid' => $courseid]);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'local_quizrenumber'));
    echo html_writer::div(get_string('intro', 'local_quizrenumber'), 'lead mb-3');
    $selectform->display();
    $PAGE->requires->js_call_amd('local_quizrenumber/selectquizzes', 'init', [select_quizzes_form::FORM_ID]);
    echo $OUTPUT->footer();
    exit;
}

$slots = quiz_finder::get_slots_for_quizzes($quizids, $source);

// Whether any selected question is shared is a property of the questions, not of the
// numbering options, so it can be settled before the form is built.
$hasshared = false;
foreach ($slots as $slot) {
    if ($slot->is_renameable() && $slot->editable && $slot->is_shared()) {
        $hasshared = true;
        break;
    }
}

$renumberform = new renumber_form($pageurl, [
    'courseid' => $courseid,
    'quizids' => $quizids,
    'hasshared' => $hasshared,
]);

if ($renumberform->is_cancelled()) {
    redirect($pageurl);
}

$submitted = ($step === 'apply') ? $renumberform->get_data() : null;

if ($submitted) {
    // The names the browser showed are never written. The plan is rebuilt here from the
    // submitted options and the current question names, and that rebuilt plan is what runs.
    $settings = renumber_settings::from_form_data($submitted);
    $plan = $service->build_plan($slots, $settings);
    $applied = $service->apply($plan, $source, $coursecontext);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('resultsheading', 'local_quizrenumber'));
    echo $OUTPUT->notification(
        get_string('resultssummary', 'local_quizrenumber', $applied),
        \core\output\notification::NOTIFY_SUCCESS
    );

    $renderable = new preview($plan, $settings, $source, true, $courseid, $quizids);
    echo $OUTPUT->render_from_template(
        'local_quizrenumber/preview_table',
        $renderable->export_for_template($OUTPUT)
    );

    echo $OUTPUT->single_button($pageurl, get_string('renumbermore', 'local_quizrenumber'), 'get');
    echo $OUTPUT->footer();
    exit;
}

// Steps 3 and 4: show the options form with a live preview underneath it.
$settings = renumber_settings::from_site_defaults();
$plan = $service->build_plan($slots, $settings);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('previewheading', 'local_quizrenumber'));

$renumberform->display();

$renderable = new preview($plan, $settings, $source, false, $courseid, $quizids);
echo $OUTPUT->render_from_template(
    'local_quizrenumber/preview_table',
    $renderable->export_for_template($OUTPUT)
);

$PAGE->requires->js_call_amd('local_quizrenumber/preview', 'init', [renumber_form::FORM_ID]);

echo $OUTPUT->footer();
