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

namespace local_interactembedcreator\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form for importing one editable Creator source package.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_form extends moodleform {
    /**
     * Defines the import form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('projectname', 'local_interactembedcreator'), ['maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addElement('filepicker', 'sourcefile', get_string('sourcepackage', 'local_interactembedcreator'), null, [
            'accepted_types' => ['.zip'],
            'maxbytes' => 104857600,
        ]);
        $mform->addRule('sourcefile', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('importproject', 'local_interactembedcreator'));
    }
}
