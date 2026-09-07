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
 * Upgrade steps for the InteractEmbed Creator.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs local_interactembedcreator upgrades.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_interactembedcreator_upgrade(int $oldversion): bool {
    if ($oldversion < 2026090101) {
        upgrade_plugin_savepoint(true, 2026090101, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090102) {
        upgrade_plugin_savepoint(true, 2026090102, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090103) {
        upgrade_plugin_savepoint(true, 2026090103, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090104) {
        upgrade_plugin_savepoint(true, 2026090104, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090105) {
        upgrade_plugin_savepoint(true, 2026090105, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090106) {
        upgrade_plugin_savepoint(true, 2026090106, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090107) {
        upgrade_plugin_savepoint(true, 2026090107, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090108) {
        upgrade_plugin_savepoint(true, 2026090108, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090109) {
        upgrade_plugin_savepoint(true, 2026090109, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090110) {
        upgrade_plugin_savepoint(true, 2026090110, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090111) {
        upgrade_plugin_savepoint(true, 2026090111, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090112) {
        upgrade_plugin_savepoint(true, 2026090112, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090113) {
        upgrade_plugin_savepoint(true, 2026090113, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090114) {
        upgrade_plugin_savepoint(true, 2026090114, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090200) {
        upgrade_plugin_savepoint(true, 2026090200, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090201) {
        upgrade_plugin_savepoint(true, 2026090201, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090202) {
        upgrade_plugin_savepoint(true, 2026090202, 'local', 'interactembedcreator');
    }
    if ($oldversion < 2026090205) {
        upgrade_plugin_savepoint(true, 2026090205, 'local', 'interactembedcreator');
    }
    return true;
}
