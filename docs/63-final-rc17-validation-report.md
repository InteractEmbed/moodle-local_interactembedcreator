# InteractEmbed Creator 0.9.0-rc17 validation report

Date: 2026-09-07

## Scope

This release candidate adds native Moodle backup and restore support for
course-scoped Creator projects. The validation covered the project record,
revision history, source assets, immutable publication package, identifier
remapping, and package integrity after restoration into a new course.

## Environment

- Moodle 5.2.1+ (Build 20260807)
- PHP 8.3.30
- MySQLi 8.4.11
- Isolated PHPUnit database prefix and dataroot

## Automated results

- PHP syntax: all plugin PHP files passed.
- Moodle PHP_CodeSniffer standard: passed with no findings.
- PHPUnit plugin suite: 15 tests, 100 assertions, all passed.
- Backup/restore scenario: 1 test, 11 assertions, all passed.

The backup/restore scenario creates a course-scoped project, adds a source
asset, publishes the project, creates a real Moodle course backup archive, and
restores it into a new course. It verifies fresh project and publication UUIDs,
mapped database relations, updated JSON identifiers, restored files, and the
canonical publication package hash.

## Public CI baseline

The rc17 GitHub Actions run 34142247632 passed all six jobs for Moodle 4.5 through 5.2,
PHP 8.1 through 8.4, MariaDB and PostgreSQL. Each job installed the Activity
and Block dependencies before running PHP lint, Moodle CodeSniffer, PHPDoc,
plugin validation, savepoints, Mustache, Grunt and PHPUnit.

## Release status

## Visual acceptance correction

The IOMAD 4.5 browser recipe uncovered and corrected a system-library preview
failure: Moodle passes a null course record to pluginfile callbacks outside a
course context. RC15 accepts that documented callback state. A new site-library
project was then created, edited with two scenes and a quiz, saved, checked by
the integrated accessibility checker, published, previewed and navigated in the
generated player. The publication opened successfully and the quiz and scene
navigation worked. The regression suite now contains 14 tests and 94 assertions.

## Lifecycle correction

The coordinated Activity cleanup audit found course-scoped Creator records
remaining after their Moodle contexts were deleted. RC16 registers Moodle
course and category deletion observers, removes the full related project graph
and Files API areas, and reuses the same cleanup service for privacy erasure.
Integration coverage now deletes real Moodle courses and categories and checks
that projects, revisions, and publications are all removed. The complete rc17
suite passes 15 tests and 100 assertions on Moodle 5.2.

The code is technically validated as 0.9.0-rc17. The Activity repeated its
complete backup, restore and course-deletion scenario on Moodle 5.2 and
IOMAD/Moodle 4.5, finding zero orphaned Creator projects. The Block's final
integrated recipe is also green. The three pre-1.0 candidates are therefore
ready for the human 1.0 release decision. No stable 1.0 tag or Marketplace
release is authorised by this report.
