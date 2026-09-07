<?php
// This file is part of Moodle - http://moodle.org/.
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
use moodle_exception;
use stdClass;

/**
 * Portable, editable Creator source packages.
 *
 * These archives are deliberately distinct from immutable HTML5 publications.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_package {
    /** @var int Maximum uncompressed source package size (100 MiB). */
    private const MAX_UNCOMPRESSED_BYTES = 104857600;

    /** @var int Maximum number of archive entries. */
    private const MAX_ENTRIES = 250;

    /**
     * Builds an editable source ZIP and returns its temporary pathname.
     *
     * @param stdClass $project
     * @param int $userid
     * @return string
     */
    public function export(stdClass $project, int $userid): string {
        $repository = new project_repository();
        $project = $repository->get((int) $project->id, $userid);
        $document = $repository->get_current_document($project);
        unset($document->_revision, $document->_lockversion);
        $manifest = [
            'format' => 'interactembed-creator-source',
            'version' => 1,
            'schemaVersion' => 1,
            'name' => $project->name,
            'exportedAt' => gmdate('c'),
        ];
        $files = [
            'interactembed-source.json' => [json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )],
            'project.json' => [json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )],
        ];
        $storedfiles = get_file_storage()->get_area_files(
            $project->contextid,
            'local_interactembedcreator',
            'asset',
            $project->id,
            'id',
            false
        );
        $totalsize = strlen($files['interactembed-source.json'][0]) + strlen($files['project.json'][0]);
        foreach ($storedfiles as $storedfile) {
            $totalsize += $storedfile->get_filesize();
            if ($totalsize > self::MAX_UNCOMPRESSED_BYTES) {
                throw new moodle_exception('sourcepackagetoolarge', 'local_interactembedcreator');
            }
            $path = ltrim($storedfile->get_filepath(), '/') . $storedfile->get_filename();
            $files['assets/' . $path] = $storedfile;
        }
        $pathname = make_request_directory() . '/interactembed-source.zip';
        if (!get_file_packer('application/zip')->archive_to_pathname($files, $pathname, false)) {
            throw new moodle_exception('sourceexportfailed', 'local_interactembedcreator');
        }
        return $pathname;
    }

    /**
     * Imports a validated source archive as a new independent project.
     *
     * @param \stored_file|string $archive
     * @param context $context
     * @param int $userid
     * @param string|null $requestedname
     * @return stdClass
     */
    public function import(
        \stored_file|string $archive,
        context $context,
        int $userid,
        ?string $requestedname = null
    ): stdClass {
        $packer = get_file_packer('application/zip');
        $entries = $packer->list_files($archive);
        if ($entries === false || count($entries) < 2 || count($entries) > self::MAX_ENTRIES) {
            throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
        }
        $total = 0;
        $paths = [];
        $seen = [];
        foreach ($entries as $entry) {
            $path = (string) $entry->pathname;
            if (!self::safe_path($path) || isset($seen[$path])) {
                throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
            }
            $seen[$path] = true;
            $total += (int) $entry->size;
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new moodle_exception('sourcepackagetoolarge', 'local_interactembedcreator');
            }
            if (!$entry->is_directory) {
                if (
                    !in_array($path, ['project.json', 'interactembed-source.json'], true) &&
                        !str_starts_with($path, 'assets/')
                ) {
                    throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
                }
                $paths[] = $path;
            }
        }
        if (!in_array('project.json', $paths, true) || !in_array('interactembed-source.json', $paths, true)) {
            throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
        }

        $tempdir = make_request_directory();
        if (!$packer->extract_to_pathname($archive, $tempdir, $paths, null, true)) {
            throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
        }
        $manifestjson = file_get_contents($tempdir . '/interactembed-source.json');
        $documentjson = file_get_contents($tempdir . '/project.json');
        if ($manifestjson === false || $documentjson === false) {
            throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
        }
        try {
            $manifest = json_decode($manifestjson, true, 32, JSON_THROW_ON_ERROR);
            $document = json_decode($documentjson, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new moodle_exception(
                'invalidsourcepackage',
                'local_interactembedcreator',
                '',
                null,
                $exception->getMessage()
            );
        }
        if (($manifest['format'] ?? '') !== 'interactembed-creator-source' || (int) ($manifest['version'] ?? 0) !== 1) {
            throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
        }
        if (!is_object($document) || !is_string($document->projectId ?? null)) {
            throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
        }
        project_repository::validate_document($documentjson, $document->projectId);

        $name = trim(clean_param($requestedname ?? (string) ($manifest['name'] ?? ''), PARAM_TEXT));
        if ($name === '') {
            $name = get_string('importedproject', 'local_interactembedcreator');
        }
        $repository = new project_repository();
        $project = null;
        try {
            $project = $repository->create($context, $userid, $name, 'blank');
            $document->projectId = $project->uuid;
            $document->metadata = $document->metadata ?? new stdClass();
            $document->metadata->title = $name;
            $repository->save(
                $project->id,
                $userid,
                json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                (int) $project->lockversion,
                false
            );

            $fs = get_file_storage();
            foreach ($paths as $path) {
                if (!str_starts_with($path, 'assets/')) {
                    continue;
                }
                $relative = substr($path, 7);
                $parts = explode('/', $relative);
                $filename = array_pop($parts);
                $filepath = '/' . ($parts ? implode('/', $parts) . '/' : '');
                $created = $fs->create_file_from_pathname([
                    'contextid' => $context->id,
                    'component' => 'local_interactembedcreator',
                    'filearea' => 'asset',
                    'itemid' => $project->id,
                    'filepath' => $filepath,
                    'filename' => $filename,
                ], $tempdir . '/' . $path);
                if (!$created) {
                    throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
                }
            }
        } catch (\Throwable $exception) {
            if ($project !== null) {
                self::purge_failed_import($project, $context);
            }
            throw $exception;
        }
        return $project;
    }

    /**
     * Rejects absolute, traversal, ambiguous and platform metadata paths.
     *
     * @param string $path
     * @return bool
     */
    private static function safe_path(string $path): bool {
        $segments = explode('/', rtrim($path, '/'));
        return $path !== '' && strlen($path) <= 1024 && !str_contains($path, "\0") &&
            !str_contains($path, '\\') && !str_starts_with($path, '/') &&
            !preg_match('/^[a-z]:/i', $path) &&
            !in_array('', $segments, true) && !in_array('.', $segments, true) &&
            !in_array('..', $segments, true) &&
            !str_starts_with($path, '__MACOSX/');
    }

    /**
     * Removes an incomplete project created by an import that could not finish.
     *
     * @param stdClass $project
     * @param context $context
     */
    private static function purge_failed_import(stdClass $project, context $context): void {
        global $DB;

        get_file_storage()->delete_area_files(
            $context->id,
            'local_interactembedcreator',
            'asset',
            $project->id
        );
        $DB->delete_records('local_iec_revision', ['projectid' => $project->id]);
        $DB->delete_records('local_iec_project', ['id' => $project->id]);
    }
}
