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

namespace local_interactembedcreator\local;

/**
 * Deletes Creator project graphs when their Moodle owner context is removed.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class project_lifecycle {
    /**
     * Deletes projects, revisions, publications, and files in one context.
     *
     * @param int $contextid Moodle context ID.
     * @param int|null $ownerid Optional project owner restriction.
     * @return void
     */
    public static function delete_context_projects(int $contextid, ?int $ownerid = null): void {
        global $DB;

        $conditions = ['contextid' => $contextid];
        if ($ownerid !== null) {
            $conditions['ownerid'] = $ownerid;
        }
        $projects = $DB->get_records('local_interactembedcreator_project', $conditions, '', 'id');
        if (!$projects) {
            return;
        }

        $projectids = array_keys($projects);
        [$insql, $params] = $DB->get_in_or_equal($projectids, SQL_PARAMS_NAMED);
        $publications = $DB->get_records_select(
            'local_interactembedcreator_publication',
            "projectid {$insql}",
            $params,
            '',
            'id'
        );

        $DB->delete_records_select('local_interactembedcreator_publication', "projectid {$insql}", $params);
        $DB->delete_records_select('local_interactembedcreator_revision', "projectid {$insql}", $params);
        $DB->delete_records_select('local_interactembedcreator_project', "id {$insql}", $params);

        $fs = get_file_storage();
        foreach ($projectids as $projectid) {
            $fs->delete_area_files($contextid, 'local_interactembedcreator', 'asset', $projectid);
            $fs->delete_area_files($contextid, 'local_interactembedcreator', 'thumbnail', $projectid);
        }
        foreach (array_keys($publications) as $publicationid) {
            $fs->delete_area_files($contextid, 'local_interactembedcreator', 'publication', $publicationid);
        }
    }
}
