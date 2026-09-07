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

namespace local_interactembedcreator\local;

use context;
use core_text;
use moodle_exception;
use stdClass;

/**
 * Persistence service for editable InteractEmbed projects.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class project_repository {
    /** @var int Maximum number of scenes accepted by schema v1. */
    private const MAX_SCENES = 300;

    /** @var int Maximum number of elements accepted in one scene. */
    private const MAX_ELEMENTS_PER_SCENE = 100;

    /**
     * Creates a project and its first durable revision.
     *
     * @param context $context
     * @param int $ownerid
     * @param string $name
     * @param string $template
     * @return stdClass
     */
    public function create(context $context, int $ownerid, string $name, string $template = 'blank'): stdClass {
        global $DB;

        library_context::validate($context);
        require_capability('local/interactembedcreator:create', $context);
        $name = trim(clean_param($name, PARAM_TEXT));
        if ($name === '') {
            throw new moodle_exception('invaliddata');
        }
        if (!in_array($template, ['blank', 'presentation', 'microcourse', 'quiz', 'showcase'], true)) {
            $template = 'blank';
        }

        $limit = max(0, (int) get_config('local_interactembedcreator', 'maxprojectsperuser'));
        if ($limit > 0) {
            $active = $DB->count_records_select(
                'local_iec_project',
                'ownerid = :ownerid AND status <> :archived',
                ['ownerid' => $ownerid, 'archived' => 'archived']
            );
            if ($active >= $limit) {
                throw new moodle_exception('error');
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $project = (object) [
            'uuid' => self::uuid(),
            'contextid' => $context->id,
            'ownerid' => $ownerid,
            'name' => $name,
            'description' => '',
            'status' => 'draft',
            'currentrevision' => 0,
            'lockversion' => 0,
            'defaultlocale' => substr(current_language(), 0, 2),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $project->id = $DB->insert_record('local_iec_project', $project);

        $contentjson = json_encode(
            self::default_document($project->uuid, $name, $template),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $revision = $this->insert_revision($project, $ownerid, $contentjson, 'manual', 'Initial revision');
        $project->currentrevision = $revision->id;
        $project->lockversion = 1;
        $DB->update_record('local_iec_project', $project);
        if ($template === 'showcase') {
            official_template::install_assets($context, $project->id, $ownerid);
        }
        $transaction->allow_commit();

        return $project;
    }

    /**
     * Returns one project after checking the caller's permissions.
     *
     * @param int $projectid
     * @param int $userid
     * @param bool $foredit
     * @return stdClass
     */
    public function get(int $projectid, int $userid, bool $foredit = false): stdClass {
        global $DB;

        $project = $DB->get_record('local_iec_project', ['id' => $projectid], '*', MUST_EXIST);
        $context = context::instance_by_id($project->contextid, MUST_EXIST);
        require_capability('local/interactembedcreator:view', $context);

        if (
            $foredit && $project->ownerid != $userid &&
                !has_capability('local/interactembedcreator:editany', $context)
        ) {
            throw new moodle_exception('nopermissions', 'error', '', get_string('editproject', 'local_interactembedcreator'));
        }
        if ($foredit && $project->ownerid == $userid) {
            require_capability('local/interactembedcreator:editown', $context);
        }

        return $project;
    }

    /**
     * Returns the current project document.
     *
     * @param stdClass $project
     * @return stdClass
     */
    public function get_current_document(stdClass $project): stdClass {
        global $DB;

        $revision = $DB->get_record(
            'local_iec_revision',
            ['id' => $project->currentrevision, 'projectid' => $project->id],
            '*',
            MUST_EXIST
        );
        $document = json_decode($revision->contentjson, false, 512, JSON_THROW_ON_ERROR);
        $document->_revision = $revision->revisionno;
        $document->_lockversion = $project->lockversion;
        return $document;
    }

    /**
     * Stores a validated snapshot with optimistic concurrency.
     *
     * @param int $projectid
     * @param int $userid
     * @param string $contentjson
     * @param int $baselockversion
     * @param bool $autosave
     * @return array
     */
    public function save(
        int $projectid,
        int $userid,
        string $contentjson,
        int $baselockversion,
        bool $autosave
    ): array {
        global $DB;

        $project = $this->get($projectid, $userid, true);
        if ($project->status === 'archived') {
            throw new moodle_exception('projectisarchived', 'local_interactembedcreator');
        }
        self::validate_document($contentjson, $project->uuid);

        $transaction = $DB->start_delegated_transaction();
        $project = $DB->get_record_sql(
            'SELECT * FROM {local_iec_project} WHERE id = :id FOR UPDATE',
            ['id' => $projectid],
            MUST_EXIST
        );
        if ((int) $project->lockversion !== $baselockversion) {
            throw new moodle_exception('saveconflict', 'local_interactembedcreator');
        }

        $current = $DB->get_record('local_iec_revision', ['id' => $project->currentrevision], '*', MUST_EXIST);
        $hash = hash('sha256', $contentjson);
        if (hash_equals($current->contenthash, $hash)) {
            $transaction->allow_commit();
            return [
                'revision' => (int) $current->revisionno,
                'lockversion' => (int) $project->lockversion,
                'timemodified' => (int) $project->timemodified,
                'changed' => false,
            ];
        }

        $type = $autosave ? 'autosave' : 'manual';
        $revision = $this->insert_revision($project, $userid, $contentjson, $type, null);
        $project->currentrevision = $revision->id;
        $project->lockversion++;
        $project->timemodified = time();
        $DB->update_record('local_iec_project', $project);
        $transaction->allow_commit();

        $this->prune_revisions($projectid);

        return [
            'revision' => (int) $revision->revisionno,
            'lockversion' => (int) $project->lockversion,
            'timemodified' => (int) $project->timemodified,
            'changed' => true,
        ];
    }

    /**
     * Lists visible projects in one context.
     *
     * @param context $context
     * @param int $userid
     * @return array
     */
    public function list(
        context $context,
        int $userid,
        bool $archived = false,
        string $search = '',
        int $offset = 0,
        int $limit = 0
    ): array {
        global $DB;

        require_capability('local/interactembedcreator:view', $context);
        $caneditany = has_capability('local/interactembedcreator:editany', $context);
        $where = $archived
            ? 'contextid = :contextid AND status = :archived'
            : 'contextid = :contextid AND status <> :archived';
        $params = ['contextid' => $context->id, 'archived' => 'archived'];
        if (!$caneditany) {
            $where .= ' AND ownerid = :ownerid';
            $params['ownerid'] = $userid;
        }
        $search = trim(clean_param($search, PARAM_TEXT));
        if ($search !== '') {
            $where .= ' AND ' . $DB->sql_like('name', ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        return $DB->get_records_select('local_iec_project', $where, $params, 'timemodified DESC', '*', $offset, $limit);
    }

    /**
     * Counts projects matching the same visibility and search rules as list().
     *
     * @param context $context
     * @param int $userid
     * @param bool $archived
     * @param string $search
     * @return int
     */
    public function count(context $context, int $userid, bool $archived = false, string $search = ''): int {
        global $DB;

        require_capability('local/interactembedcreator:view', $context);
        $where = $archived
            ? 'contextid = :contextid AND status = :archived'
            : 'contextid = :contextid AND status <> :archived';
        $params = ['contextid' => $context->id, 'archived' => 'archived'];
        if (!has_capability('local/interactembedcreator:editany', $context)) {
            $where .= ' AND ownerid = :ownerid';
            $params['ownerid'] = $userid;
        }
        $search = trim(clean_param($search, PARAM_TEXT));
        if ($search !== '') {
            $where .= ' AND ' . $DB->sql_like('name', ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        return $DB->count_records_select('local_iec_project', $where, $params);
    }

    /**
     * Renames a project and records the matching document title as a new revision.
     *
     * @param int $projectid
     * @param int $userid
     * @param string $name
     * @return stdClass
     */
    public function rename(int $projectid, int $userid, string $name): stdClass {
        global $DB;

        $project = $this->get($projectid, $userid, true);
        $name = trim(clean_param($name, PARAM_TEXT));
        if ($name === '') {
            throw new moodle_exception('invaliddata');
        }
        $document = $this->get_current_document($project);
        $lockversion = (int) $document->_lockversion;
        unset($document->_revision, $document->_lockversion);
        $document->metadata = $document->metadata ?? new stdClass();
        $document->metadata->title = $name;
        $this->save(
            $project->id,
            $userid,
            json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $lockversion,
            false
        );
        $project = $DB->get_record('local_iec_project', ['id' => $projectid], '*', MUST_EXIST);
        $project->name = $name;
        $project->timemodified = time();
        $DB->update_record('local_iec_project', $project);
        return $project;
    }

    /**
     * Archives or restores a project without deleting its history or files.
     *
     * @param int $projectid
     * @param int $userid
     * @param bool $archived
     * @return stdClass
     */
    public function set_archived(int $projectid, int $userid, bool $archived): stdClass {
        global $DB;

        $project = $this->get($projectid, $userid, true);
        $project->status = $archived ? 'archived' : 'draft';
        $project->timemodified = time();
        $DB->update_record('local_iec_project', $project);
        return $project;
    }

    /**
     * Creates an independent editable copy, including the current media files.
     *
     * @param int $projectid
     * @param int $userid
     * @param string $name
     * @return stdClass
     */
    public function duplicate(int $projectid, int $userid, string $name, ?context $targetcontext = null): stdClass {
        $source = $this->get($projectid, $userid);
        $sourcecontext = context::instance_by_id($source->contextid, MUST_EXIST);
        $targetcontext = $targetcontext ?? $sourcecontext;
        library_context::validate($targetcontext);

        $copy = $this->create($targetcontext, $userid, $name, 'blank');
        $document = $this->get_current_document($source);
        unset($document->_revision, $document->_lockversion);
        $document->projectId = $copy->uuid;
        $document->metadata = $document->metadata ?? new stdClass();
        $document->metadata->title = $name;
        $this->save(
            $copy->id,
            $userid,
            json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            (int) $copy->lockversion,
            false
        );

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $sourcecontext->id,
            'local_interactembedcreator',
            'asset',
            $source->id,
            'id',
            false
        );
        foreach ($files as $file) {
            $fs->create_file_from_storedfile([
                'contextid' => $targetcontext->id,
                'component' => 'local_interactembedcreator',
                'filearea' => 'asset',
                'itemid' => $copy->id,
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ], $file);
        }
        return $copy;
    }

    /**
     * Returns projects owned by a user in every context they may view.
     *
     * @return stdClass[]
     */
    public function list_owned(int $userid, bool $archived = false, string $search = ''): array {
        global $DB;

        $where = $archived ? 'ownerid = :ownerid AND status = :archived' :
            'ownerid = :ownerid AND status <> :archived';
        $params = ['ownerid' => $userid, 'archived' => 'archived'];
        $search = trim(clean_param($search, PARAM_TEXT));
        if ($search !== '') {
            $where .= ' AND ' . $DB->sql_like('name', ':search', false);
            $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        $projects = $DB->get_records_select('local_iec_project', $where, $params, 'timemodified DESC');
        return array_filter($projects, static function (stdClass $project) use ($userid): bool {
            $context = context::instance_by_id($project->contextid, IGNORE_MISSING);
            return $context && has_capability('local/interactembedcreator:view', $context, $userid);
        });
    }

    /**
     * Lists immutable revisions, newest first.
     *
     * @param int $projectid
     * @param int $userid
     * @return array
     */
    public function list_revisions(int $projectid, int $userid, int $offset = 0, int $limit = 0): array {
        global $DB;

        $this->get($projectid, $userid);
        return $DB->get_records('local_iec_revision', ['projectid' => $projectid], 'revisionno DESC', '*', $offset, $limit);
    }

    /**
     * Counts retained revisions for a visible project.
     *
     * @param int $projectid
     * @param int $userid
     * @return int
     */
    public function count_revisions(int $projectid, int $userid): int {
        global $DB;

        $this->get($projectid, $userid);
        return $DB->count_records('local_iec_revision', ['projectid' => $projectid]);
    }

    /**
     * Restores a previous snapshot as a new revision, preserving the complete audit trail.
     *
     * @param int $projectid
     * @param int $revisionid
     * @param int $userid
     * @return array
     */
    public function restore_revision(int $projectid, int $revisionid, int $userid): array {
        global $DB;

        $project = $this->get($projectid, $userid, true);
        $source = $DB->get_record(
            'local_iec_revision',
            ['id' => $revisionid, 'projectid' => $projectid],
            '*',
            MUST_EXIST
        );
        self::validate_document($source->contentjson, $project->uuid);

        $transaction = $DB->start_delegated_transaction();
        $project = $DB->get_record_sql(
            'SELECT * FROM {local_iec_project} WHERE id = :id FOR UPDATE',
            ['id' => $projectid],
            MUST_EXIST
        );
        $revision = $this->insert_revision(
            $project,
            $userid,
            $source->contentjson,
            'manual',
            'Restored revision ' . $source->revisionno
        );
        $project->currentrevision = $revision->id;
        $project->lockversion++;
        $project->status = 'draft';
        $project->timemodified = time();
        $DB->update_record('local_iec_project', $project);
        $transaction->allow_commit();
        $this->prune_revisions($projectid);

        return [
            'revision' => (int) $revision->revisionno,
            'lockversion' => (int) $project->lockversion,
        ];
    }

    /**
     * Inserts the next strictly increasing revision.
     *
     * @param stdClass $project
     * @param int $authorid
     * @param string $contentjson
     * @param string $type
     * @param string|null $message
     * @return stdClass
     */
    private function insert_revision(
        stdClass $project,
        int $authorid,
        string $contentjson,
        string $type,
        ?string $message
    ): stdClass {
        global $DB;

        $maxrevision = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(revisionno), 0) FROM {local_iec_revision} WHERE projectid = :projectid',
            ['projectid' => $project->id]
        );
        $revision = (object) [
            'projectid' => $project->id,
            'revisionno' => $maxrevision + 1,
            'schemaversion' => 1,
            'contentjson' => $contentjson,
            'contenthash' => hash('sha256', $contentjson),
            'revisiontype' => $type,
            'message' => $message,
            'authorid' => $authorid,
            'timecreated' => time(),
        ];
        $revision->id = $DB->insert_record('local_iec_revision', $revision);
        return $revision;
    }

    /**
     * Removes old non-published snapshots without touching the current revision.
     *
     * @param int $projectid
     */
    private function prune_revisions(int $projectid): void {
        global $DB;

        $limit = max(5, (int) get_config('local_interactembedcreator', 'maxrevisions'));
        $project = $DB->get_record('local_iec_project', ['id' => $projectid], '*', MUST_EXIST);
        $records = $DB->get_records_select(
            'local_iec_revision',
            'projectid = :projectid AND revisiontype <> :published AND id <> :currentid',
            ['projectid' => $projectid, 'published' => 'published', 'currentid' => $project->currentrevision],
            'revisionno DESC',
            'id'
        );
        if (count($records) <= $limit) {
            return;
        }
        $deleteids = array_slice(array_keys($records), $limit);
        $DB->delete_records_list('local_iec_revision', 'id', $deleteids);
    }

    /**
     * Validates schema v1 and its resource limits.
     *
     * @param string $contentjson
     * @param string $projectuuid
     */
    public static function validate_document(string $contentjson, string $projectuuid): void {
        if (strlen($contentjson) > 5 * 1024 * 1024) {
            throw new moodle_exception('invaliddata');
        }
        try {
            $document = json_decode($contentjson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new moodle_exception('invalidjson', 'error', '', null, $exception->getMessage());
        }
        if (
            !is_array($document) || ($document['schema'] ?? '') !== 'interactembed-project' ||
                (int) ($document['version'] ?? 0) !== 1 || ($document['projectId'] ?? '') !== $projectuuid
        ) {
            throw new moodle_exception('invaliddata');
        }
        $canvas = $document['canvas'] ?? null;
        if (
            !is_array($canvas) || (int) ($canvas['width'] ?? 0) !== 1920 ||
                (int) ($canvas['height'] ?? 0) !== 1080 || ($canvas['aspectRatio'] ?? '') !== '16:9'
        ) {
            throw new moodle_exception('invaliddata');
        }
        $settings = $document['settings'] ?? [];
        if (
            !is_array($settings) ||
                (isset($settings['navigation']) && !is_bool($settings['navigation'])) ||
                (isset($settings['completion']) && !in_array($settings['completion'], ['last-scene'], true))
        ) {
            throw new moodle_exception('invaliddata');
        }
        $scenes = $document['scenes'] ?? null;
        if (!is_array($scenes) || count($scenes) < 1 || count($scenes) > self::MAX_SCENES) {
            throw new moodle_exception('invaliddata');
        }
        $sceneids = [];
        foreach ($scenes as $scene) {
            if (!is_array($scene) || !self::valid_id($scene['id'] ?? '') || isset($sceneids[$scene['id']])) {
                throw new moodle_exception('invaliddata');
            }
            $sceneids[$scene['id']] = true;
        }
        $allowedtypes = ['text', 'image', 'audio', 'video', 'shape', 'button', 'quiz'];
        foreach ($scenes as $scene) {
            if (isset($scene['title']) && (!is_string($scene['title']) || core_text::strlen($scene['title']) > 255)) {
                throw new moodle_exception('invaliddata');
            }
            $scenestyle = $scene['style'] ?? [];
            if (!is_array($scenestyle)) {
                throw new moodle_exception('invaliddata');
            }
            $backgroundcolor = $scenestyle['backgroundColor'] ?? '#ffffff';
            if (!is_string($backgroundcolor) || !preg_match('/^#[0-9a-f]{6}$/i', $backgroundcolor)) {
                throw new moodle_exception('invaliddata');
            }
            $backgroundimage = $scenestyle['backgroundImage'] ?? '';
            if (!is_string($backgroundimage) || ($backgroundimage !== '' && !self::valid_relative_path($backgroundimage))) {
                throw new moodle_exception('invaliddata');
            }
            $elements = $scene['elements'] ?? [];
            if (!is_array($elements) || count($elements) > self::MAX_ELEMENTS_PER_SCENE) {
                throw new moodle_exception('invaliddata');
            }
            $elementids = [];
            foreach ($elements as $element) {
                if (
                    !is_array($element) || !self::valid_id($element['id'] ?? '') ||
                        isset($elementids[$element['id']]) || !in_array($element['type'] ?? '', $allowedtypes, true)
                ) {
                    throw new moodle_exception('invaliddata');
                }
                $elementids[$element['id']] = true;
                $content = $element['content'] ?? null;
                if (!is_array($content)) {
                    throw new moodle_exception('invaliddata');
                }
                $transform = $element['transform'] ?? null;
                if (!is_array($transform)) {
                    throw new moodle_exception('invaliddata');
                }
                foreach (['x', 'y', 'width', 'height', 'rotation'] as $property) {
                    if (!isset($transform[$property]) || !is_numeric($transform[$property])) {
                        throw new moodle_exception('invaliddata');
                    }
                }
                $style = $element['style'] ?? [];
                if (!is_array($style)) {
                    throw new moodle_exception('invaliddata');
                }
                $accessibility = $element['accessibility'] ?? [];
                if (!is_array($accessibility)) {
                    throw new moodle_exception('invaliddata');
                }
                if (
                    isset($accessibility['label']) &&
                        (!is_string($accessibility['label']) || core_text::strlen($accessibility['label']) > 500)
                ) {
                    throw new moodle_exception('invaliddata');
                }
                if (
                    isset($accessibility['alt']) &&
                        (!is_string($accessibility['alt']) || core_text::strlen($accessibility['alt']) > 2000)
                ) {
                    throw new moodle_exception('invaliddata');
                }
                if (isset($accessibility['decorative']) && !is_bool($accessibility['decorative'])) {
                    throw new moodle_exception('invaliddata');
                }
                if (
                    isset($style['textAlign']) &&
                        !in_array($style['textAlign'], ['left', 'center', 'right'], true)
                ) {
                    throw new moodle_exception('invaliddata');
                }
                if (isset($style['fontWeight']) && !in_array($style['fontWeight'], ['normal', 'bold'], true)) {
                    throw new moodle_exception('invaliddata');
                }
                if (isset($style['fontStyle']) && !in_array($style['fontStyle'], ['normal', 'italic'], true)) {
                    throw new moodle_exception('invaliddata');
                }
                if (
                    isset($style['textDecoration']) &&
                        !in_array($style['textDecoration'], ['none', 'underline'], true)
                ) {
                    throw new moodle_exception('invaliddata');
                }
                if (in_array($element['type'], ['text', 'button'], true) && isset($element['content']['href'])) {
                    $href = $element['content']['href'];
                    if (
                        !is_string($href) || strlen($href) > 2048 ||
                            ($href !== '' && (!preg_match('~^https?://~i', $href) ||
                                filter_var($href, FILTER_VALIDATE_URL) === false))
                    ) {
                        throw new moodle_exception('invaliddata');
                    }
                }
                if (in_array($element['type'], ['image', 'audio', 'video'], true)) {
                    $assetpath = $element['content']['assetPath'] ?? '';
                    if (!is_string($assetpath) || !self::valid_relative_path($assetpath)) {
                        throw new moodle_exception('invaliddata');
                    }
                }
                if (
                    $element['type'] === 'image' && isset($content['fit']) &&
                        !in_array($content['fit'], ['contain', 'cover'], true)
                ) {
                    throw new moodle_exception('invaliddata');
                }
                if (in_array($element['type'], ['audio', 'video'], true)) {
                    foreach (['controls', 'loop', 'muted'] as $property) {
                        if (isset($content[$property]) && !is_bool($content[$property])) {
                            throw new moodle_exception('invaliddata');
                        }
                    }
                    if (
                        isset($content['transcript']) &&
                            (!is_string($content['transcript']) || core_text::strlen($content['transcript']) > 50000)
                    ) {
                        throw new moodle_exception('invaliddata');
                    }
                    foreach (['posterPath', 'captionsPath'] as $property) {
                        $path = $content[$property] ?? '';
                        if (!is_string($path) || ($path !== '' && !self::valid_relative_path($path))) {
                            throw new moodle_exception('invaliddata');
                        }
                    }
                }
                if ($element['type'] === 'button') {
                    $action = $element['content']['action'] ?? '';
                    if (!in_array($action, ['next', 'previous', 'scene', 'url', 'none'], true)) {
                        throw new moodle_exception('invaliddata');
                    }
                    if ($action === 'scene' && !isset($sceneids[$element['content']['targetSceneId'] ?? ''])) {
                        throw new moodle_exception('invaliddata');
                    }
                    if ($action === 'url' && empty($element['content']['href'])) {
                        throw new moodle_exception('invaliddata');
                    }
                }
                if ($element['type'] === 'quiz') {
                    $answers = $element['content']['answers'] ?? null;
                    $mode = $element['content']['selectionMode'] ?? 'single';
                    $correctindexes = $element['content']['correctIndexes'] ?? null;
                    if ($correctindexes === null && isset($element['content']['correctIndex'])) {
                        $correctindexes = [$element['content']['correctIndex']];
                    }
                    if (
                        !is_array($answers) || count($answers) < 2 || count($answers) > 10 ||
                            !in_array($mode, ['single', 'multiple', 'truefalse'], true) ||
                            !is_array($correctindexes) || count($correctindexes) < 1 ||
                            ($mode !== 'multiple' && count($correctindexes) !== 1) ||
                            ($mode === 'truefalse' && count($answers) !== 2)
                    ) {
                        throw new moodle_exception('invaliddata');
                    }
                    foreach ($answers as $answer) {
                        if (!is_string($answer) || trim($answer) === '' || core_text::strlen($answer) > 1000) {
                            throw new moodle_exception('invaliddata');
                        }
                    }
                    if (count(array_unique($correctindexes, SORT_REGULAR)) !== count($correctindexes)) {
                        throw new moodle_exception('invaliddata');
                    }
                    foreach ($correctindexes as $correctindex) {
                        if (!is_int($correctindex) || $correctindex < 0 || $correctindex >= count($answers)) {
                            throw new moodle_exception('invaliddata');
                        }
                    }
                    $points = $element['content']['points'] ?? 1;
                    if (!is_int($points) || $points < 1 || $points > 100) {
                        throw new moodle_exception('invaliddata');
                    }
                    foreach (['correctSceneId', 'incorrectSceneId'] as $targetproperty) {
                        $target = $element['content'][$targetproperty] ?? '';
                        if (!is_string($target) || ($target !== '' && !isset($sceneids[$target]))) {
                            throw new moodle_exception('invaliddata');
                        }
                    }
                }
            }
        }
    }

    /**
     * Builds the initial schema v1 document.
     *
     * @param string $projectuuid
     * @param string $name
     * @param string $template
     * @return array
     */
    private static function default_document(string $projectuuid, string $name, string $template): array {
        if ($template === 'showcase') {
            $lang = substr(current_language(), 0, 2);
            $lang = in_array($lang, ['en', 'fr', 'es'], true) ? $lang : 'en';
            return official_template::document($projectuuid, $name, $lang);
        }
        $scenes = [[
            'id' => self::uuid(),
            'title' => get_string('scene', 'local_interactembedcreator', 1),
            'style' => ['backgroundColor' => '#ffffff', 'backgroundImage' => ''],
            'elements' => [self::text_element($name, 160, 160, 1600, 180, 72)],
        ]];
        if (in_array($template, ['presentation', 'microcourse'], true)) {
            $secondtitle = $template === 'microcourse'
                ? get_string('templatesceneobjectives', 'local_interactembedcreator')
                : get_string('templatescenecontent', 'local_interactembedcreator');
            $scenes[] = [
                'id' => self::uuid(),
                'title' => $secondtitle,
                'style' => ['backgroundColor' => '#ffffff', 'backgroundImage' => ''],
                'elements' => [self::text_element($secondtitle, 180, 180, 1560, 220, 60)],
            ];
            $scenes[] = [
                'id' => self::uuid(),
                'title' => get_string('templatesceneend', 'local_interactembedcreator'),
                'style' => ['backgroundColor' => '#ffffff', 'backgroundImage' => ''],
                'elements' => [self::text_element(
                    get_string('templatesceneend', 'local_interactembedcreator'),
                    180,
                    300,
                    1560,
                    220,
                    60
                )],
            ];
        } else if ($template === 'quiz') {
            $question = get_string('quizdefaultquestion', 'local_interactembedcreator');
            $scenes[] = [
                'id' => self::uuid(),
                'title' => get_string('templatequiz', 'local_interactembedcreator'),
                'style' => ['backgroundColor' => '#ffffff', 'backgroundImage' => ''],
                'elements' => [[
                    'id' => self::uuid(),
                    'type' => 'quiz',
                    'transform' => ['x' => 360, 'y' => 250, 'width' => 1200, 'height' => 580, 'rotation' => 0],
                    'content' => [
                        'question' => $question,
                        'answers' => [
                            get_string('quizdefaultanswer1', 'local_interactembedcreator'),
                            get_string('quizdefaultanswer2', 'local_interactembedcreator'),
                        ],
                        'selectionMode' => 'single',
                        'correctIndex' => 0,
                        'correctIndexes' => [0],
                        'points' => 1,
                        'checkLabel' => get_string('checkanswer', 'local_interactembedcreator'),
                        'correctFeedback' => get_string('quizfeedbackcorrect', 'local_interactembedcreator'),
                        'incorrectFeedback' => get_string('quizfeedbackincorrect', 'local_interactembedcreator'),
                        'correctSceneId' => '',
                        'incorrectSceneId' => '',
                    ],
                    'style' => ['background' => '#ffffff', 'color' => '#172033'],
                    'accessibility' => ['label' => $question],
                ]],
            ];
        }
        return [
            'schema' => 'interactembed-project',
            'version' => 1,
            'projectId' => $projectuuid,
            'metadata' => ['title' => $name],
            'canvas' => ['width' => 1920, 'height' => 1080, 'aspectRatio' => '16:9'],
            'settings' => ['navigation' => true, 'completion' => 'last-scene'],
            'scenes' => $scenes,
        ];
    }

    /**
     * Builds one accessible text element for a template.
     *
     * @param string $text
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @param int $fontsize
     * @return array
     */
    private static function text_element(
        string $text,
        int $x,
        int $y,
        int $width,
        int $height,
        int $fontsize
    ): array {
        return [
            'id' => self::uuid(),
            'type' => 'text',
            'transform' => compact('x', 'y', 'width', 'height') + ['rotation' => 0],
            'content' => ['text' => $text],
            'style' => ['fontSize' => $fontsize, 'color' => '#172033', 'textAlign' => 'center'],
            'accessibility' => ['label' => $text],
        ];
    }

    /**
     * Generates an RFC 4122 version 4 UUID without an external dependency.
     *
     * @return string
     */
    public static function uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' .
            substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    /**
     * Checks a stable UUID-like identifier.
     *
     * @param mixed $value
     * @return bool
     */
    private static function valid_id($value): bool {
        return is_string($value) && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._:-]{2,63}$/', $value) === 1;
    }

    /**
     * Checks an asset path without permitting traversal or absolute paths.
     *
     * @param string $path
     * @return bool
     */
    private static function valid_relative_path(string $path): bool {
        if (
            $path === '' || str_starts_with($path, '/') || str_contains($path, "\0") ||
                str_contains($path, '\\') || str_contains($path, '//')
        ) {
            return false;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
