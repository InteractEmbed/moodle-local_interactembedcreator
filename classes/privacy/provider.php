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

namespace local_interactembedcreator\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;

/**
 * Privacy provider for authored projects and revision history.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_interactembedcreator_project', [
            'ownerid' => 'privacy:metadata:project:ownerid',
            'name' => 'privacy:metadata:project:name',
            'timemodified' => 'privacy:metadata:project:timemodified',
        ], 'privacy:metadata:project');
        $collection->add_database_table('local_interactembedcreator_revision', [
            'authorid' => 'privacy:metadata:revision:authorid',
            'contentjson' => 'privacy:metadata:revision:contentjson',
        ], 'privacy:metadata:revision');
        $collection->add_database_table('local_interactembedcreator_publication', [
            'createdby' => 'privacy:metadata:publication:createdby',
            'timecreated' => 'privacy:metadata:publication:timecreated',
        ], 'privacy:metadata:publication');
        return $collection;
    }

    /**
     * Finds contexts containing user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT DISTINCT contextid FROM {local_interactembedcreator_project} WHERE ownerid = :ownerid',
            ['ownerid' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT DISTINCT p.contextid
               FROM {local_interactembedcreator_publication} pub
               JOIN {local_interactembedcreator_project} p ON p.id = pub.projectid
              WHERE pub.createdby = :createdby',
            ['createdby' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT DISTINCT p.contextid
               FROM {local_interactembedcreator_revision} r
               JOIN {local_interactembedcreator_project} p ON p.id = r.projectid
              WHERE r.authorid = :authorid',
            ['authorid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Adds users with data in a context to the supplied list.
     *
     * @param \core_privacy\local\request\userlist $userlist
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
        $contextid = $userlist->get_context()->id;
        $userlist->add_from_sql(
            'ownerid',
            'SELECT ownerid FROM {local_interactembedcreator_project} WHERE contextid = :contextid',
            ['contextid' => $contextid]
        );
        $userlist->add_from_sql(
            'authorid',
            'SELECT r.authorid
               FROM {local_interactembedcreator_revision} r
               JOIN {local_interactembedcreator_project} p ON p.id = r.projectid
              WHERE p.contextid = :contextid',
            ['contextid' => $contextid]
        );
        $userlist->add_from_sql(
            'createdby',
            'SELECT pub.createdby
               FROM {local_interactembedcreator_publication} pub
               JOIN {local_interactembedcreator_project} p ON p.id = pub.projectid
              WHERE p.contextid = :contextid',
            ['contextid' => $contextid]
        );
    }

    /**
     * Exports projects owned or revised by the approved user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $projects = $DB->get_records('local_interactembedcreator_project', ['contextid' => $context->id, 'ownerid' => $userid]);
            foreach ($projects as $project) {
                $data = (object) [
                    'name' => $project->name,
                    'description' => $project->description,
                    'status' => $project->status,
                    'timecreated' => transform::datetime($project->timecreated),
                    'timemodified' => transform::datetime($project->timemodified),
                    'revisions' => array_values($DB->get_records('local_interactembedcreator_revision', [
                        'projectid' => $project->id,
                    ], 'revisionno ASC')),
                ];
                \core_privacy\local\request\writer::with_context($context)->export_data([
                    get_string('pluginname', 'local_interactembedcreator'),
                    format_string($project->name),
                ], $data);
                $path = [get_string('pluginname', 'local_interactembedcreator'), format_string($project->name)];
                $writer = \core_privacy\local\request\writer::with_context($context);
                $writer->export_area_files($path, 'local_interactembedcreator', 'asset', $project->id);
                $publications = $DB->get_records('local_interactembedcreator_publication', ['projectid' => $project->id]);
                foreach ($publications as $publication) {
                    if ((int) $publication->createdby === (int) $userid) {
                        $writer->export_area_files(
                            array_merge($path, [get_string('publications', 'local_interactembedcreator'),
                                (string) $publication->publicationno]),
                            'local_interactembedcreator',
                            'publication',
                            $publication->id
                        );
                    }
                }
            }
            $authoredrevisions = $DB->get_records_sql(
                'SELECT r.*, p.name AS projectname
                   FROM {local_interactembedcreator_revision} r
                   JOIN {local_interactembedcreator_project} p ON p.id = r.projectid
                  WHERE p.contextid = :contextid AND r.authorid = :authorid AND p.ownerid <> :ownerid',
                ['contextid' => $context->id, 'authorid' => $userid, 'ownerid' => $userid]
            );
            foreach ($authoredrevisions as $revision) {
                \core_privacy\local\request\writer::with_context($context)->export_data([
                    get_string('pluginname', 'local_interactembedcreator'),
                    get_string('privacycontributions', 'local_interactembedcreator'),
                    format_string($revision->projectname),
                    get_string('revision', 'local_interactembedcreator') . ' ' . $revision->revisionno,
                ], (object) [
                    'contentjson' => $revision->contentjson,
                    'revisiontype' => $revision->revisiontype,
                    'timecreated' => transform::datetime($revision->timecreated),
                ]);
            }
            $createdpublications = $DB->get_records_sql(
                'SELECT pub.*, p.name AS projectname
                   FROM {local_interactembedcreator_publication} pub
                   JOIN {local_interactembedcreator_project} p ON p.id = pub.projectid
                  WHERE p.contextid = :contextid AND pub.createdby = :createdby AND p.ownerid <> :ownerid',
                ['contextid' => $context->id, 'createdby' => $userid, 'ownerid' => $userid]
            );
            foreach ($createdpublications as $publication) {
                $path = [
                    get_string('pluginname', 'local_interactembedcreator'),
                    get_string('privacycontributions', 'local_interactembedcreator'),
                    format_string($publication->projectname),
                    get_string('publicationnumber', 'local_interactembedcreator') . ' ' . $publication->publicationno,
                ];
                $writer = \core_privacy\local\request\writer::with_context($context);
                $writer->export_data($path, (object) [
                    'status' => $publication->status,
                    'packagehash' => $publication->packagehash,
                    'engineversion' => $publication->engineversion,
                    'timecreated' => transform::datetime($publication->timecreated),
                ]);
                $writer->export_area_files(
                    $path,
                    'local_interactembedcreator',
                    'publication',
                    $publication->id
                );
            }
        }
    }

    /**
     * Deletes all plugin data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        self::delete_projects($context->id, null);
    }

    /**
     * Deletes approved user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contextids() as $contextid) {
            self::delete_projects($contextid, $contextlist->get_user()->id);
        }
    }

    /**
     * Deletes data for users in one context.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::delete_projects($userlist->get_context()->id, $userid);
        }
    }

    /**
     * Deletes project graphs and their files.
     *
     * @param int $contextid
     * @param int|null $ownerid
     */
    private static function delete_projects(int $contextid, ?int $ownerid): void {
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
        $publications = $DB->get_records_select('local_interactembedcreator_publication', "projectid {$insql}", $params, '', 'id');
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
