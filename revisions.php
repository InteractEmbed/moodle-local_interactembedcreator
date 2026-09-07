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
 * Project revision history and recovery.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\project_repository;
use local_interactembedcreator\local\library_context;

$projectid = required_param('id', PARAM_INT);
require_login();
$repository = new project_repository();
$project = $repository->get($projectid, $USER->id);
$context = context::instance_by_id($project->contextid, MUST_EXIST);
library_context::validate($context);
library_context::require_login($context);
$course = library_context::page_course($context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/revisions.php', ['id' => $project->id]));
$PAGE->set_title(get_string('revisionhistory', 'local_interactembedcreator'));
$PAGE->set_heading(format_string($project->name));

if (optional_param('action', '', PARAM_ALPHA) === 'restore') {
    require_sesskey();
    $repository->restore_revision($project->id, required_param('revisionid', PARAM_INT), $USER->id);
    redirect(
        new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]),
        get_string('revisionrestored', 'local_interactembedcreator'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$canedit = $project->ownerid == $USER->id
    ? has_capability('local/interactembedcreator:editown', $context)
    : has_capability('local/interactembedcreator:editany', $context);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;
$total = $repository->count_revisions($project->id, $USER->id);
$revisions = [];
foreach ($repository->list_revisions($project->id, $USER->id, $page * $perpage, $perpage) as $revision) {
    $author = core_user::get_user($revision->authorid, '*', MUST_EXIST);
    $revisions[] = [
        'id' => $revision->id,
        'projectid' => $project->id,
        'revisionno' => $revision->revisionno,
        'type' => get_string('revisiontype' . $revision->revisiontype, 'local_interactembedcreator'),
        'message' => format_string($revision->message ?? ''),
        'author' => fullname($author),
        'created' => userdate($revision->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'current' => (int) $revision->id === (int) $project->currentrevision,
        'canrestore' => $canedit && (int) $revision->id !== (int) $project->currentrevision,
    ];
}
$data = [
    'id' => $project->id,
    'sesskey' => sesskey(),
    'revisions' => $revisions,
    'editurl' => (new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]))->out(false),
    'libraryurl' => (new moodle_url(
        '/local/interactembedcreator/index.php',
        library_context::url_params($context)
    ))->out(false),
    'paging' => $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url(
        '/local/interactembedcreator/revisions.php',
        ['id' => $project->id]
    )),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_interactembedcreator/revisions', $data);
echo $OUTPUT->footer();
