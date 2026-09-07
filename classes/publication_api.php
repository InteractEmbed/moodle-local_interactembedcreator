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

use context;
use context_course;
use local_interactembedcreator\local\library_context;
use moodle_exception;
use stdClass;
use stored_file;

/**
 * Stable read-only API used by optional InteractEmbed consumers.
 *
 * Consumers remain responsible for copying returned files into their own
 * component file area and for applying their normal package validation.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publication_api {
    /** Public contract version understood by optional consumers. */
    public const API_VERSION = 1;

    /**
     * Lists ready publications visible in a course context.
     *
     * @param context_course $context
     * @return array
     */
    public static function list_for_context(context_course $context): array {
        global $DB;

        require_capability('local/interactembedcreator:use', $context);
        $contextids = library_context::publication_context_ids($context);
        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $sql = 'SELECT pub.id, pub.uuid, pub.publicationno, pub.entrypoint, pub.packagehash,
                       pub.engineversion, pub.timecreated, p.id AS projectid, p.name
                  FROM {local_iec_publication} pub
                  JOIN {local_iec_project} p ON p.id = pub.projectid
                 WHERE p.contextid ' . $insql . ' AND pub.status = :status
              ORDER BY p.name ASC, pub.publicationno DESC';
        return array_values($DB->get_records_sql($sql, $inparams + ['status' => 'ready']));
    }

    /**
     * Returns immutable publication metadata after access checks.
     *
     * @param int $publicationid
     * @return stdClass
     */
    public static function get_snapshot(int $publicationid, context_course $consumercontext): stdClass {
        global $DB;

        require_capability('local/interactembedcreator:use', $consumercontext);
        $allowedcontextids = library_context::publication_context_ids($consumercontext);
        [$insql, $inparams] = $DB->get_in_or_equal($allowedcontextids, SQL_PARAMS_NAMED, 'ctx');
        $sql = 'SELECT pub.id, pub.uuid, pub.publicationno, pub.entrypoint, pub.packagehash,
                       pub.engineversion, pub.timecreated, p.name AS projectname, p.contextid
                  FROM {local_iec_publication} pub
                  JOIN {local_iec_project} p ON p.id = pub.projectid
                 WHERE pub.id = :publicationid
                       AND pub.status = :status
                       AND p.contextid ' . $insql;
        $publication = $DB->get_record_sql($sql, $inparams + [
            'publicationid' => $publicationid,
            'status' => 'ready',
        ], MUST_EXIST);
        $records = get_file_storage()->get_area_files(
            $publication->contextid,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            'filepath, filename',
            false
        );
        if (!$records) {
            throw new moodle_exception('filenotfound', 'error');
        }
        $publication->files = [];
        foreach ($records as $file) {
            $path = ltrim($file->get_filepath(), '/') . $file->get_filename();
            if (isset($publication->files[$path])) {
                throw new moodle_exception('invaliddata');
            }
            $publication->files[$path] = $file;
        }
        if (!hash_equals($publication->packagehash, self::calculate_hash($publication->files))) {
            throw new moodle_exception('invaliddata');
        }
        return $publication;
    }

    /**
     * Calculates the v1 canonical hash of an immutable file collection.
     *
     * Each record is: POSIX relative path, NUL, Moodle contenthash, NUL,
     * filesize in decimal, NUL. Records are sorted bytewise by path.
     *
     * @param stored_file[] $files
     * @return string Lowercase SHA-256.
     */
    public static function calculate_hash(array $files): string {
        $records = [];
        foreach ($files as $file) {
            if (!$file instanceof stored_file || $file->is_directory()) {
                continue;
            }
            $path = ltrim($file->get_filepath(), '/') . $file->get_filename();
            if (isset($records[$path])) {
                throw new moodle_exception('invaliddata');
            }
            $records[$path] = $file;
        }
        ksort($records, SORT_STRING);
        $hash = hash_init('sha256');
        foreach ($records as $path => $file) {
            hash_update($hash, $path . "\0" . $file->get_contenthash() . "\0" . $file->get_filesize() . "\0");
        }
        return hash_final($hash);
    }
}
