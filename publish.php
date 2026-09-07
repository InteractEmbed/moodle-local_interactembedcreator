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

/**
 * Publishes an InteractEmbed project and downloads its HTML5 ZIP.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\project_repository;
use local_interactembedcreator\local\publication_builder;

$projectid = required_param('id', PARAM_INT);
require_login();
require_sesskey();
$repository = new project_repository();
$project = $repository->get($projectid, $USER->id, true);
$builder = new publication_builder();
$publication = $builder->publish($project, $USER->id);
redirect(
    new moodle_url('/local/interactembedcreator/publications.php', ['id' => $project->id]),
    get_string('publicationcreated', 'local_interactembedcreator', $publication->publicationno),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
