<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Downloads an editable Creator source package.
 *
 * @package local_interactembedcreator
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
