<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_interactembedcreator;

use local_interactembedcreator\local\project_repository;
use local_interactembedcreator\local\publication_builder;

/**
 * Tests course backup and restore of Creator projects and publications.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_interactembedcreator\local\project_repository
 * @covers    \local_interactembedcreator\local\publication_builder
 * @covers    \local_interactembedcreator\publication_api
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Deleting Moodle containers removes their complete Creator project graphs.
     */
    public function test_course_and_category_deletion_remove_projects(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $repository = new project_repository();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $courseproject = $repository->create($coursecontext, $USER->id, 'Course lifecycle project');
        $coursepublication = (new publication_builder())->publish($courseproject, $USER->id);

        delete_course($course, false);

        $this->assertFalse($DB->record_exists('local_interactembedcreator_project', ['id' => $courseproject->id]));
        $this->assertFalse($DB->record_exists('local_interactembedcreator_revision', [
            'projectid' => $courseproject->id,
        ]));
        $this->assertFalse($DB->record_exists('local_interactembedcreator_publication', [
            'id' => $coursepublication->id,
        ]));

        $categoryrecord = $this->getDataGenerator()->create_category();
        $categorycontext = \context_coursecat::instance($categoryrecord->id);
        $categoryproject = $repository->create($categorycontext, $USER->id, 'Category lifecycle project');
        $categorypublication = (new publication_builder())->publish($categoryproject, $USER->id);

        \core_course_category::get($categoryrecord->id)->delete_full(false);

        $this->assertFalse($DB->record_exists('local_interactembedcreator_project', ['id' => $categoryproject->id]));
        $this->assertFalse($DB->record_exists('local_interactembedcreator_revision', [
            'projectid' => $categoryproject->id,
        ]));
        $this->assertFalse($DB->record_exists('local_interactembedcreator_publication', [
            'id' => $categorypublication->id,
        ]));
    }

    /**
     * A course-scoped project, its history, files, and publications follow the course.
     */
    public function test_course_project_backup_and_restore(): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $repository = new project_repository();
        $project = $repository->create($context, $USER->id, 'Portable Creator project');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $project->id,
            'filepath' => '/',
            'filename' => 'portable.txt',
        ], 'Portable asset');
        $publication = (new publication_builder())->publish($project, $USER->id);

        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $backup = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backupresults = $backup->get_results();
        $restoreid = 'local_interactembedcreator_test';
        $backuptempdir = make_backup_temp_directory('');
        $backupresults['backup_destination']->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            $backuptempdir . '/' . $restoreid
        );
        $backup->destroy();

        $restoredcourse = $this->getDataGenerator()->create_course();
        $restore = new \restore_controller(
            $restoreid,
            $restoredcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $restoredcontext = \context_course::instance($restoredcourse->id);
        $restoredproject = $DB->get_record(
            'local_interactembedcreator_project',
            ['contextid' => $restoredcontext->id],
            '*',
            MUST_EXIST
        );
        $this->assertSame('Portable Creator project', $restoredproject->name);
        $this->assertNotSame($project->uuid, $restoredproject->uuid);
        $restoredrevision = $DB->get_record(
            'local_interactembedcreator_revision',
            ['projectid' => $restoredproject->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals($restoredrevision->id, $restoredproject->currentrevision);
        $restoreddocument = json_decode($restoredrevision->contentjson, false, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($restoredproject->uuid, $restoreddocument->projectId);
        $this->assertTrue(get_file_storage()->file_exists(
            $restoredcontext->id,
            'local_interactembedcreator',
            'asset',
            $restoredproject->id,
            '/',
            'portable.txt'
        ));

        $restoredpublication = $DB->get_record(
            'local_interactembedcreator_publication',
            ['projectid' => $restoredproject->id],
            '*',
            MUST_EXIST
        );
        $this->assertNotSame($publication->uuid, $restoredpublication->uuid);
        $snapshot = publication_api::get_snapshot($restoredpublication->id, $restoredcontext);
        $this->assertSame($restoredpublication->packagehash, $snapshot->packagehash);
        $projectjson = json_decode($snapshot->files['project.json']->get_content(), false, 512, JSON_THROW_ON_ERROR);
        $manifest = json_decode(
            $snapshot->files['interactembed-manifest.json']->get_content(),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertSame($restoredproject->uuid, $projectjson->projectId);
        $this->assertSame($restoredpublication->uuid, $manifest->publicationId);
        $this->assertArrayHasKey('assets/portable.txt', $snapshot->files);
    }
}
