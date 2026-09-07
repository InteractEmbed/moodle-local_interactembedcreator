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
 * Integrated trilingual guide for the InteractEmbed Creator.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Login is enforced immediately below by library_context::require_login().
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\local\library_context;

$context = library_context::from_request();
library_context::require_login($context);
$course = library_context::page_course($context);
require_capability('local/interactembedcreator:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/guide.php', library_context::url_params($context)));
$PAGE->set_title(get_string('guide', 'local_interactembedcreator'));
$PAGE->set_heading(format_string($course->fullname));

$steps = [];
for ($i = 1; $i <= 5; $i++) {
    $steps[] = [
        'title' => get_string('guidestep' . $i . 'title', 'local_interactembedcreator'),
        'body' => get_string('guidestep' . $i, 'local_interactembedcreator'),
    ];
}
$data = [
    'intro' => get_string('guideintro', 'local_interactembedcreator'),
    'steps' => $steps,
    'libraryurl' => (new moodle_url(
        '/local/interactembedcreator/index.php',
        library_context::url_params($context)
    ))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_interactembedcreator/guide', $data);
echo $OUTPUT->footer();
