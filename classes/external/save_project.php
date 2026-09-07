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

namespace local_interactembedcreator\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_interactembedcreator\local\project_repository;

/**
 * AJAX service used by the visual editor autosave.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_project extends external_api {
    /**
     * Defines input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'projectid' => new external_value(PARAM_INT, 'Project id'),
            'contentjson' => new external_value(PARAM_RAW, 'Schema v1 JSON document'),
            'baselockversion' => new external_value(PARAM_INT, 'Optimistic lock version'),
            'autosave' => new external_value(PARAM_BOOL, 'Whether this is an autosave'),
        ]);
    }

    /**
     * Stores a project snapshot.
     *
     * @param int $projectid
     * @param string $contentjson
     * @param int $baselockversion
     * @param bool $autosave
     * @return array
     */
    public static function execute(
        int $projectid,
        string $contentjson,
        int $baselockversion,
        bool $autosave
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'projectid' => $projectid,
            'contentjson' => $contentjson,
            'baselockversion' => $baselockversion,
            'autosave' => $autosave,
        ]);
        $repository = new project_repository();
        $project = $repository->get($params['projectid'], $USER->id, true);
        $context = context::instance_by_id($project->contextid, MUST_EXIST);
        self::validate_context($context);

        return $repository->save(
            $params['projectid'],
            $USER->id,
            $params['contentjson'],
            $params['baselockversion'],
            $params['autosave']
        );
    }

    /**
     * Defines the service result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'revision' => new external_value(PARAM_INT, 'Current revision number'),
            'lockversion' => new external_value(PARAM_INT, 'Current optimistic lock version'),
            'timemodified' => new external_value(PARAM_INT, 'Last modification timestamp'),
            'changed' => new external_value(PARAM_BOOL, 'Whether a new snapshot was stored'),
        ]);
    }
}
