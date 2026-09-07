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
 * Visual project editor.
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
$project = $repository->get($projectid, $USER->id, true);
if ($project->status === 'archived') {
    throw new moodle_exception('projectisarchived', 'local_interactembedcreator');
}
$context = context::instance_by_id($project->contextid, MUST_EXIST);
library_context::validate($context);
library_context::require_login($context);
$course = library_context::page_course($context);
$document = $repository->get_current_document($project);
$assetrecords = get_file_storage()->get_area_files(
    $context->id,
    'local_interactembedcreator',
    'asset',
    $project->id,
    'filepath, filename',
    false
);
$assets = [];
foreach ($assetrecords as $asset) {
    $path = ltrim($asset->get_filepath(), '/') . $asset->get_filename();
    $assets[] = [
        'path' => $path,
        'name' => $asset->get_filename(),
        'mimetype' => $asset->get_mimetype(),
        'url' => moodle_url::make_pluginfile_url(
            $context->id,
            'local_interactembedcreator',
            'asset',
            $project->id,
            $asset->get_filepath(),
            $asset->get_filename()
        )->out(false),
    ];
}

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]));
$PAGE->set_title(format_string($project->name));
$PAGE->set_heading(format_string($project->name));
$PAGE->add_body_class('local-interactembedcreator-editor');
$PAGE->requires->css('/local/interactembedcreator/styles.css');
$PAGE->requires->js_call_amd('local_interactembedcreator/editor', 'init', [[
    'projectId' => (int) $project->id,
    'document' => $document,
    'assets' => $assets,
    'strings' => [
        'scene' => get_string('scene', 'local_interactembedcreator', '__NUMBER__'),
        'saving' => get_string('saving', 'local_interactembedcreator'),
        'saved' => get_string('saved', 'local_interactembedcreator'),
        'saveconflict' => get_string('saveconflict', 'local_interactembedcreator'),
        'selectelement' => get_string('selectelement', 'local_interactembedcreator'),
        'selectedcount' => get_string('selectedcount', 'local_interactembedcreator'),
        'buttondefault' => get_string('buttondefault', 'local_interactembedcreator'),
        'checkanswer' => get_string('checkanswer', 'local_interactembedcreator'),
        'quizdefaultquestion' => get_string('quizdefaultquestion', 'local_interactembedcreator'),
        'quizdefaultanswer1' => get_string('quizdefaultanswer1', 'local_interactembedcreator'),
        'quizdefaultanswer2' => get_string('quizdefaultanswer2', 'local_interactembedcreator'),
        'quizfeedbackcorrect' => get_string('quizfeedbackcorrect', 'local_interactembedcreator'),
        'quizfeedbackincorrect' => get_string('quizfeedbackincorrect', 'local_interactembedcreator'),
        'quizcorrectinvalid' => get_string('quizcorrectinvalid', 'local_interactembedcreator'),
        'preview' => get_string('preview', 'local_interactembedcreator'),
        'exitpreview' => get_string('exitpreview', 'local_interactembedcreator'),
        'copysuffix' => get_string('copysuffix', 'local_interactembedcreator'),
        'deletesceneconfirm' => get_string('deletesceneconfirm', 'local_interactembedcreator'),
        'deleteelementsconfirm' => get_string('deleteelementsconfirm', 'local_interactembedcreator'),
        'deleteelement' => get_string('deleteelement', 'local_interactembedcreator'),
        'confirm' => get_string('confirm'),
        'noimage' => get_string('noimage', 'local_interactembedcreator'),
        'linkinvalid' => get_string('linkinvalid', 'local_interactembedcreator'),
        'bringforward' => get_string('bringforward', 'local_interactembedcreator'),
        'sendbackward' => get_string('sendbackward', 'local_interactembedcreator'),
        'targetscenenone' => get_string('targetscenenone', 'local_interactembedcreator'),
        'true' => get_string('true', 'local_interactembedcreator'),
        'false' => get_string('false', 'local_interactembedcreator'),
        'noasset' => get_string('noasset', 'local_interactembedcreator'),
        'accessibilitypassed' => get_string('accessibilitypassed', 'local_interactembedcreator'),
        'accessibilityissues' => get_string('accessibilityissues', 'local_interactembedcreator'),
        'issueaudiotranscript' => get_string('issueaudiotranscript', 'local_interactembedcreator'),
        'issuebuttontext' => get_string('issuebuttontext', 'local_interactembedcreator'),
        'issueimagealt' => get_string('issueimagealt', 'local_interactembedcreator'),
        'issuelabel' => get_string('issuelabel', 'local_interactembedcreator'),
        'issuevideocaptions' => get_string('issuevideocaptions', 'local_interactembedcreator'),
        'text' => get_string('elementtext', 'local_interactembedcreator'),
        'button' => get_string('elementbutton', 'local_interactembedcreator'),
        'shape' => get_string('elementshape', 'local_interactembedcreator'),
        'quiz' => get_string('elementquiz', 'local_interactembedcreator'),
        'image' => get_string('elementimage', 'local_interactembedcreator'),
        'audio' => get_string('elementaudio', 'local_interactembedcreator'),
        'video' => get_string('elementvideo', 'local_interactembedcreator'),
        'focusmode' => get_string('focusmode', 'local_interactembedcreator'),
        'exitfocusmode' => get_string('exitfocusmode', 'local_interactembedcreator'),
    ],
]]);

$data = [
    'projectid' => (int) $project->id,
    'sesskey' => sesskey(),
    'name' => format_string($project->name),
    'mediaurl' => (new moodle_url('/local/interactembedcreator/media.php', ['id' => $project->id]))->out(false),
    'guideurl' => (new moodle_url(
        '/local/interactembedcreator/guide.php',
        library_context::url_params($context)
    ))->out(false),
    'publicationsurl' => (new moodle_url('/local/interactembedcreator/publications.php', ['id' => $project->id]))->out(false),
    'libraryurl' => (new moodle_url(
        '/local/interactembedcreator/index.php',
        library_context::url_params($context)
    ))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_interactembedcreator/editor', $data);
echo $OUTPUT->footer();
