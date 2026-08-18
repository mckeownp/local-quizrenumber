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

namespace local_quizrenumber;

use local_quizrenumber\form\select_quizzes_form;

/**
 * Tests for the quiz selection step.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_quizrenumber\form\select_quizzes_form
 */
final class select_quizzes_form_test extends \advanced_testcase {
    /**
     * Reach the protected badge-class helper.
     *
     * @param int $fixed
     * @param int $random
     * @return string
     */
    protected function badge_class(int $fixed, int $random): string {
        $method = new \ReflectionMethod(select_quizzes_form::class, 'count_badge_class');
        $method->setAccessible(true);

        return $method->invoke(null, ['fixedcount' => $fixed, 'randomcount' => $random]);
    }

    /**
     * The count badge is colour coded by whether renumbering will do anything.
     *
     * @dataProvider badge_class_provider
     * @param int $fixed
     * @param int $random
     * @param string $expected
     */
    public function test_count_badge_class(int $fixed, int $random, string $expected): void {
        $this->assertSame($expected, $this->badge_class($fixed, $random));
    }

    /**
     * Cases for test_count_badge_class.
     *
     * @return array
     */
    public static function badge_class_provider(): array {
        return [
            'all fixed is green' => [12, 0, 'local-quizrenumber-count-fixed'],
            'one fixed is green' => [1, 0, 'local-quizrenumber-count-fixed'],
            'mixed is orange' => [11, 1, 'local-quizrenumber-count-mixed'],
            'mostly random is still orange' => [1, 30, 'local-quizrenumber-count-mixed'],
            'all random is red' => [0, 5, 'local-quizrenumber-count-random'],
            'empty quiz is neutral' => [0, 0, 'local-quizrenumber-count-empty'],
        ];
    }

    /**
     * Every class the helper can return is actually defined in the stylesheet.
     *
     * A colour coded badge that falls back to unstyled is worse than no colour at all, and
     * this is exactly the pairing that rots when someone renames one and not the other.
     */
    public function test_every_badge_class_is_styled(): void {
        $css = file_get_contents(__DIR__ . '/../styles.css');

        foreach ([[5, 0], [5, 5], [0, 5], [0, 0]] as $counts) {
            $class = $this->badge_class($counts[0], $counts[1]);
            $this->assertStringContainsString(
                '.' . $class,
                $css,
                "The stylesheet has no rule for {$class}."
            );
        }
    }

    /**
     * Every selector in the stylesheet is namespaced to this plugin.
     *
     * Moodle concatenates every plugin's styles.css into one sheet, so an unprefixed
     * selector here would leak into the whole site.
     */
    public function test_stylesheet_selectors_are_namespaced(): void {
        $css = file_get_contents(__DIR__ . '/../styles.css');

        preg_match_all('/^\s*([.#][a-zA-Z0-9_-]+)/m', $css, $matches);
        $this->assertNotEmpty($matches[1], 'No selectors found to check.');

        foreach ($matches[1] as $selector) {
            $this->assertStringStartsWith(
                '.local-quizrenumber-',
                $selector,
                "Selector {$selector} is not namespaced to this plugin."
            );
        }
    }
}
