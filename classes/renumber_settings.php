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

/**
 * The numbering options chosen by the user, validated.
 *
 * Constructing one of these is the last line of defence: the form validates first and the
 * JavaScript validates before that, but neither is trustworthy on its own, so the values
 * are checked again here where the numbering actually happens.
 *
 * @package    local_quizrenumber
 * @copyright  2026 Paul McKeown, University of Canterbury <paul.mckeown@canterbury.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renumber_settings {
    /** @var int Hard cap on the increment. A four digit gap is already generous. */
    const MAX_INCREMENT = 100;

    /** @var int Smallest allowed increment. */
    const MIN_INCREMENT = 1;

    /** @var int Largest allowed start number. */
    const MAX_START = 999999;

    /** @var int Smallest allowed padding width. */
    const MIN_PADDING = 1;

    /** @var int Largest allowed padding width. */
    const MAX_PADDING = 6;

    /** @var string Each quiz starts again at the start number. */
    const SCOPE_PER_QUIZ = 'perquiz';

    /** @var string One running sequence across every selected quiz. */
    const SCOPE_CONTINUOUS = 'continuous';

    /**
     * Build and validate a set of options.
     *
     * Readonly throughout: once a set of options has been validated, nothing should be able
     * to change it out from under the numbering, which is exactly the class of bug that
     * would produce a half-renumbered quiz.
     *
     * @param int $startnumber Number given to the first question.
     * @param int $increment Step between consecutive questions.
     * @param int $padding Number of digits to pad the prefix to.
     * @param string $scope One of the SCOPE_* constants.
     * @param bool $stripprefix Whether to strip an existing NNNN_ prefix before applying the new one.
     * @param bool $reserverandom Whether random slots consume a number even though they are never renamed.
     * @throws \invalid_parameter_exception If any value is out of range.
     */
    public function __construct(
        /** @var int Number given to the first question */
        public readonly int $startnumber = 10,
        /** @var int Step between consecutive questions */
        public readonly int $increment = 10,
        /** @var int Number of digits to pad the prefix to */
        public readonly int $padding = 4,
        /** @var string One of the SCOPE_* constants */
        public readonly string $scope = self::SCOPE_PER_QUIZ,
        /** @var bool Whether to strip an existing NNNN_ prefix before applying the new one */
        public readonly bool $stripprefix = true,
        /** @var bool Whether random slots consume a number even though they are never renamed */
        public readonly bool $reserverandom = false,
    ) {
        if ($startnumber < 0 || $startnumber > self::MAX_START) {
            throw new \invalid_parameter_exception('Start number must be between 0 and ' . self::MAX_START);
        }
        if ($increment < self::MIN_INCREMENT || $increment > self::MAX_INCREMENT) {
            throw new \invalid_parameter_exception('Increment must be between ' . self::MIN_INCREMENT .
                ' and ' . self::MAX_INCREMENT);
        }
        if ($padding < self::MIN_PADDING || $padding > self::MAX_PADDING) {
            throw new \invalid_parameter_exception('Padding must be between ' . self::MIN_PADDING .
                ' and ' . self::MAX_PADDING);
        }
        if ($scope !== self::SCOPE_PER_QUIZ && $scope !== self::SCOPE_CONTINUOUS) {
            throw new \invalid_parameter_exception('Unknown numbering scope: ' . $scope);
        }
    }

    /**
     * Build settings from submitted form data.
     *
     * @param \stdClass $data Data returned by renumber_form::get_data().
     * @return self
     */
    public static function from_form_data(\stdClass $data): self {
        return new self(
            (int)$data->startnumber,
            (int)$data->increment,
            (int)$data->padding,
            (string)($data->scope ?? self::SCOPE_PER_QUIZ),
            !empty($data->stripprefix),
            !empty($data->reserverandom)
        );
    }

    /**
     * The options to start from, as configured by the site administrator.
     *
     * @return self
     */
    public static function from_site_defaults(): self {
        $config = get_config('local_quizrenumber');
        return new self(
            (int)($config->defaultstartnumber ?? 10),
            (int)($config->defaultincrement ?? 10),
            (int)($config->defaultpadding ?? 4)
        );
    }

    /**
     * The scope options, for a form select.
     *
     * @return array Scope value => translated label.
     */
    public static function get_scope_options(): array {
        return [
            self::SCOPE_PER_QUIZ => get_string('scopeperquiz', 'local_quizrenumber'),
            self::SCOPE_CONTINUOUS => get_string('scopecontinuous', 'local_quizrenumber'),
        ];
    }
}
