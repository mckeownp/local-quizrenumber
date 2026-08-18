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

namespace local_quizrenumber\event;

/**
 * Fired once per renumbering operation.
 *
 * The full old name to new name mapping travels in the event payload, which is the plugin's
 * entire audit trail: there is no custom log table, so this event is what a site is expected
 * to keep if it wants a record of who renamed what.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 * @var int $count Number of questions renamed.
 * @var array $quizids Ids of the quizzes involved.
 * @var array $renames List of ['questionid' => int, 'oldname' => string, 'newname' => string].
 * }
 */
class questions_renumbered extends \core\event\base {
    /**
     * Set the basic event properties.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = null;
    }

    /**
     * Name of this event, shown in log reports.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventquestionsrenumbered', 'local_quizrenumber');
    }

    /**
     * One line description for the log.
     *
     * @return string
     */
    public function get_description() {
        $count = $this->other['count'] ?? 0;
        $quizcount = count($this->other['quizids'] ?? []);
        return "The user with id '{$this->userid}' renumbered {$count} question(s) " .
            "across {$quizcount} quiz(zes) in the course with id '{$this->courseid}'.";
    }

    /**
     * Check that the payload this event needs is actually present.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['count'])) {
            throw new \coding_exception('The \'count\' value must be set in other.');
        }
        if (!isset($this->other['quizids'])) {
            throw new \coding_exception('The \'quizids\' value must be set in other.');
        }
        if (!isset($this->other['renames'])) {
            throw new \coding_exception('The \'renames\' value must be set in other.');
        }
    }
}
