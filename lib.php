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
 * Library callbacks for the InteractEmbed Creator.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a site-wide entry to the user's own contextual projects.
 *
 * @param global_navigation $navigation
 */
function local_interactembedcreator_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (
        !has_capability('local/interactembedcreator:view', context_system::instance()) &&
            !get_user_capability_course('local/interactembedcreator:view', null, true, '', '', 1)
    ) {
        return;
    }
    $navigation->add(
        get_string('library', 'local_interactembedcreator'),
        new moodle_url('/local/interactembedcreator/index.php', ['scope' => 'my']),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_interactembedcreator_global',
        new pix_icon('i/contentbank', '')
    );
}

/**
 * Adds the library to the course secondary navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_interactembedcreator_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/interactembedcreator:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('library', 'local_interactembedcreator'),
        new moodle_url('/local/interactembedcreator/index.php', ['courseid' => $course->id]),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_interactembedcreator',
        new pix_icon('i/contentbank', '')
    );
}

/**
 * Serves project assets and immutable publication files.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_interactembedcreator_pluginfile(
    stdClass $course,
    ?stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    if (!in_array($filearea, ['asset', 'thumbnail', 'publication'], true)) {
        return false;
    }

    require_login();
    require_capability('local/interactembedcreator:view', $context);

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'local_interactembedcreator',
        $filearea,
        $itemid,
        $filepath,
        $filename
    );

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 3600, 0, $forcedownload, $options);
}
