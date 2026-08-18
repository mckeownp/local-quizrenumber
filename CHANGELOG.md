# Changelog

All notable changes to local_quizrenumber are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-08-18

First release.

### Added

- Renumber the questions in one or more quizzes so their names carry a zero-padded,
  incrementing prefix.
- Quiz selection step listing every quiz in the course with its fixed and random question
  counts, a select-all control, and a warning for quizzes that are entirely random.
- Live preview table showing current name, stripped name and new name, recomputed in the
  browser as the options change.
- Configurable start number, increment (capped at 100), padding width, and a numbering
  scope of either restart-per-quiz or continuous across all selected quizzes.
- Prefix stripping, on by default, so re-running the tool never stacks prefixes.
- Optional reservation of numbers for random slots, off by default.
- "Used elsewhere" badge with a tooltip naming the first few quizzes and courses a shared
  question appears in, summarising the remainder as "… and N others", and a mandatory
  confirmation when any selected question is shared. The badge links through to a page
  listing every quiz that uses the question, with a way back to the preview.
- Colour coded question counts on the quiz selection step: green for all fixed, orange for
  mixed fixed and random, red for all random.
- Results page listing every rename performed and every slot skipped, with the reason.
- Compatibility layer covering the Moodle 4.5 and 5.0+ question bank models, selected at
  runtime, so the rest of the plugin is version-blind.
- `local/quizrenumber:manage` capability, checked alongside `moodle/question:editall` in
  the context that owns each question.
- `\local_quizrenumber\event\questions_renumbered` event carrying the full old-to-new name
  mapping, which is the plugin's audit trail.
- Site-level defaults for start number, increment and padding.
- PHPUnit and Behat test suites, including one test class per compatibility implementation.

### Requirements

- Moodle 4.5 LTS is the minimum. Earlier releases are all end of life, and the CI matrix
  cannot reach them: Moodle 4.0–4.4 top out at PHP 8.0 while the lowest tested PHP is 8.1.
- PHP 8.1 or later, which Moodle 4.5 requires anyway.

### Compatibility notes

- Random slots are detected through both markers core has used: `qtype = 'random'` on
  Moodle 4.5 to 5.1, and the explicit `random` flag introduced in 5.2, where `qtype` is
  null for random slots. Matching on `qtype` alone made 5.2 report random slots as missing
  questions.

### Notes

- Random slots are never renamed. They resolve to a category rather than a single question,
  so there is no one name to change. They appear in the preview, badged and greyed.
- Renaming updates the question version each slot already references rather than creating a
  new version, to keep version history readable during bulk renames.
