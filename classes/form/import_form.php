<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_interactembedcreator\form;

use moodleform;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Moodle form for importing one editable Creator source package. */
final class import_form extends moodleform {
    /** Defines the form. */
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
