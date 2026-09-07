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
 * Administrative settings for the InteractEmbed Creator.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_interactembedcreator',
        get_string('pluginname', 'local_interactembedcreator')
    );
    $settings->add(new admin_setting_configtext(
        'local_interactembedcreator/maxprojectsperuser',
        get_string('maxprojectsperuser', 'local_interactembedcreator'),
        get_string('maxprojectsperuser_help', 'local_interactembedcreator'),
        100,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_interactembedcreator/maxrevisions',
        get_string('maxrevisions', 'local_interactembedcreator'),
        get_string('maxrevisions_help', 'local_interactembedcreator'),
        25,
        PARAM_INT
    ));
    $ADMIN->add('localplugins', $settings);
}
