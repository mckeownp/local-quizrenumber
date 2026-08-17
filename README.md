# local_quizrenumber — Renumber quiz questions

A Moodle `local` plugin that renames the questions in selected quizzes so each name carries
a zero-padded, incrementing prefix (`0010_first_question`, `0020_next_question`, …). This
makes questions sort predictably in the question bank. Any prefix the plugin previously
wrote is stripped before the new one is applied, so re-running it never stacks prefixes.

## Requirements

- Moodle 4.0 or later (`$plugin->requires = 2022041900`). Installation is blocked on older sites.
- `mod_quiz`.

## Installing

Copy this directory to `local/quizrenumber` in your Moodle root, then visit
Site administration → Notifications, or run `php admin/cli/upgrade.php`.

## Using it

1. Go to a course → Course administration → **Renumber quiz questions**.
2. Tick the quizzes to renumber. Each is labelled with its fixed/random question counts.
3. Set the start number, increment, padding width and numbering scope. The preview table
   updates live as you type.
4. Confirm and apply. The results page lists every rename and everything that was skipped.

Requires the `local/quizrenumber:manage` capability in the course, plus
`moodle/question:editall` in the context that owns each question. Questions you cannot edit
are listed as skipped rather than silently dropped.

## What it deliberately does not do

- **Random slots are never renamed.** A random slot resolves to a category, not to one
  question, so there is no single name to change. Renaming everything in the category would
  be a much larger and riskier operation. Random slots appear in the preview, greyed out and
  badged, and by default do not consume a number. The *Reserve numbers for random slots*
  option changes that if you would rather the numbers line up with slot positions.
- **It does not create new question versions.** The name is updated on the version each slot
  already points at, so version history stays readable and every quiz using the question
  sees the new name immediately.

## Shared questions

Question names live in the shared question bank. Renaming affects every quiz that uses the
question, including quizzes in other courses. The preview badges shared questions with a
tooltip naming where else they are used, and a confirmation checkbox becomes mandatory
whenever any selected question is shared.

## Architecture

The question bank storage model changed between Moodle 4.x and 5.0, so all version-specific
knowledge is confined to `classes/compat/`:

| File | Responsibility |
| --- | --- |
| `question_source_interface.php` | The only contract the rest of the plugin knows |
| `question_source_v4.php` | Moodle 4.0–4.5: course-context category trees |
| `question_source_v5.php` | Moodle 5.0+: `mod_qbank` module contexts, multiple banks per course |
| `question_source_factory.php` | Picks an implementation from `$CFG->branch` |

Everything else — `renumber_service`, the forms, the output layer — talks only to the
interface, never to `$DB` for question bank data and never to `$CFG->branch`. Supporting a
future Moodle version should mean adding one implementation and teaching the factory about
it, and changing nothing else.

`renumber_service` is pure: given slots and settings it returns a plan, which makes the
numbering rules testable against a stub on any Moodle version.

## Development

Run the tests from your Moodle root:

```bash
vendor/bin/phpunit --testsuite local_quizrenumber_testsuite
vendor/bin/behat --tags=@local_quizrenumber
```

Coding standard:

```bash
vendor/bin/phpcs --standard=moodle local/quizrenumber
```

The built AMD modules in `amd/build/` are committed, as Moodle plugins are expected to ship
them. After editing anything in `amd/src/`, rebuild from a Moodle root with Node 22:

```bash
npx grunt amd --root=local/quizrenumber
```

## Status

Verified on two Moodle versions, both PHP 8.3 / PostgreSQL 15, with phpcs clean on each:

| Site | PHPUnit | Behat |
| --- | --- | --- |
| Moodle 4.5.13 (branch 405) | 47/47 | 8/8 |
| Moodle 5.1.5+ (branch 501) | 46 passed, 11 skipped | 8/8 |

The skips are `question_source_v4_test` opting out on a 5.x site, and vice versa: each
compatibility implementation is tested only against the question bank era it targets. The
Behat feature is shared and unmodified between the two.

Moodle 5.0 itself has not been tested — the 5.x site available was 5.1. Since the factory
selects `question_source_v5` for any branch >= 500 and 5.1 keeps the `mod_qbank` model
introduced in 5.0, 5.0 is expected to work, but that is inference rather than a test result.

### A note on PHPUnit deprecations

On Moodle 5.x, PHPUnit 11 reports 10 deprecations for `@covers` and `@dataProvider` in
doc-comments. These are kept deliberately: the plugin supports Moodle 4.0, which ships
PHPUnit 9, and that version does not read the attribute equivalents — switching would break
the tests on the older half of the supported range. They do not fail `--fail-on-warning`.
Revisit when the minimum supported Moodle version ships PHPUnit 10 or later.

## Licence

GPL v3 or later.
