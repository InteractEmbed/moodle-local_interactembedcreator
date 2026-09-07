# InteractEmbed Creator 0.9.0-rc14 validation report

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
- PHPUnit plugin suite: 13 tests, 93 assertions, all passed.
- Backup/restore scenario: 1 test, 11 assertions, all passed.

The backup/restore scenario creates a course-scoped project, adds a source
asset, publishes the project, creates a real Moodle course backup archive, and
restores it into a new course. It verifies fresh project and publication UUIDs,
mapped database relations, updated JSON identifiers, restored files, and the
canonical publication package hash.

## Release status

The code is technically ready for public CI as 0.9.0-rc14. It remains an RC:
no stable 1.0 tag or Marketplace release is authorised by this report. Final
visual acceptance and the coordinated Activity/Block readiness review remain
the release gate.
