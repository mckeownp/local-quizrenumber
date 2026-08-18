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

namespace local_quizrenumber\tests\fixtures;

use local_quizrenumber\compat\question_source_interface;

/**
 * An in-memory question source, so the numbering rules can be tested without a question bank.
 *
 * This is the point of the compatibility layer: because every consumer talks to the
 * interface, the rules in renumber_service can be exercised identically on any Moodle
 * version, and the version-specific classes are tested separately against a real schema.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_question_source implements question_source_interface {
    /** @var array Question id => name, standing in for the question table. */
    public array $names = [];

    /** @var array Question id => usage count. */
    public array $usagecounts = [];

    /** @var array Slots keyed by quiz id. */
    public array $slots = [];

    /** @var bool Whether check_ready() should throw. */
    public bool $notready = false;

    /**
     * Build the stub, optionally pre-loaded with slots.
     *
     * @param array $slots Quiz id => question_slot[].
     */
    public function __construct(array $slots = []) {
        $this->slots = $slots;
    }

    /**
     * The slots this stub was given for a quiz.
     *
     * @param int $quizid
     * @return \local_quizrenumber\compat\question_slot[]
     */
    public function get_quiz_questions(int $quizid): array {
        return $this->slots[$quizid] ?? [];
    }

    /**
     * Counts derived from the stubbed slots.
     *
     * @param array $quizids
     * @return array
     */
    public function get_quiz_summaries(array $quizids): array {
        $summaries = [];
        foreach ($quizids as $quizid) {
            $counts = ['fixed' => 0, 'random' => 0, 'total' => 0];
            foreach ($this->get_quiz_questions((int)$quizid) as $slot) {
                $counts['total']++;
                $counts[$slot->israndom ? 'random' : 'fixed']++;
            }
            $summaries[(int)$quizid] = $counts;
        }
        return $summaries;
    }

    /**
     * Record a rename in memory instead of writing to the question bank.
     *
     * @param int $questionid
     * @param string $newname
     * @return void
     */
    public function rename_question(int $questionid, string $newname): void {
        $this->names[$questionid] = $newname;
    }

    /**
     * The usage count this stub was told to report, defaulting to one.
     *
     * @param int $questionid
     * @return int
     */
    public function get_usage_count(int $questionid): int {
        return $this->usagecounts[$questionid] ?? 1;
    }

    /** @var array Question id => list of places, standing in for the usage query. */
    public array $usagedetails = [];

    /**
     * Replays whatever places the test loaded, honouring the limit so the capping logic
     * can be exercised without a database.
     *
     * @param int $questionid
     * @param int $excludequizid
     * @param int $limit
     * @param int $comparecourseid
     * @return array
     */
    public function get_usage_details(
        int $questionid,
        int $excludequizid = 0,
        int $limit = 0,
        int $comparecourseid = 0
    ): array {
        $places = $this->usagedetails[$questionid] ?? [];
        return [
            'places' => $limit > 0 ? array_slice($places, 0, $limit) : $places,
            'total' => count($places),
        ];
    }

    /**
     * Throws only when the test asked it to, for the mid-upgrade path.
     *
     * @param int $courseid
     * @return void
     */
    public function check_ready(int $courseid): void {
        if ($this->notready) {
            throw new \moodle_exception('errorbankupgradepending', 'local_quizrenumber');
        }
    }
}
