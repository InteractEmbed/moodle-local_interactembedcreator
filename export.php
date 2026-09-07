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

/**
 * Downloads an editable Creator source package.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\project_repository;
use local_interactembedcreator\local\library_context;
use local_interactembedcreator\local\source_package;

$projectid = required_param('id', PARAM_INT);
require_sesskey();
require_login();
$project = (new project_repository())->get($projectid, $USER->id);
$context = context::instance_by_id($project->contextid, MUST_EXIST);
library_context::validate($context);
library_context::require_login($context);
$filename = clean_filename($project->name) . '.interactembed-source.zip';
send_temp_file((new source_package())->export($project, $USER->id), $filename);
