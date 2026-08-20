# Unlock passing quiz grades

## Purpose

`local_nolockwhenpassed` observes automatically graded quiz attempts. After the
attempt score is persisted, a score of at least 80 percent clears lock and
override state from the learner's quiz grade so Moodle can retain the passing
result.

## Requirements

- Moodle 5.2 on PHP 8.3 or another version supported by the matching repository branch.
- Moodle Quiz and Gradebook components.

## Installation

Install at `public/local/nolockwhenpassed`. In the Shipmate distribution, run
`tools/plugins/install.sh` from the superproject root, then run
`php public/admin/cli/upgrade.php --non-interactive`.

## Configuration

There is no settings page. The 80 percent threshold is part of the shipped
business rule. Validate the rule against course grading policy before deployment.

## Development and testing

From a configured Shipmate Moodle checkout, run:

```sh
vendor/bin/phpunit --testsuite local_nolockwhenpassed_testsuite
```

The superproject CI installs the locked plugin source before running Moodle tests.
This repository has no independent release workflow.

## Privacy and external services

The plugin owns no personal-data tables and calls no external service. It reads
quiz attempts and updates grade lock and override fields stored by Moodle core.

## Support

Report defects in the public
[plugin repository](https://github.com/jamiepratt/moodle-local_nolockwhenpassed/issues).

## License

GNU GPL v3 or later, as stated in the source-file headers.
