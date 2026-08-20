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

use grade_grade;
use grade_item;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests grade protection through the public quiz submission lifecycle.
 *
 * @package   local_nolockwhenpassed
 * @copyright 2026 Jamie Pratt
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class attempt_grading_integration_test extends \advanced_testcase {
    /**
     * A passing submission unlocks only after automatic grading persists its result.
     */
    public function test_passing_submission_unlocks_after_grade_is_persisted(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'grade' => 10.0,
            'sumgrades' => 1.0,
            'questionsperpage' => 0,
        ]);

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('shortanswer', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        grade_update(
            'mod/quiz',
            $course->id,
            'mod',
            'quiz',
            $quiz->id,
            0,
            ['userid' => $user->id, 'rawgrade' => 8.0],
        );
        $gradeitem = grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
        ]);
        $grade = grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $user->id]);
        $grade->set_overridden(true, false);
        $grade->set_locked(true, false, false);

        $quizsettings = quiz_settings::create($quiz->id, $user->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizsettings->get_context());
        $quba->set_preferred_behaviour($quizsettings->get_quiz()->preferredbehaviour);

        $time = time();
        $attempt = quiz_create_attempt($quizsettings, 1, false, $time, false, $user->id);
        quiz_start_new_attempt($quizsettings, $quba, $attempt, 1, $time);
        quiz_attempt_save_started($quizsettings, $quba, $attempt);

        $attemptobject = quiz_attempt::create($attempt->id);
        $attemptobject->process_submitted_actions($time, false, [1 => ['answer' => 'frog']]);
        $attemptobject->process_submit($time, false);

        $submittedattempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame(quiz_attempt::SUBMITTED, $submittedattempt->state);
        $this->assertNull($submittedattempt->sumgrades);
        $grade->update_from_db();
        $this->assertTrue($grade->is_locked());
        $this->assertTrue($grade->is_overridden());

        $attemptobject->process_grade_submission($time);

        $finishedattempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame(quiz_attempt::FINISHED, $finishedattempt->state);
        $this->assertEquals(1.0, $finishedattempt->sumgrades);
        $grade->update_from_db();
        $this->assertFalse($grade->is_locked());
        $this->assertFalse($grade->is_overridden());
        $this->assertEquals(10.0, $grade->rawgrade);
        $this->assertEquals(10.0, $grade->finalgrade);
    }
}
