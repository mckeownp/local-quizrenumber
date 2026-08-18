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

namespace local_quizrenumber\compat;

/**
 * Chooses the question source implementation matching the running Moodle version.
 *
 * This is the only place in the plugin allowed to look at $CFG->branch.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_source_factory {
    /** @var question_source_interface|null Overridden implementation, for tests. */
    protected static ?question_source_interface $override = null;

    /**
     * Build the right question source for this site.
     *
     * @return question_source_interface
     */
    public static function create(): question_source_interface {
        global $CFG;

        if (self::$override !== null) {
            return self::$override;
        }

        $branch = (int)$CFG->branch;

        if ($branch < 405) {
            // Unreachable in practice: version.php requires Moodle 4.5, so installation is
            // blocked on older sites. Kept as a defensive fallback.
            throw new \moodle_exception('errorunsupportedversion', 'local_quizrenumber', '', $CFG->release);
        }

        if ($branch >= 500) {
            return new question_source_v5();
        }

        return new question_source_v4();
    }

    /**
     * Force a particular implementation. Only for use by tests.
     *
     * @param question_source_interface|null $source Pass null to restore normal detection.
     * @return void
     */
    public static function set_override(?question_source_interface $source): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('question_source_factory::set_override is only available in tests.');
        }
        self::$override = $source;
    }
}
