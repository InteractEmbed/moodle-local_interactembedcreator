<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_interactembedcreator\local;

use context;
use moodle_exception;
use stdClass;

/** Manages visibility of immutable publications without deleting their files. */
final class publication_manager {
    /**
     * Lists every retained publication for a project, newest first.
     *
     * @param int $projectid
     * @param int $userid
     * @return array
     */
    public function list(int $projectid, int $userid, int $offset = 0, int $limit = 0): array {
        global $DB;

        (new project_repository())->get($projectid, $userid);
        return $DB->get_records(
            'local_iec_publication',
            ['projectid' => $projectid],
            'publicationno DESC',
            '*',
            $offset,
            $limit
        );
    }

    /**
     * Counts retained publications for a visible project.
     *
     * @param int $projectid
     * @param int $userid
     * @return int
     */
    public function count(int $projectid, int $userid): int {
        global $DB;

        (new project_repository())->get($projectid, $userid);
        return $DB->count_records('local_iec_publication', ['projectid' => $projectid]);
    }

    /**
     * Makes an immutable publication available to or withdrawn from new consumer copies.
     *
     * Existing copies in the Block and Activity remain independent and unchanged.
     *
     * @param int $publicationid
     * @param int $userid
     * @param bool $available
     * @return stdClass
     */
    public function set_available(int $publicationid, int $userid, bool $available): stdClass {
        global $DB;

        $publication = $DB->get_record('local_iec_publication', ['id' => $publicationid], '*', MUST_EXIST);
        $project = (new project_repository())->get((int) $publication->projectid, $userid, true);
        $context = context::instance_by_id($project->contextid, MUST_EXIST);
        require_capability('local/interactembedcreator:publish', $context);
        if (!in_array($publication->status, ['ready', 'withdrawn'], true)) {
            throw new moodle_exception('invaliddata');
        }
        $publication->status = $available ? 'ready' : 'withdrawn';
        $DB->update_record('local_iec_publication', $publication);
        return $publication;
    }
}
