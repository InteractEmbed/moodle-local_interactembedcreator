<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Read-only bundled Creator mini-course.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\library_context;

$context = library_context::from_request();
library_context::require_login($context);
$course = library_context::page_course($context);
require_capability('local/interactembedcreator:view', $context);

$lang = substr(current_language(), 0, 2);
$lang = in_array($lang, ['en', 'fr', 'es'], true) ? $lang : 'en';
$playerurl = new moodle_url('/local/interactembedcreator/showcase/' . $lang . '/index.html', [
    'v' => 2026090209,
]);
$libraryurl = new moodle_url('/local/interactembedcreator/index.php', library_context::url_params($context));

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/showcase.php', library_context::url_params($context)));
$PAGE->set_title(get_string('showcaseheadline', 'local_interactembedcreator'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('local-interactembedcreator-showcase');

$shellid = 'iec-showcase-shell';
$playerid = 'iec-showcase-player';
$fullscreenid = 'iec-showcase-fullscreen';
$PAGE->requires->js_call_amd('local_interactembedcreator/showcase', 'init', [[
    'shellid' => $shellid,
    'playerid' => $playerid,
    'buttonid' => $fullscreenid,
    'enterlabel' => get_string('showcasefullscreen', 'local_interactembedcreator'),
    'exitlabel' => get_string('showcaseexitfullscreen', 'local_interactembedcreator'),
]]);

echo $OUTPUT->header();
echo html_writer::link($libraryurl, get_string('backtolibrary', 'local_interactembedcreator'), [
    'class' => 'btn btn-link mb-3',
]);
echo $OUTPUT->heading(get_string('showcaseheadline', 'local_interactembedcreator'), 2);
echo html_writer::tag('p', get_string('showcasecarddescription', 'local_interactembedcreator'));
echo html_writer::start_div('iec-showcase-player-shell', ['id' => $shellid]);
echo html_writer::start_div('iec-showcase-player-toolbar');
echo html_writer::tag('button',
    $OUTPUT->pix_icon('e/fullscreen', '') . html_writer::span(
        get_string('showcasefullscreen', 'local_interactembedcreator'),
        'iec-showcase-fullscreen-label'
    ), [
        'id' => $fullscreenid,
        'class' => 'btn btn-secondary btn-sm',
        'type' => 'button',
        'aria-controls' => $playerid,
        'aria-pressed' => 'false',
    ]);
echo html_writer::end_div();
echo html_writer::tag('iframe', '', [
    'id' => $playerid,
    'class' => 'iec-showcase-player',
    'src' => $playerurl->out(false),
    'title' => get_string('showcaseheadline', 'local_interactembedcreator'),
    'allowfullscreen' => 'allowfullscreen',
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
