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
use mod_quiz\event\attempt_graded;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests graded attempts for quizzes without gradeable points.
 *
 * @package   local_nolockwhenpassed
 * @copyright 2026 Jamie Pratt
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class zero_sumgrades_test extends \advanced_testcase {
    /**
     * A zero-point quiz leaves protected grade state unchanged.
     */
    public function test_zero_maximum_preserves_grade_protection(): void {
        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(0.0, 0.0);

        observer::attempt_graded($event);

        $grade->update_from_db();
        $this->assertTrue($grade->is_locked());
        $this->assertTrue($grade->is_overridden());
    }

    /**
     * An invalid negative quiz maximum also leaves protected grade state unchanged.
     */
    public function test_negative_maximum_preserves_grade_protection(): void {
        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(0.0, -1.0);

        observer::attempt_graded($event);

        $grade->update_from_db();
        $this->assertTrue($grade->is_locked());
        $this->assertTrue($grade->is_overridden());
    }

    /**
     * A positive quiz maximum still allows a passing grade to become editable.
     */
    public function test_positive_maximum_unlocks_passing_grade(): void {
        $this->resetAfterTest();
        [$event, $grade] = $this->create_attempt_event(0.8, 1.0);

        observer::attempt_graded($event);

        $grade->update_from_db();
        $this->assertFalse($grade->is_locked());
        $this->assertFalse($grade->is_overridden());
    }

    /**
     * Create a graded-attempt event and protected grade.
     *
     * @param float $attemptsum Attempt points earned.
     * @param float $quizsum Maximum attempt points.
     * @return array{0: attempt_graded, 1: grade_grade}
     */
    private function create_attempt_event(float $attemptsum, float $quizsum): array {
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
        $grade->set_overridden(true, false);
        $grade->set_locked(true, false, false);

        $attempt = (object) [
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'uniqueid' => 0,
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => 1699999940,
            'timefinish' => 1700000000,
            'timemodified' => 1700000000,
            'timemodifiedoffline' => 0,
            'timecheckstate' => null,
            'sumgrades' => $attemptsum,
            'attempt' => 1,
            'gradednotificationsenttime' => null,
        ];
        $attempt->id = $DB->insert_record('quiz_attempts', $attempt);

        $event = attempt_graded::create([
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
