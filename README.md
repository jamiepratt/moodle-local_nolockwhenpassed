# Unlock passing quiz grades

`local_nolockwhenpassed` lets Moodle update a learner's protected quiz grade
when a newly automatically graded attempt scores at least 80%. It is intended
for sites where reaching that threshold should return control of the quiz grade
to Moodle's normal calculation.

A **locked grade** prevents automatic changes. An **overridden grade** uses a
manually supplied result instead of the result calculated by the quiz. These
protections can keep an old result in the gradebook even after a better attempt.
This plugin removes the individual learner's lock and override so Moodle can
refresh that quiz grade.

For example, a learner has a protected grade of 50/100, then earns 8 out of 10
question points in a new automatically graded attempt. The plugin removes the
individual protection. With highest-attempt grading, no better previous attempt,
and no other protection, Moodle can refresh the grade to 80/100. With average,
first-attempt or last-attempt grading, the result can differ. The plugin does not
award a pass or write the latest attempt score directly into the final grade.

This affects **gradebook records**. It does not unlock courses, activities or
quiz attempts, grant more attempts, or bypass access restrictions.

## Requirements and scope

This README describes the maintained `TEST_AUTOEHS_52+` branch:

- Moodle 5.2; `version.php` requires core build `2026042000` or later and declares
  plugin version `2026081900`. The minimum build check is not a promise of
  compatibility with every later Moodle release.
- PHP 8.3 is Moodle 5.2's minimum. Shipmate's required validation platform is
  PHP 8.4 and MySQL 8.4; use the matching Moodle requirements for extensions
  and other dependencies.
- Moodle's bundled Quiz, Gradebook and event APIs. No additional plugin
  dependency is declared.

There is no settings page, course opt-in or quiz selection. While installed,
the observer applies site-wide to qualifying quiz events. The threshold is
hard-coded at 80%, independently of Moodle's configurable **Grade to pass**.
A course using a different pass mark still gets this 80% unlocking rule.

Deliberately applied manual overrides are also removed by qualifying events.
Use this plugin only where that behavior matches the site's grading policy;
it cannot exempt a particular learner, course or teacher's override.

## Trigger and algorithm

[`db/events.php`](db/events.php) registers only
`mod_quiz\event\attempt_graded`. In the maintained Moodle core, automatic
submission grading persists the attempt's points, recomputes its quiz result,
then emits that event for non-preview attempts. Submission alone does not
trigger this plugin.

[`classes/observer.php`](classes/observer.php) performs these steps:

1. Read the event's `quiz_attempts` and `quiz` record snapshots.
2. Return without changes if the quiz's maximum question points
   (`quiz.sumgrades`) are zero or negative.
3. Divide persisted attempt points (`quiz_attempts.sumgrades`) by those maximum
   points. This uses question points, not the scaled gradebook maximum.
4. For a fraction **greater than or equal to 0.8**, find the quiz grade item and
   the existing grade record for that attempt's learner.
5. If the record exists and Moodle reports it locked or overridden, call
   `grade_grade::set_overridden(false)` followed by `set_locked(0)`. These core
   APIs request a grade refresh. Moodle retains responsibility for calculating
   the result using the quiz's grading method.

The observer does not create a missing learner grade, change attempt answers
or points, or directly assign `rawgrade` or `finalgrade`.

### Boundaries and limitations

| Situation | Behavior |
| --- | --- |
| Attempt below 80% | Individual lock and override remain unchanged. |
| Exactly 80% | Qualifies; no display rounding is used in the comparison. |
| Zero or negative maximum question points | Returns before division or grade changes. |
| No existing learner grade record | Does not create one; normal Moodle grading may do so separately. |
| Grade already unlocked and not overridden | No plugin change, unless Moodle reports a whole-item lock. |
| Manual question grading or quiz regrading | Their separate `attempt_manual_grading_completed` and `attempt_regraded` events are not observed. A later manual/regraded score reaching 80% alone does not trigger this observer. |
| Whole quiz grade item locked | Core `is_locked()` reports this too, so the observer can clear the learner's override/individual lock, but it does not clear the grade item's lock. Refresh can remain blocked. |
| Scheduled locks | The plugin delegates to core. Core clears an elapsed individual lock time when removing an existing individual lock; future individual lock times and grade-item lock schedules remain. The grade can lock again. |

The integration test verifies the ordinary automatic-grading path with an
existing individually locked and overridden grade. Whole-item locks, scheduled
locks, manual grading and regrading are documented from core API/event inspection,
not claimed as plugin integration-test coverage. There is no retrospective scan
of old attempts or plugin repair task. Other plugins and site-specific grading
customizations may introduce additional behavior.

## Installation and verification

For Moodle 5.2's layout, place this repository's contents in
`public/local/nolockwhenpassed` under the Moodle checkout. `version.php` must be
directly inside that directory, not inside another nested repository folder.
Install source from the intended maintained branch or an explicitly selected
commit, then complete Moodle's normal plugin installation through **Site
administration > Notifications**.

