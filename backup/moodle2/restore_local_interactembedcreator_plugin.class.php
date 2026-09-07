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
 * Course restore support for InteractEmbed Creator.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores course-scoped Creator projects as independent library copies.
 */
class restore_local_interactembedcreator_plugin extends restore_local_plugin {
    /** @var array<int, array{newid: int, uuid: string, currentrevision: int}> Restored project state by old ID. */
    private array $projects = [];

    /** @var array<int, array{uuid: string, projectuuid: string}> Restored publication state by new ID. */
    private array $publications = [];

    /**
     * Defines the Creator paths attached to the course connection point.
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {
        return [
            new restore_path_element(
                'local_interactembedcreator_project',
                $this->get_pathfor('/projects/project')
            ),
            new restore_path_element(
                'local_interactembedcreator_revision',
                $this->get_pathfor('/projects/project/revisions/revision')
            ),
            new restore_path_element(
                'local_interactembedcreator_publication',
                $this->get_pathfor('/projects/project/publications/publication')
            ),
        ];
    }

    /**
     * Restores one project into the destination course context.
     *
     * @param array|stdClass $data Backup record.
     * @return void
     */
    public function process_local_interactembedcreator_project($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = (int) $data->id;
        $oldcurrentrevision = (int) $data->currentrevision;
        unset($data->id);
        $data->uuid = \local_interactembedcreator\local\project_repository::uuid();
        $data->contextid = $this->task->get_contextid();
        $data->ownerid = $this->get_mappingid('user', $data->ownerid, $this->task->get_userid());
        $data->currentrevision = 0;
        $newid = $DB->insert_record('local_interactembedcreator_project', $data);

        $this->set_mapping('local_interactembedcreator_project', $oldid, $newid, true);
        $this->projects[$oldid] = [
            'newid' => $newid,
            'uuid' => $data->uuid,
            'currentrevision' => $oldcurrentrevision,
        ];
    }

    /**
     * Restores one immutable project revision.
     *
     * @param array|stdClass $data Backup record.
     * @return void
     */
    public function process_local_interactembedcreator_revision($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = (int) $data->id;
        $oldprojectid = (int) $this->get_old_parentid('local_interactembedcreator_project');
        unset($data->id);
        $data->projectid = $this->get_mappingid('local_interactembedcreator_project', $oldprojectid);
        $data->authorid = $this->get_mappingid('user', $data->authorid, $this->task->get_userid());

        $document = json_decode($data->contentjson, false, 512, JSON_THROW_ON_ERROR);
        $document->projectId = $this->projects[$oldprojectid]['uuid'];
        $data->contentjson = json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $data->contenthash = hash('sha256', $data->contentjson);
        $newid = $DB->insert_record('local_interactembedcreator_revision', $data);

        $this->set_mapping('local_interactembedcreator_revision', $oldid, $newid);
        if ($oldid === $this->projects[$oldprojectid]['currentrevision']) {
            $DB->set_field(
                'local_interactembedcreator_project',
                'currentrevision',
                $newid,
                ['id' => $data->projectid]
            );
        }
    }

    /**
     * Restores one immutable publication record.
     *
     * @param array|stdClass $data Backup record.
     * @return void
     */
    public function process_local_interactembedcreator_publication($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = (int) $data->id;
        $oldprojectid = (int) $this->get_old_parentid('local_interactembedcreator_project');
        unset($data->id);
        $data->uuid = \local_interactembedcreator\local\project_repository::uuid();
        $data->projectid = $this->get_mappingid('local_interactembedcreator_project', $oldprojectid);
        $data->revisionid = $this->get_mappingid('local_interactembedcreator_revision', $data->revisionid);
        $data->createdby = $this->get_mappingid('user', $data->createdby, $this->task->get_userid());
        $newid = $DB->insert_record('local_interactembedcreator_publication', $data);

        $this->set_mapping('local_interactembedcreator_publication', $oldid, $newid, true);
        $this->publications[$newid] = [
            'uuid' => $data->uuid,
            'projectuuid' => $this->projects[$oldprojectid]['uuid'],
        ];
    }

    /**
     * Restores Moodle files and synchronises identifiers embedded in publication metadata.
     *
     * @return void
     */
    public function after_execute_course(): void {
        $this->add_related_files(
            'local_interactembedcreator',
            'asset',
            'local_interactembedcreator_project'
        );
        $this->add_related_files(
            'local_interactembedcreator',
            'thumbnail',
            'local_interactembedcreator_project'
        );
        $this->add_related_files(
            'local_interactembedcreator',
            'publication',
            'local_interactembedcreator_publication'
        );

        foreach ($this->publications as $publicationid => $state) {
            $this->synchronise_publication($publicationid, $state['uuid'], $state['projectuuid']);
        }
    }

    /**
     * Rewrites restored metadata and refreshes its canonical package hash.
     *
     * @param int $publicationid Restored publication ID.
     * @param string $publicationuuid Restored publication UUID.
     * @param string $projectuuid Restored project UUID.
     * @return void
     */
    private function synchronise_publication(
        int $publicationid,
        string $publicationuuid,
        string $projectuuid
    ): void {
        global $DB;

        $contextid = $this->task->get_contextid();
        $fs = get_file_storage();
        $projectfile = $fs->get_file(
            $contextid,
            'local_interactembedcreator',
            'publication',
            $publicationid,
            '/',
            'project.json'
        );
        if ($projectfile) {
            $project = json_decode($projectfile->get_content(), false, 512, JSON_THROW_ON_ERROR);
            $project->projectId = $projectuuid;
            $this->replace_json_file($projectfile, $project);
        }

        $manifestfile = $fs->get_file(
            $contextid,
            'local_interactembedcreator',
            'publication',
            $publicationid,
            '/',
            'interactembed-manifest.json'
        );
        if ($manifestfile) {
            $manifest = json_decode($manifestfile->get_content(), false, 512, JSON_THROW_ON_ERROR);
            $manifest->publicationId = $publicationuuid;
            $this->replace_json_file($manifestfile, $manifest);
        }

        $files = $fs->get_area_files(
            $contextid,
            'local_interactembedcreator',
            'publication',
            $publicationid,
            'filepath, filename',
            false
        );
        $DB->set_field(
            'local_interactembedcreator_publication',
            'packagehash',
            \local_interactembedcreator\publication_api::calculate_hash($files),
            ['id' => $publicationid]
        );
    }

    /**
     * Replaces a JSON file while preserving its Moodle Files API identity.
     *
     * @param stored_file $file Existing stored file.
     * @param stdClass $value JSON value.
     * @return void
     */
    private function replace_json_file(stored_file $file, stdClass $value): void {
        $record = [
            'contextid' => $file->get_contextid(),
            'component' => $file->get_component(),
            'filearea' => $file->get_filearea(),
            'itemid' => $file->get_itemid(),
            'filepath' => $file->get_filepath(),
            'filename' => $file->get_filename(),
        ];
        $file->delete();
        get_file_storage()->create_file_from_string(
            $record,
            json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            )
        );
    }
}
