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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unlock passing quiz grades.
 *
 * @package   local_nolockwhenpassed
 * @copyright 2017 James Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nolockwhenpassed;

/**
 * Event observer for submitted quiz attempts.
 *
 * @package   local_nolockwhenpassed
 * @copyright 2017 James Pratt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Unlock a passing quiz grade after an attempt is submitted.
     *
     * @param \mod_quiz\event\attempt_submitted $event The submitted attempt event.
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
        $quiz    = $event->get_record_snapshot('quiz', $attempt->quiz);

        $gradefraction = $attempt->sumgrades / $quiz->sumgrades;

        if ($gradefraction >= 0.8) { // Is the new grade 80% or better?
            // Any existing grades for this user?
            $gradeitem = new \grade_item(['courseid' => $quiz->course, 'itemtype' => 'mod',
                                                'itemmodule' => 'quiz', 'iteminstance' => $quiz->id]);
            $grade = new \grade_grade(['itemid' => $gradeitem->id, 'userid' => $attempt->userid]);
            // Did we find a grade for this user? If not nothing to do.
            if (isset($grade->id)) {
                if ($grade->is_locked() || $grade->is_overridden()) {
                    // Turn overridden and locked off.
                    $grade->set_overridden(false);
                    $grade->set_locked(0);
                    $grade->update('local_nolockwhenpassed');
                }
            }
        }
    }
}
