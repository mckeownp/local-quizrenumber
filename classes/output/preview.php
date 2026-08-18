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

namespace local_quizrenumber\output;

use local_quizrenumber\compat\question_source_interface;
use local_quizrenumber\renumber_plan;
use local_quizrenumber\renumber_settings;
use renderer_base;

/**
 * Turns a renumbering plan into template context for the preview and results tables.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview implements \renderable, \templatable {
    /**
     * @var int How many quizzes to name in the badge tooltip before summarising the rest.
     *
     * Eight fits in a readable tooltip. Anything beyond that becomes a wall of text, which is
     * exactly what happened on a real course where one exam question was reused in 83 quizzes.
     */
    const TOOLTIP_MAX_PLACES = 8;

    /**
     * Build the renderable.
     *
     * @param renumber_plan $plan The plan being displayed.
     * @param renumber_settings $settings The options that produced it.
     * @param question_source_interface|null $source Pass null to skip the "used elsewhere" tooltips.
     * @param bool $isresults True when rendering the after-the-fact results table.
     */
    public function __construct(
        /** @var renumber_plan The plan being displayed */
        protected readonly renumber_plan $plan,
        /** @var renumber_settings The options that produced it */
        protected readonly renumber_settings $settings,
        /** @var question_source_interface|null Used to describe where shared questions are used */
        protected readonly ?question_source_interface $source = null,
        /** @var bool Whether this is the read-only results table rather than the live preview */
        protected readonly bool $isresults = false,
        /** @var int Course being worked on, needed to build the usage links */
        protected readonly int $courseid = 0,
        /** @var array Quiz ids currently selected, so the usage page can offer a way back */
        protected readonly array $selectedquizids = [],
    ) {
    }

    /**
     * Export for the Mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $quizzes = [];

        foreach ($this->plan->get_rows_by_quiz() as $group) {
            $rows = [];
            foreach ($group['rows'] as $row) {
                $slot = $row->slot;
                $rows[] = [
                    'slot' => $slot->slot,
                    'currentname' => $slot->name,
                    'strippedname' => $row->strippedname,
                    'newname' => $row->newname,
                    'bankname' => $slot->bankname,
                    'israndom' => $slot->israndom,
                    'skipreason' => (string)$row->skipreason,
                    'skiplabel' => $row->get_skip_label(),
                    'skipped' => !$row->will_apply(),
                    'shared' => $slot->is_shared(),
                    'usagecount' => $slot->usagecount,
                    'usagetooltip' => $this->build_usage_tooltip($slot),
                    'usageurl' => $this->build_usage_url($slot),
                ];
            }

            $quizzes[] = [
                'quizid' => $group['quizid'],
                'quizname' => $group['quizname'],
                'rows' => $rows,
            ];
        }

        return [
            'quizzes' => $quizzes,
            'warnings' => $this->plan->get_warning_messages(),
            'hasrows' => !empty($quizzes),
            'isresults' => $this->isresults,
            'appliedcount' => count($this->plan->get_applicable_rows()),
            'skippedcount' => count($this->plan->get_skipped_rows()),
            // Mirrors the server-side rules so the JavaScript can recompute names locally.
            'jsconfig' => json_encode([
                'startnumber' => $this->settings->startnumber,
                'increment' => $this->settings->increment,
                'padding' => $this->settings->padding,
                'scope' => $this->settings->scope,
                'stripprefix' => $this->settings->stripprefix,
                'reserverandom' => $this->settings->reserverandom,
            ]),
        ];
    }

    /**
     * Link to the full usage list for a shared question.
     *
     * @param \local_quizrenumber\compat\question_slot $slot
     * @return string Empty if the question is not shared, so the template shows a plain badge.
     */
    protected function build_usage_url(\local_quizrenumber\compat\question_slot $slot): string {
        if (!$slot->is_shared() || !$slot->is_renameable() || $this->courseid === 0) {
            return '';
        }

        return (new \moodle_url('/local/quizrenumber/usage.php', [
            'id' => $this->courseid,
            'questionid' => $slot->questionid,
            'quizid' => $slot->quizid,
            'quizids' => implode(',', $this->selectedquizids),
        ]))->out(false);
    }

    /**
     * Describe the other places a shared question is used.
     *
     * Capped rather than exhaustive: a question reused across years can appear in dozens of
     * quizzes, and a title attribute listing eighty of them is unreadable. The exact count is
     * already on the badge, and the full list is one click away.
     *
     * @param \local_quizrenumber\compat\question_slot $slot
     * @return string Empty if the question is not shared or no source was supplied.
     */
    protected function build_usage_tooltip(\local_quizrenumber\compat\question_slot $slot): string {
        if (!$slot->is_shared() || $this->source === null || !$slot->is_renameable()) {
            return '';
        }

        $details = $this->source->get_usage_details(
            $slot->questionid,
            $slot->quizid,
            self::TOOLTIP_MAX_PLACES,
            $this->courseid
        );
        if (empty($details['places'])) {
            return '';
        }

        $places = [];
        foreach ($details['places'] as $detail) {
            if ($detail['samecourse']) {
                $places[] = $detail['quizname'];
            } else {
                // Naming the course matters: the user may not even have edit rights there.
                $places[] = get_string('usageothercourse', 'local_quizrenumber', $detail);
            }
        }

        $tooltip = get_string('usedalsoin', 'local_quizrenumber', implode('; ', $places));

        $remaining = $details['total'] - count($details['places']);
        if ($remaining > 0) {
            $tooltip .= ' ' . get_string('usageandothers', 'local_quizrenumber', $remaining);
        }

        return $tooltip;
    }
}
