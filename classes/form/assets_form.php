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

namespace local_interactembedcreator\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle File Manager form for project media.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assets_form extends moodleform {
    /**
     * Defines the project media form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('filemanager', 'assets', get_string('assets', 'local_interactembedcreator'), null, [
            'accepted_types' => ['image', 'audio', 'video', '.vtt'],
            'maxbytes' => 0,
            'maxfiles' => 200,
            'subdirs' => true,
        ]);
        $mform->addElement('static', 'assethelp', '', get_string('uploadmedia', 'local_interactembedcreator'));
        $mform->addElement(
            'static',
            'imagemediaguidance',
            '',
            get_string('imagemediaguidance', 'local_interactembedcreator')
        );
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
