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
 * Context-aware InteractEmbed project library.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\library_context;
use local_interactembedcreator\local\project_repository;

require_login();
$repository = new project_repository();
$scope = optional_param('scope', 'context', PARAM_ALPHA) === 'my' ? 'my' : 'context';
$context = library_context::from_request();
if ($scope === 'context' && !has_capability('local/interactembedcreator:view', $context)) {
    if (!optional_param('contextid', 0, PARAM_INT) && !optional_param('courseid', 0, PARAM_INT)) {
        $scope = 'my';
    } else {
        require_capability('local/interactembedcreator:view', $context);
    }
}
if ($scope === 'context') {
    library_context::require_login($context);
    require_capability('local/interactembedcreator:view', $context);
}

$pagecourse = library_context::page_course($context);
$baseparams = $scope === 'my' ? ['scope' => 'my'] : library_context::url_params($context);
$PAGE->set_course($pagecourse);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/index.php', $baseparams));
$PAGE->set_title(get_string('library', 'local_interactembedcreator'));
$PAGE->set_heading($scope === 'my' ? get_string('myprojects', 'local_interactembedcreator') :
    library_context::label($context));
$PAGE->add_body_class('local-interactembedcreator-library');

$createcontexts = library_context::available($USER->id, 'local/interactembedcreator:create');
$viewcontexts = library_context::available($USER->id, 'local/interactembedcreator:view');
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'create') {
    require_sesskey();
    $targetcontext = context::instance_by_id(required_param('targetcontextid', PARAM_INT), MUST_EXIST);
    library_context::validate($targetcontext);
    $project = $repository->create(
        $targetcontext,
        $USER->id,
        required_param('name', PARAM_TEXT),
        optional_param('template', 'blank', PARAM_ALPHA)
    );
    redirect(
        new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]),
        get_string('projectcreated', 'local_interactembedcreator'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
if (in_array($action, ['archive', 'restore', 'duplicate', 'copy', 'rename'], true)) {
    require_sesskey();
    $projectid = required_param('id', PARAM_INT);
    if (in_array($action, ['duplicate', 'copy'], true)) {
        $source = $repository->get($projectid, $USER->id);
        $targetcontext = $action === 'copy'
            ? context::instance_by_id(required_param('targetcontextid', PARAM_INT), MUST_EXIST)
            : context::instance_by_id($source->contextid, MUST_EXIST);
        library_context::validate($targetcontext);
        $copy = $repository->duplicate(
            $projectid,
            $USER->id,
            get_string('projectcopyname', 'local_interactembedcreator', $source->name),
            $targetcontext
        );
        redirect(
            new moodle_url('/local/interactembedcreator/edit.php', ['id' => $copy->id]),
            get_string(
                $action === 'copy' ? 'projectcopiedtocontext' : 'projectduplicated',
                'local_interactembedcreator'
            ),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    if ($action === 'rename') {
        $repository->rename($projectid, $USER->id, required_param('name', PARAM_TEXT));
        redirect(
            new moodle_url('/local/interactembedcreator/index.php', $baseparams),
            get_string('projectrenamed', 'local_interactembedcreator'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $repository->set_archived($projectid, $USER->id, $action === 'archive');
    redirect(new moodle_url('/local/interactembedcreator/index.php', $baseparams + [
        'view' => $action === 'restore' ? 'archived' : 'active',
    ]), get_string(
        $action === 'archive' ? 'projectarchived' : 'projectrestored',
        'local_interactembedcreator'
    ), null, \core\output\notification::NOTIFY_SUCCESS);
}

$archivedview = optional_param('view', 'active', PARAM_ALPHA) === 'archived';
$search = optional_param('search', '', PARAM_TEXT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 12;
if ($scope === 'my') {
    $allprojects = array_values($repository->list_owned($USER->id, $archivedview, $search));
    $total = count($allprojects);
    $projects = array_slice($allprojects, $page * $perpage, $perpage);
} else {
    $total = $repository->count($context, $USER->id, $archivedview, $search);
    $projects = $repository->list($context, $USER->id, $archivedview, $search, $page * $perpage, $perpage);
}

$contextoptions = [];
foreach ($viewcontexts as $availablecontext) {
    $contextoptions[] = ['id' => $availablecontext->id, 'label' => library_context::label($availablecontext),
        'selected' => $scope === 'context' && $availablecontext->id === $context->id];
}
$createoptions = [];
foreach ($createcontexts as $availablecontext) {
    $createoptions[] = ['id' => $availablecontext->id, 'label' => library_context::label($availablecontext),
        'selected' => $scope === 'context' && $availablecontext->id === $context->id];
}

$cards = [];
foreach ($projects as $project) {
    $projectcontext = context::instance_by_id($project->contextid, MUST_EXIST);
    $canedit = has_capability('local/interactembedcreator:editany', $projectcontext) ||
        ($project->ownerid == $USER->id && has_capability('local/interactembedcreator:editown', $projectcontext));
    $copycontexts = [];
    foreach ($createcontexts as $targetcontext) {
        if ($targetcontext->id !== $projectcontext->id) {
            $copycontexts[] = ['id' => $targetcontext->id, 'label' => library_context::label($targetcontext)];
        }
    }
    $cards[] = [
        'id' => $project->id,
        'name' => format_string($project->name),
        'contextlabel' => library_context::label($projectcontext),
        'status' => get_string($project->status === 'published' ? 'statuspublished' :
            ($project->status === 'archived' ? 'statusarchived' : 'statusdraft'), 'local_interactembedcreator'),
        'modified' => userdate($project->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        'editurl' => (new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]))->out(false),
        'historyurl' => (new moodle_url('/local/interactembedcreator/revisions.php', ['id' => $project->id]))->out(false),
        'exporturl' => (new moodle_url('/local/interactembedcreator/export.php', [
            'id' => $project->id, 'sesskey' => sesskey(),
        ]))->out(false),
        'isarchived' => $project->status === 'archived',
        'canopen' => $project->status !== 'archived' && $canedit,
        'canmanage' => $project->status !== 'archived' && $canedit,
        'canduplicate' => $project->status !== 'archived' && isset($createcontexts[$projectcontext->id]),
        'canrestore' => $project->status === 'archived' && $canedit,
        'cancopy' => $project->status !== 'archived' && count($copycontexts) > 0,
        'copycontexts' => $copycontexts,
    ];
}

$auxcontext = $scope === 'context' ? $context : (reset($viewcontexts) ?: context_system::instance());
$importcontext = isset($createcontexts[$context->id]) ? $context : (reset($createcontexts) ?: $auxcontext);
$data = [
    'contextid' => $context->id,
    'scope' => $scope,
    'myscope' => $scope === 'my',
    'contextscope' => $scope === 'context',
    'contextlabel' => $scope === 'my' ? get_string('myprojects', 'local_interactembedcreator') :
        library_context::label($context),
    'contextoptions' => $contextoptions,
    'createcontexts' => $createoptions,
    'hascreatecontexts' => count($createoptions) > 0,
    'sesskey' => sesskey(),
    'projects' => $cards,
    'hasprojects' => count($cards) > 0,
    'cancreate' => count($createoptions) > 0,
    'projectcount' => get_string(
        $total === 1 ? 'projectcountone' : 'projectcount',
        'local_interactembedcreator',
        $total
    ),
    'myurl' => (new moodle_url('/local/interactembedcreator/index.php', ['scope' => 'my']))->out(false),
    'guideurl' => (new moodle_url(
        '/local/interactembedcreator/guide.php',
        library_context::url_params($auxcontext)
    ))->out(false),
    'showcaseurl' => (new moodle_url(
        '/local/interactembedcreator/showcase.php',
        library_context::url_params($auxcontext)
    ))->out(false),
    'archivedview' => $archivedview,
    'activeview' => !$archivedview,
    'activeurl' => (new moodle_url('/local/interactembedcreator/index.php', $baseparams))->out(false),
    'archivedurl' => (new moodle_url(
        '/local/interactembedcreator/index.php',
        $baseparams + ['view' => 'archived']
    ))->out(false),
    'importurl' => (new moodle_url(
        '/local/interactembedcreator/import.php',
        library_context::url_params($importcontext)
    ))->out(false),
    'search' => s($search),
    'searching' => trim($search) !== '',
    'showcaseprojectname' => get_string('showcaseprojectname', 'local_interactembedcreator'),
    'paging' => $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url(
        '/local/interactembedcreator/index.php',
        $baseparams + [
            'view' => $archivedview ? 'archived' : 'active', 'search' => $search,
        ]
    )),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_interactembedcreator/library', $data);
echo $OUTPUT->footer();
