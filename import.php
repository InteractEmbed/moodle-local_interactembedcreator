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
 * Imports an editable Creator source package.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Login is enforced immediately below by library_context::require_login().
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_interactembedcreator\form\import_form;
use local_interactembedcreator\local\library_context;
use local_interactembedcreator\local\source_package;

$context = library_context::from_request();
library_context::require_login($context);
$course = library_context::page_course($context);
require_capability('local/interactembedcreator:create', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/interactembedcreator/import.php', library_context::url_params($context)));
$PAGE->set_title(get_string('importproject', 'local_interactembedcreator'));
$PAGE->set_heading(format_string($course->fullname));
$form = new import_form();
$libraryurl = new moodle_url('/local/interactembedcreator/index.php', library_context::url_params($context));
if ($form->is_cancelled()) {
    redirect($libraryurl);
} else if ($data = $form->get_data()) {
    $archive = $form->save_temp_file('sourcefile');
    if ($archive === false) {
        throw new moodle_exception('invalidsourcepackage', 'local_interactembedcreator');
    }
    $project = (new source_package())->import($archive, $context, $USER->id, $data->name ?? null);
    redirect(
        new moodle_url('/local/interactembedcreator/edit.php', ['id' => $project->id]),
        get_string('projectimported', 'local_interactembedcreator'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importproject', 'local_interactembedcreator'));
echo html_writer::tag('p', get_string('importprojecthelp', 'local_interactembedcreator'));
$form->display();
echo $OUTPUT->footer();