For Shipmate source assembly, environment setup, upgrade commands and deployment
checkpoints, follow the authoritative
[Shipmate Moodle root README](https://github.com/kbanning/moodle/blob/MINIMAL_AUTOEHS_52%2B/README.md).
Its locked-source installation rules take precedence over simply following a
moving plugin branch. Installing files alone does not register a new observer.

Under **Site administration > Plugins > Plugins overview**, verify that
**Unlock passing quiz grades** (`local_nolockwhenpassed`) is installed without
an outstanding upgrade. There are no further plugin settings.

### Small administrator check

Use a disposable test site and synthetic learner:

1. Create a quiz with automatically graded questions, 10 available question
   points, a gradebook maximum of 100 and highest-attempt grading. Allow enough
   attempts for this check. Leave the whole quiz grade item and lock schedules
   unprotected.
2. Establish an existing learner grade, then edit that learner's quiz grade in
   the gradebook to 50 and apply an individual lock and override.
3. Submit a new attempt scoring 8/10. Allow automatic grading to finish.
4. Check that the individual lock and override are cleared and the grade is
   80/100, provided there is no higher previous attempt.
5. With a fresh learner or reset fixture, repeat at 7/10. The protected grade
   and its flags should remain unchanged.

Do not use teacher preview attempts for this check: core does not emit the
observed event for previews.

## Upgrades and removal

Before a source upgrade, preserve the site's normal recovery checkpoint, check
the target branch's requirements, and follow Moodle's upgrade and cache-refresh
procedure. Use the linked superproject runbook for Shipmate. A README-only change
requires no plugin version bump or database migration.

To remove the plugin, use **Plugins overview > Uninstall** and complete Moodle's
removal procedure, including removing its source directory so Moodle does not
offer to reinstall it. Coordinate source removal with the Shipmate manifest owner
for an assembled installation.

Uninstalling stops future plugin handling. It does not restore previously
removed locks or overrides, reconstruct manually supplied grades, or undo core
grade refreshes. There is no plugin-owned history or rollback routine; review
necessary grade corrections through Moodle's gradebook and the site's recovery
records.

## Troubleshooting

If a qualifying attempt does not change the displayed grade:

- Confirm automatic grading has finished. A submitted attempt can still have
  no persisted points; submission is a separate lifecycle stage.
- Check the actual question-point fraction, including the denominator. A rounded
  display, the configured grade-to-pass value or a manual regrade is not the
  plugin's trigger.
- Inspect both the learner's grade and the entire quiz grade item for locks,
  scheduled locks and overrides. Removing individual flags does not remove
  item-level protection.
- Check the quiz's grading method and previous attempts. An average or first
  attempt result need not equal the newest qualifying score.
- Confirm installation/upgrade completion, the installed source revision and
  observer registration. Investigate Moodle quiz reports, grade history, event
  logs and administrator debugging output for grading failures or other plugins.

The observer does not report refresh failures through a plugin UI and does not
promise an immediate visible grade change in every protected-grade scenario.

## Development and tests

This is a Moodle plugin, not a standalone PHP application. Development requires
a compatible Moodle checkout, its dependencies and an initialized disposable
Moodle PHPUnit database/data directory. Never point tests at a live site.
For Shipmate, use the linked root README's Docker prerequisites and native test
setup. From that configured superproject root, run:

```sh
tools/testing/run.sh phpunit --testsuite local_nolockwhenpassed_testsuite --fail-on-skipped --fail-on-warning --fail-on-risky
```

After source changes, follow that runbook's synchronization requirements before
running tests. In a separately initialized native Moodle PHPUnit installation,
the equivalent suite selection from its checkout root is:

```sh
vendor/bin/phpunit --testsuite local_nolockwhenpassed_testsuite
```

| Source | Responsibility or evidence |
| --- | --- |
| `version.php` | Component identity and minimum Moodle build. |
| `db/events.php`, `eventincludes.php` | Event registration and Quiz/Gradebook dependencies. |
| `classes/observer.php` | Threshold and individual grade protection changes. |
| `lang/en/local_nolockwhenpassed.php` | Display name and privacy explanation. |
| `classes/privacy/provider.php` | Moodle Privacy API null provider. |
| `tests/observer_test.php` | Exact 80%, below threshold, individual flags, already unprotected and absent learner grades. |
| `tests/zero_sumgrades_test.php` | Zero/negative maxima and positive-maximum control. |
| `tests/attempt_grading_integration_test.php` | Real submission remains protected until automatic grading persists points; then flags clear and raw/final grades refresh. |
| `tests/privacy/provider_test.php` | Null-provider declaration and translated explanation. |

These tests do not establish compatibility with every Moodle/PHP combination.
For implementation changes, include a focused regression test and use Shipmate's
current custom-PHP checking procedure. Documentation changes should be checked
against both plugin source/tests and the matching core APIs.

## Data handling and privacy

The plugin defines no tables, calls no external services and stores no separate
personal-data collection. It reads learner quiz attempts and changes **core-owned
grade records**, with refresh/history behavior handled by Moodle's grading APIs.
That is a change to personal learning data even though the plugin has no tables.

Its Privacy API `null_provider` supplies the `privacy:metadata` explanation;
it implements no plugin-specific export or erasure. Quiz and core grade data
remain the responsibility of their owning Moodle subsystems/providers. Removing
this plugin is not deletion of those records.

## Maintenance and contributions

Report defects or propose changes through
[plugin issues](https://github.com/jamiepratt/moodle-local_nolockwhenpassed/issues).
Include the plugin commit/branch, Moodle and PHP versions, grading method,
protection state and reproducible steps using synthetic data. Omit learner
identities and private grade records.

Submit focused pull requests against `TEST_AUTOEHS_52+`, describing the behavior
and validation. Discuss grading-policy changes in an issue first. Review the
[maintained branch history](https://github.com/jamiepratt/moodle-local_nolockwhenpassed/commits/TEST_AUTOEHS_52%2B/)
for changes; this repository contains no independent release workflow or stated
support-service guarantee. Shipmate release and operational policy belongs to
the superproject README.

Source headers credit James Pratt (2017), with later work credited to Jamie
Pratt (2026). The plugin is licensed under the **GNU General Public License,
version 3 or later**, as stated in the source headers.
