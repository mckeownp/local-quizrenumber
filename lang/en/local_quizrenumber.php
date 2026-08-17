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
 * English strings for local_quizrenumber.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['applyrenumbering'] = 'Apply renumbering';
$string['bankandcategory'] = '{$a->bank} ({$a->category})';
$string['bankquizonly'] = 'Quiz-only question';
$string['bankunknown'] = 'Unknown question bank';
$string['columnbank'] = 'Question bank';
$string['columncurrentname'] = 'Current name';
$string['columnnewname'] = 'New name';
$string['columnnotes'] = 'Notes';
$string['columnslot'] = 'Slot';
$string['columnstrippedname'] = 'Stripped name';
$string['confirmheader'] = 'Confirm';
$string['confirmsharedlabel'] = 'I understand this renames the question in the shared question bank, and that the new name will apply to every quiz that uses it.';
$string['continuetopreview'] = 'Continue to preview';
$string['defaultincrement'] = 'Default increment';
$string['defaultincrement_desc'] = 'The increment offered when the renumbering form first loads. Users can still change it, up to the maximum of {$a}.';
$string['defaultpadding'] = 'Default padding width';
$string['defaultpadding_desc'] = 'How many digits the number prefix is padded to when the form first loads. A width of 4 produces prefixes like 0010_.';
$string['defaultstartnumber'] = 'Default start number';
$string['defaultstartnumber_desc'] = 'The start number offered when the renumbering form first loads.';
$string['errorbankupgradepending'] = 'This site\'s question bank upgrade has not finished yet, so the question banks for this course cannot be read reliably. Please try again once the scheduled tasks have run.';
$string['errorconfirmrequired'] = 'You must confirm that you understand shared questions will be renamed everywhere they are used.';
$string['errorincrementtoolarge'] = 'The increment cannot be larger than {$a}.';
$string['errorincrementtoosmall'] = 'The increment must be at least {$a}.';
$string['errornoquizselected'] = 'Select at least one quiz to renumber.';
$string['errorpaddingrange'] = 'The padding width must be between {$a->min} and {$a->max}.';
$string['errorqbankmissing'] = 'This site reports Moodle 5.0 or later, but the question bank module is not installed. The renumbering tool cannot run until that is resolved.';
$string['errorstartnumberrange'] = 'The start number must be between 0 and {$a}.';
$string['errorunsupportedversion'] = 'This plugin supports Moodle 4.0 and later. This site reports version {$a}.';
$string['eventquestionsrenumbered'] = 'Quiz questions renumbered';
$string['fixedandrandomcount'] = '{$a->fixed} fixed / {$a->random} random';
$string['hiddenquiz'] = 'Hidden';
$string['increment'] = 'Increment';
$string['increment_help'] = 'How much the number goes up between one question and the next. Leaving gaps, for example 10, makes it easy to insert a question later without renumbering everything. The maximum is 100.';
$string['intro'] = 'Renumber the questions in one or more quizzes so their names carry a zero-padded, incrementing prefix. This makes them sort predictably in the question bank.';
$string['nothingtorenumber'] = 'There is nothing to renumber in the quizzes you selected. They may contain only random questions, or questions you do not have permission to edit.';
$string['noquizzesincourse'] = 'This course has no quizzes you can renumber.';
$string['numberingoptions'] = 'Numbering options';
$string['padding'] = 'Padding width';
$string['padding_help'] = 'How many digits the number is padded to. A width of 4 turns 10 into 0010. If the numbering runs past the width, the numbers get longer rather than being cut off, and you will see a warning.';
$string['pluginname'] = 'Renumber quiz questions';
$string['previewheading'] = 'Preview renumbering';
$string['previewsummary'] = '{$a} question(s) will be renamed.';
$string['previewtablecaption'] = 'Preview of the question names before and after renumbering';
$string['privacy:metadata'] = 'The Renumber quiz questions plugin stores no personal data. It renames questions in the question bank, which the question subsystem is responsible for, and records what it did in the standard log.';
$string['quizrenumber:manage'] = 'Renumber quiz questions';
$string['quizzesselected'] = '{$a} quiz(zes) selected';
$string['renumbermore'] = 'Renumber more quizzes';
$string['reserverandom'] = 'Reserve numbers for random slots';
$string['reserverandomlabel'] = 'Advance the sequence for random slots as well';
$string['reserverandom_help'] = 'Off by default, so the numbers run consecutively across the questions that are actually renamed. Turn it on if you would rather the numbers line up with the slot positions shown in the quiz, which leaves gaps where the random slots are.';
$string['resultsheading'] = 'Renumbering results';
$string['resultssummary'] = '{$a} question(s) renamed.';
$string['scope'] = 'Numbering scope';
$string['scope_help'] = 'Restart per quiz gives each selected quiz its own sequence, starting again at the start number. Continuous runs one sequence across every selected quiz in turn.';
$string['scopecontinuous'] = 'Continuous across all selected quizzes';
$string['scopeperquiz'] = 'Restart for each quiz';
$string['selectall'] = 'Select all quizzes';
$string['selectquizzes'] = 'Select quizzes';
$string['settingsheading'] = 'Renumber quiz questions';
$string['skipmissing'] = 'Question missing - not renumbered';
$string['skipnopermission'] = 'No permission to edit - not renumbered';
$string['skiprandom'] = 'Random - not renumbered';
$string['skipunchanged'] = 'Already named this way';
$string['startnumber'] = 'Start number';
$string['startnumber_help'] = 'The number given to the first question. With the default padding of 4, a start number of 10 produces the prefix 0010_.';
$string['stripprefix'] = 'Strip existing prefix';
$string['stripprefix_help'] = 'On by default, so running the tool twice does not stack prefixes such as 0010_0020_name. Only a leading group of three to six digits followed by underscores is removed, so a title that genuinely begins with a short number is left alone. Turn this off if an existing leading number is part of the real title.';
$string['stripprefixlabel'] = 'Remove any existing number prefix before applying the new one';
$string['usageothercourse'] = '{$a->coursename} - {$a->quizname}';
$string['usedalsoin'] = 'Also used in: {$a}';
$string['usedelsewhere'] = 'Used in {$a} places';
$string['warningallrandomshort'] = 'All random';
$string['warningduplicatenames'] = 'Some questions in a quiz share the same name: {$a}. Renumbering will give them distinct prefixes.';
$string['warningnametruncated'] = '{$a} new name(s) would be longer than the 255 characters a question name allows, and will be shortened to fit.';
$string['warningpaddingoverflow'] = 'The numbering runs past the padding width of {$a} digits, so some prefixes will be longer than the others. Increase the padding width, or lower the start number or increment, if you want them all the same length.';
$string['warningsharedquestions'] = 'At least one of the selected questions is used by more than one quiz. Question names live in the shared question bank, so renaming affects every quiz that uses the question, including quizzes in other courses.';
