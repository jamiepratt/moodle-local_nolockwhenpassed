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

namespace local_nolockwhenpassed\privacy;

use advanced_testcase;
use core_privacy\local\metadata\null_provider;

/**
 * Tests the grade-unlocking privacy declaration.
 *
 * @package    local_nolockwhenpassed
 * @copyright  2026 Jamie Pratt
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends advanced_testcase {
    /**
     * The plugin declares that grades are stored only by Moodle core.
     */
    public function test_declares_no_plugin_owned_personal_data(): void {
        $this->assertTrue(is_subclass_of(provider::class, null_provider::class));
        $this->assertSame('privacy:metadata', provider::get_reason());
        $this->assertNotEmpty(get_string(provider::get_reason(), 'local_nolockwhenpassed'));
    }
}
