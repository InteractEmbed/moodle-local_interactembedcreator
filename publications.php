<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Versioned publication management.
 *
 * @package local_interactembedcreator
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\project_repository;
use local_interactembedcreator\local\publication_manager;
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
$PAGE->set_url(new moodle_url('/local/interactembedcreator/publications.php', ['id' => $project->id]));
$PAGE->set_title(get_string('publications', 'local_interactembedcreator'));
$PAGE->set_heading(format_string($project->name));
$manager = new publication_manager();

$action = optional_param('action', '', PARAM_ALPHA);
if (in_array($action, ['withdraw', 'enable'], true)) {
    require_sesskey();
    $manager->set_available(required_param('publicationid', PARAM_INT), $USER->id, $action === 'enable');
    redirect(
        new moodle_url('/local/interactembedcreator/publications.php', ['id' => $project->id]),
        get_string($action === 'enable' ? 'publicationenabled' : 'publicationwithdrawn', 'local_interactembedcreator'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$canpublish = has_capability('local/interactembedcreator:publish', $context);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 25;
$total = $manager->count($project->id, $USER->id);
$items = [];
foreach ($manager->list($project->id, $USER->id, $page * $perpage, $perpage) as $publication) {
    $ready = $publication->status === 'ready';
    $items[] = [
        'id' => $publication->id,
        'projectid' => $project->id,
        'number' => $publication->publicationno,
        'status' => get_string($ready ? 'publicationready' : 'publicationwithdrawnstatus', 'local_interactembedcreator'),
        'ready' => $ready,
        'withdrawn' => !$ready,
        'canmanage' => $canpublish,
        'engine' => $publication->engineversion,
        'hash' => $publication->packagehash,
        'created' => userdate($publication->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'downloadurl' => (new moodle_url('/local/interactembedcreator/download.php', [
            'id' => $publication->id,
            'sesskey' => sesskey(),
        ]))->out(false),
        'previewurl' => moodle_url::make_pluginfile_url(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/',
            $publication->entrypoint
        )->out(false),
    ];
}
$data = [
    'id' => $project->id,
    'sesskey' => sesskey(),
    'publications' => $items,
    'haspublications' => count($items) > 0,
    'editurl' => (new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]))->out(false),
    'paging' => $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url(
        '/local/interactembedcreator/publications.php',
        ['id' => $project->id]
    )),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_interactembedcreator/publications', $data);
echo $OUTPUT->footer();
