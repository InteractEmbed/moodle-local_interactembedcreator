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

/**
 * Course backup support for InteractEmbed Creator.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds course-scoped Creator projects to Moodle course backups.
 */
class backup_local_interactembedcreator_plugin extends backup_local_plugin {
    /**
     * Defines the Creator data attached to the course connection point.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $projects = new backup_nested_element('projects');
        $project = new backup_nested_element('project', ['id'], [
            'uuid',
            'ownerid',
            'name',
            'description',
            'status',
            'currentrevision',
            'lockversion',
            'defaultlocale',
            'timecreated',
            'timemodified',
        ]);
        $revisions = new backup_nested_element('revisions');
        $revision = new backup_nested_element('revision', ['id'], [
            'revisionno',
            'schemaversion',
            'contentjson',
            'contenthash',
            'revisiontype',
            'message',
            'authorid',
            'timecreated',
        ]);
        $publications = new backup_nested_element('publications');
        $publication = new backup_nested_element('publication', ['id'], [
            'uuid',
            'revisionid',
            'publicationno',
            'status',
            'entrypoint',
            'packagehash',
            'engineversion',
            'createdby',
            'timecreated',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($projects);
        $projects->add_child($project);
        $project->add_child($revisions);
        $revisions->add_child($revision);
        $project->add_child($publications);
        $publications->add_child($publication);

        $project->set_source_table('local_interactembedcreator_project', [
            'contextid' => ['sqlparam' => $this->task->get_contextid()],
        ]);
        $revision->set_source_table('local_interactembedcreator_revision', [
            'projectid' => backup::VAR_PARENTID,
        ]);
        $publication->set_source_table('local_interactembedcreator_publication', [
            'projectid' => backup::VAR_PARENTID,
        ]);

        $project->annotate_ids('user', 'ownerid');
        $revision->annotate_ids('user', 'authorid');
        $publication->annotate_ids('user', 'createdby');
        $project->annotate_files('local_interactembedcreator', 'asset', 'id');
        $project->annotate_files('local_interactembedcreator', 'thumbnail', 'id');
        $publication->annotate_files('local_interactembedcreator', 'publication', 'id');

        return $plugin;
    }
}
