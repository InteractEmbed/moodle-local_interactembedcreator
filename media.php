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
 * Project media manager.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\form\assets_form;
use local_interactembedcreator\local\library_context;
use local_interactembedcreator\local\project_repository;

$projectid = required_param('id', PARAM_INT);
require_login();
$repository = new project_repository();
$project = $repository->get($projectid, $USER->id, true);
if ($project->status === 'archived') {
    throw new moodle_exception('projectisarchived', 'local_interactembedcreator');
}
$context = context::instance_by_id($project->contextid, MUST_EXIST);
library_context::validate($context);
library_context::require_login($context);
$course = library_context::page_course($context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/media.php', ['id' => $project->id]));
$PAGE->set_title(get_string('managemedia', 'local_interactembedcreator'));
$PAGE->set_heading(format_string($project->name));

$draftitemid = file_get_submitted_draft_itemid('assets');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'local_interactembedcreator',
    'asset',
    $project->id,
    ['subdirs' => true, 'maxfiles' => 200]
);
$form = new assets_form(null, ['project' => $project]);
$form->set_data((object) ['assets' => $draftitemid]);
$editurl = new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]);

if ($form->is_cancelled()) {
    redirect($editurl);
} else if ($data = $form->get_data()) {
    file_save_draft_area_files(
        $data->assets,
        $context->id,
        'local_interactembedcreator',
        'asset',
        $project->id,
        ['subdirs' => true, 'maxfiles' => 200]
    );
    redirect($editurl, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managemedia', 'local_interactembedcreator'));
$form->display();
echo $OUTPUT->footer();
