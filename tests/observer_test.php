<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_nolockwhenpassed;

use context_module;
use grade_grade;
use grade_item;
use mod_quiz\event\attempt_submitted;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests submitted-attempt grade unlocking.
 *
 * @package   local_nolockwhenpassed
 * @copyright 2026 Jamie Pratt
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    /**
     * A grade at the passing boundary becomes editable again.
     */
    public function test_exactly_eighty_percent_clears_lock_and_override(): void {
        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(8.0, 10.0, true, true);

        observer::attempt_submitted($event);

        $grade->update_from_db();
        $this->assertFalse($grade->is_locked());
        $this->assertFalse($grade->is_overridden());
    }

    /**
     * A grade below the passing boundary remains protected.
     */
    public function test_below_eighty_percent_preserves_lock_and_override(): void {
        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(7.99, 10.0, true, true);

        observer::attempt_submitted($event);

        $grade->update_from_db();
        $this->assertTrue($grade->is_locked());
        $this->assertTrue($grade->is_overridden());
    }

    /**
     * Passing clears either protection when only one is present.
     *
     * @param bool $locked Whether the grade starts locked.
     * @param bool $overridden Whether the grade starts overridden.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('individual_protection_provider')]
    public function test_passing_clears_each_individual_protection(bool $locked, bool $overridden): void {
        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(10.0, 10.0, $locked, $overridden);

        observer::attempt_submitted($event);

        $grade->update_from_db();
        $this->assertFalse($grade->is_locked());
        $this->assertFalse($grade->is_overridden());
    }

    /**
     * Protection states exercised independently.
     *
     * @return array<string, array{0: bool, 1: bool}>
     */
    public static function individual_protection_provider(): array {
        return [
            'locked only' => [true, false],
            'overridden only' => [false, true],
            'already editable' => [false, false],
        ];
    }

    /**
     * A passing attempt without a grade does not create grade state.
     */
    public function test_passing_attempt_without_grade_is_ignored(): void {
        global $DB;

        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(9.0, 10.0, false, false);
        $DB->delete_records('grade_grades', ['id' => $grade->id]);

        observer::attempt_submitted($event);

        $this->assertFalse($DB->record_exists('grade_grades', [
            'itemid' => $grade->itemid,
            'userid' => $grade->userid,
        ]));
    }

    /**
     * Create a submitted-attempt event and optional grade state.
     *
     * @param float $attemptsum Attempt points earned.
     * @param float $quizsum Maximum attempt points.
     * @param bool $locked Whether the grade starts locked.
     * @param bool $overridden Whether the grade starts overridden.
     * @return array{0: attempt_submitted, 1: grade_grade}
     */
    private function create_attempt_event(
        float $attemptsum,
        float $quizsum,
        bool $locked,
        bool $overridden,
    ): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'grade' => 100.0,
            'sumgrades' => $quizsum,
        ]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        $gradeitem = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
        ]);
        grade_update(
            'mod/quiz',
            $course->id,
            'mod',
            'quiz',
            $quiz->id,
            0,
            ['userid' => $user->id, 'rawgrade' => 80.0],
        );
        $grade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $user->id]);
        $grade->set_overridden($overridden, false);
        $grade->set_locked($locked, false, false);

        $attempt = (object) [
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'uniqueid' => 0,
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => time() - 60,
            'timefinish' => time(),
            'timemodified' => time(),
            'timemodifiedoffline' => 0,
            'timecheckstate' => null,
            'sumgrades' => $attemptsum,
            'attempt' => 1,
            'gradednotificationsenttime' => null,
        ];
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

        $event = attempt_submitted::create([
            'context' => context_module::instance($cm->id),
            'objectid' => $attempt->id,
            'relateduserid' => $user->id,
            'other' => [
                'submitterid' => $user->id,
                'quizid' => $quiz->id,
            ],
        ]);
        $event->add_record_snapshot('quiz_attempts', $attempt);
        $event->add_record_snapshot('quiz', $quiz);

        return [$event, $grade];
    }
}
