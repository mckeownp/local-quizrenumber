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
 * Lists every quiz that uses a particular question.
 *
 * Reached from the "used in N places" badge on the preview, which is capped at a handful of
 * names; this is where the full list lives.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_quizrenumber\compat\question_source_factory;
use local_quizrenumber\quiz_finder;

$courseid = required_param('id', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);
$quizids = optional_param('quizids', '', PARAM_SEQUENCE);

$course = get_course($courseid);
require_login($course);

$coursecontext = context_course::instance($courseid);
require_capability('local/quizrenumber:manage', $coursecontext);

$pageurl = new moodle_url('/local/quizrenumber/usage.php', [
    'id' => $courseid,
    'questionid' => $questionid,
    'quizid' => $quizid,
    'quizids' => $quizids,
]);

$PAGE->set_url($pageurl);
$PAGE->set_context($coursecontext);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('usageheading', 'local_quizrenumber'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $coursecontext]));
$PAGE->navbar->add(
    get_string('pluginname', 'local_quizrenumber'),
    new moodle_url('/local/quizrenumber/index.php', ['id' => $courseid])
);
$PAGE->navbar->add(get_string('usageheading', 'local_quizrenumber'), $pageurl);

$source = question_source_factory::create();

// The capability above is on the course, so the question being asked about has to belong to
// this course as well. Without this a user could enumerate where any question on the site is
// used simply by changing the id in the URL.
$selected = quiz_finder::filter_quizids(
    array_values(array_filter(array_map('intval', explode(',', $quizids)))),
    $courseid,
    $source
);

// Only the quizzes the user actually came from are searched, rather than every quiz in the
// course: a course can hold hundreds, and resolving them all to answer one question would be
// far more work than the page is worth.
$candidates = quiz_finder::filter_quizids(array_merge([$quizid], $selected), $courseid, $source);

$questionname = '';
foreach ($candidates as $candidate) {
    foreach ($source->get_quiz_questions($candidate) as $slot) {
        if ($slot->questionid === $questionid) {
            $questionname = $slot->name;
            break 2;
        }
    }
}

if ($questionname === '') {
    throw new moodle_exception('errorquestionnotincourse', 'local_quizrenumber');
}

// Not excluding any quiz here: this page is the complete picture, including the quiz the
// user came from, so the counts line up with the badge they clicked. Because nothing is
// excluded, the course to compare against has to be passed explicitly.
$details = $source->get_usage_details($questionid, 0, 0, $courseid);

$rows = [];
foreach ($details['places'] as $place) {
    $rows[] = [
        'coursename' => $place['coursename'],
        'quizname' => $place['quizname'],
        'samecourse' => $place['samecourse'],
        'quizurl' => quiz_finder::get_quiz_url($place['courseid'], $place['quizid']),
    ];
}

$backurl = new moodle_url('/local/quizrenumber/index.php', ['id' => $courseid]);
if (!empty($selected)) {
    // Return to the preview the user was looking at, not just the quiz list.
    $backurl->param('step', 'configure');
    $backurl->param('quizids', implode(',', $selected));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('usageheading', 'local_quizrenumber'));

echo $OUTPUT->render_from_template('local_quizrenumber/usage_list', [
    'questionname' => $questionname,
    'total' => $details['total'],
    'rows' => $rows,
    'hasrows' => !empty($rows),
    'backurl' => $backurl->out(false),
]);

echo $OUTPUT->footer();
