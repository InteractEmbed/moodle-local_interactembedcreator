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

namespace local_interactembedcreator;

use local_interactembedcreator\local\project_lifecycle;

/**
 * Moodle lifecycle event observers.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Removes projects that belonged to a deleted course.
     *
     * @param \core\event\course_deleted $event Course deletion event.
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        project_lifecycle::delete_context_projects($event->contextid);
    }

    /**
     * Removes projects that belonged to a deleted course category.
     *
     * @param \core\event\course_category_deleted $event Category deletion event.
     * @return void
     */
    public static function course_category_deleted(\core\event\course_category_deleted $event): void {
        project_lifecycle::delete_context_projects($event->contextid);
    }
}
