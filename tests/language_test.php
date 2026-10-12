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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_interactembedcreator;

/**
 * Verifies that every declared capability has an English description.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class language_test extends \advanced_testcase {
    /**
     * Checks the actual capability declarations against the source language.
     */
    public function test_all_capabilities_have_descriptions(): void {
        $capabilities = [];
        require(__DIR__ . '/../db/access.php');
        $string = [];
        require(__DIR__ . '/../lang/en/local_interactembedcreator.php');
        $this->assertCount(7, $capabilities);
        foreach (array_keys($capabilities) as $capability) {
            $key = substr($capability, strlen('local/'));
            $this->assertArrayHasKey($key, $string);
            $this->assertNotEmpty($string[$key]);
        }
    }
}
