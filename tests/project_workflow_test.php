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

namespace local_interactembedcreator;

use advanced_testcase;
use context_course;
use context_coursecat;
use context_system;
use local_interactembedcreator\local\project_repository;
use local_interactembedcreator\local\publication_builder;
use local_interactembedcreator\local\publication_manager;
use local_interactembedcreator\local\source_package;
use local_interactembedcreator\publication_api;

/**
 * End-to-end persistence and publication tests.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_interactembedcreator\local\project_repository
 * @covers    \local_interactembedcreator\local\publication_builder
 * @covers    \local_interactembedcreator\publication_api
 */
final class project_workflow_test extends advanced_testcase {
    /**
     * System-library files accept Moodle's null course callback argument.
     */
    public function test_pluginfile_accepts_system_context_without_course(): void {
        global $CFG;

        require_once($CFG->dirroot . '/local/interactembedcreator/lib.php');
        $this->assertFalse(\local_interactembedcreator_pluginfile(
            null,
            null,
            context_system::instance(),
            'unknown',
            [],
            false
        ));
    }

    /**
     * A project can be edited and published with a Moodle Files API asset.
     */
    public function test_project_can_be_saved_and_published(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $repository = new project_repository();
        $project = $repository->create($context, $USER->id, 'Test presentation');
        $document = $repository->get_current_document($project);

        $assetpath = 'illustration.svg';
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $project->id,
            'filepath' => '/',
            'filename' => $assetpath,
        ], '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $project->id,
            'filepath' => '/',
            'filename' => 'captions.vtt',
        ], "WEBVTT\n\n00:00.000 --> 00:01.000\nCaption\n");
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $project->id,
            'filepath' => '/',
            'filename' => 'lesson.mp4',
        ], 'test-video');
        $document->scenes[0]->style = (object) [
            'backgroundColor' => '#112233',
            'backgroundImage' => $assetpath,
        ];
        $document->scenes[0]->elements[0]->content->href = 'https://example.com/learn';
        $document->scenes[0]->elements[0]->style->fontWeight = 'bold';
        $document->scenes[0]->elements[] = (object) [
            'id' => project_repository::uuid(),
            'type' => 'image',
            'transform' => (object) ['x' => 100, 'y' => 300, 'width' => 800, 'height' => 500, 'rotation' => 0],
            'content' => (object) ['assetPath' => $assetpath],
            'style' => (object) [],
            'accessibility' => (object) ['label' => 'Illustration', 'alt' => 'Illustration'],
        ];
        $document->scenes[0]->elements[] = (object) [
            'id' => project_repository::uuid(),
            'type' => 'video',
            'transform' => (object) ['x' => 900, 'y' => 300, 'width' => 800, 'height' => 500, 'rotation' => 0],
            'content' => (object) [
                'assetPath' => 'lesson.mp4',
                'controls' => true,
                'loop' => false,
                'muted' => false,
                'posterPath' => $assetpath,
                'captionsPath' => 'captions.vtt',
                'transcript' => 'Accessible transcript.',
            ],
            'style' => (object) [],
            'accessibility' => (object) ['label' => 'Lesson video'],
        ];
        $document->settings->navigation = false;
        $document->scenes[0]->elements[] = (object) [
            'id' => project_repository::uuid(),
            'type' => 'button',
            'transform' => (object) ['x' => 100, 'y' => 900, 'width' => 400, 'height' => 100, 'rotation' => 0],
            'content' => (object) [
                'text' => 'Branch',
                'action' => 'scene',
                'targetSceneId' => $document->scenes[0]->id,
                'href' => '',
            ],
            'style' => (object) [],
            'accessibility' => (object) ['label' => 'Branch'],
        ];
        $document->scenes[0]->elements[] = (object) [
            'id' => project_repository::uuid(),
            'type' => 'quiz',
            'transform' => (object) ['x' => 600, 'y' => 600, 'width' => 800, 'height' => 400, 'rotation' => 0],
            'content' => (object) [
                'question' => 'Select two answers',
                'answers' => ['First', 'Second', 'Third'],
                'selectionMode' => 'multiple',
                'correctIndex' => 0,
                'correctIndexes' => [0, 2],
                'points' => 3,
                'checkLabel' => 'Check',
                'correctFeedback' => 'Correct',
                'incorrectFeedback' => 'Try again',
                'correctSceneId' => $document->scenes[0]->id,
                'incorrectSceneId' => '',
            ],
            'style' => (object) [],
            'accessibility' => (object) ['label' => 'Select two answers'],
        ];
        $lockversion = $document->_lockversion;
        unset($document->_revision, $document->_lockversion);
        $result = $repository->save(
            $project->id,
            $USER->id,
            json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $lockversion,
            false
        );
        $this->assertTrue($result['changed']);
        $this->assertSame(2, $result['revision']);

        $project = $DB->get_record('local_interactembedcreator_project', ['id' => $project->id], '*', MUST_EXIST);
        $publication = (new publication_builder())->publish($project, $USER->id);
        $this->assertSame('ready', $publication->status);
        $this->assertNotEmpty($publication->packagehash);
        $fs = get_file_storage();
        $this->assertNotFalse($fs->get_file(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/',
            'index.html'
        ));
        $this->assertNotFalse($fs->get_file(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/assets/',
            $assetpath
        ));
        $projectfile = $fs->get_file(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/',
            'project.json'
        );
        $this->assertNotFalse($projectfile);
        $publishedjson = $projectfile->get_content();
        $this->assertStringContainsString('"backgroundImage": "illustration.svg"', $publishedjson);
        $this->assertStringContainsString('"href": "https://example.com/learn"', $publishedjson);
        $this->assertStringContainsString('"selectionMode": "multiple"', $publishedjson);
        $this->assertStringContainsString('"correctIndexes": [', $publishedjson);
        $this->assertStringContainsString('"navigation": false', $publishedjson);
        $this->assertStringContainsString('"captionsPath": "captions.vtt"', $publishedjson);
        $this->assertStringContainsString('"transcript": "Accessible transcript."', $publishedjson);
        $playerfile = $fs->get_file(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/',
            'player.js'
        );
        $this->assertNotFalse($playerfile);
        $player = $playerfile->get_content();
        $this->assertStringContainsString("fetch('project.json', {credentials: 'same-origin'})", $player);
        $this->assertStringContainsString('projectIsReady(project)', $player);
        $this->assertStringContainsString('event.source === window.parent', $player);
        $this->assertStringContainsString("typeof data.nonce === 'string'", $player);
        $this->assertStringContainsString('fitElementText(node', $player);
        $manifestfile = $fs->get_file(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/',
            'interactembed-manifest.json'
        );
        $this->assertNotFalse($manifestfile);
        $manifest = json_decode($manifestfile->get_content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($manifest['capabilities']['score']);
        $this->assertTrue($manifest['capabilities']['branching']);
        $this->assertSame('0.4.2', $manifest['runtime']['version']);
        $listed = publication_api::list_for_context($context);
        $this->assertCount(1, $listed);
        $snapshot = publication_api::get_snapshot($publication->id, $context);
        $this->assertSame($publication->packagehash, $snapshot->packagehash);
        $this->assertArrayHasKey('index.html', $snapshot->files);
        $this->assertArrayHasKey('assets/' . $assetpath, $snapshot->files);
        $this->assertArrayHasKey('assets/captions.vtt', $snapshot->files);
        $this->assertArrayHasKey('assets/lesson.mp4', $snapshot->files);
    }

    /**
     * Traversal paths in media references are rejected.
     */
    public function test_schema_rejects_asset_traversal(): void {
        $document = [
            'schema' => 'interactembed-project',
            'version' => 1,
            'projectId' => 'project-test-id',
            'canvas' => ['width' => 1920, 'height' => 1080, 'aspectRatio' => '16:9'],
            'scenes' => [[
                'id' => 'scene-test-id',
                'elements' => [[
                    'id' => 'element-test-id',
                    'type' => 'image',
                    'transform' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'rotation' => 0],
                    'content' => ['assetPath' => '../secret.txt'],
                ]],
            ]],
        ];
        $this->expectException(\moodle_exception::class);
        project_repository::validate_document(
            json_encode($document, JSON_THROW_ON_ERROR),
            'project-test-id'
        );
    }

    /**
     * Executable and malformed text links are rejected.
     */
    public function test_schema_rejects_unsafe_text_link(): void {
        $document = [
            'schema' => 'interactembed-project',
            'version' => 1,
            'projectId' => 'project-test-id',
            'canvas' => ['width' => 1920, 'height' => 1080, 'aspectRatio' => '16:9'],
            'scenes' => [[
                'id' => 'scene-test-id',
                'elements' => [[
                    'id' => 'element-test-id',
                    'type' => 'text',
                    'transform' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'rotation' => 0],
                    'content' => ['text' => 'Unsafe', 'href' => 'javascript:alert(1)'],
                ]],
            ]],
        ];
        $this->expectException(\moodle_exception::class);
        project_repository::validate_document(
            json_encode($document, JSON_THROW_ON_ERROR),
            'project-test-id'
        );
    }

    /**
     * Branches may only target scenes in the same validated document.
     */
    public function test_schema_rejects_unknown_branch_target(): void {
        $document = [
            'schema' => 'interactembed-project',
            'version' => 1,
            'projectId' => 'project-test-id',
            'canvas' => ['width' => 1920, 'height' => 1080, 'aspectRatio' => '16:9'],
            'scenes' => [[
                'id' => 'scene-test-id',
                'elements' => [[
                    'id' => 'element-test-id',
                    'type' => 'button',
                    'transform' => ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'rotation' => 0],
                    'content' => ['text' => 'Branch', 'action' => 'scene', 'targetSceneId' => 'missing-scene'],
                ]],
            ]],
        ];
        $this->expectException(\moodle_exception::class);
        project_repository::validate_document(
            json_encode($document, JSON_THROW_ON_ERROR),
            'project-test-id'
        );
    }

    /**
     * A publication identifier cannot be replayed from another course.
     */
    public function test_snapshot_is_restricted_to_expected_course(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $sourcecourse = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $sourcecontext = context_course::instance($sourcecourse->id);
        $project = (new project_repository())->create($sourcecontext, $USER->id, 'Scoped publication');
        $project = $DB->get_record('local_interactembedcreator_project', ['id' => $project->id], '*', MUST_EXIST);
        $publication = (new publication_builder())->publish($project, $USER->id);

        $this->expectException(\dml_missing_record_exception::class);
        publication_api::get_snapshot($publication->id, context_course::instance($othercourse->id));
    }

    /**
     * Site and category publications are inherited while sibling-course content remains isolated.
     */
    public function test_contextual_publications_and_cross_context_copy(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $sourcecourse = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $consumercourse = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $systemcontext = context_system::instance();
        $categorycontext = context_coursecat::instance($category->id);
        $sourcecontext = context_course::instance($sourcecourse->id);
        $consumercontext = context_course::instance($consumercourse->id);
        $repository = new project_repository();
        $builder = new publication_builder();

        $siteproject = $repository->create($systemcontext, $USER->id, 'Site publication');
        $categoryproject = $repository->create($categorycontext, $USER->id, 'Category publication');
        $courseproject = $repository->create($sourcecontext, $USER->id, 'Sibling course publication');
        $sitepublication = $builder->publish(
            $DB->get_record('local_interactembedcreator_project', ['id' => $siteproject->id], '*', MUST_EXIST),
            $USER->id
        );
        $categorypublication = $builder->publish(
            $DB->get_record('local_interactembedcreator_project', ['id' => $categoryproject->id], '*', MUST_EXIST),
            $USER->id
        );
        $builder->publish(
            $DB->get_record('local_interactembedcreator_project', ['id' => $courseproject->id], '*', MUST_EXIST),
            $USER->id
        );

        $available = publication_api::list_for_context($consumercontext);
        $this->assertCount(2, $available);
        $this->assertNotEmpty(publication_api::get_snapshot($sitepublication->id, $consumercontext)->files);
        $this->assertNotEmpty(publication_api::get_snapshot($categorypublication->id, $consumercontext)->files);

        get_file_storage()->create_file_from_string([
            'contextid' => $sourcecontext->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $courseproject->id,
            'filepath' => '/',
            'filename' => 'copy-test.txt',
        ], 'copy');
        $copy = $repository->duplicate($courseproject->id, $USER->id, 'Site copy', $systemcontext);
        $this->assertNotSame($courseproject->uuid, $copy->uuid);
        $this->assertSame($systemcontext->id, (int) $copy->contextid);
        $this->assertNotFalse(get_file_storage()->get_file(
            $systemcontext->id,
            'local_interactembedcreator',
            'asset',
            $copy->id,
            '/',
            'copy-test.txt'
        ));
    }

    /**
     * Snapshot delivery rejects files changed after publication.
     */
    public function test_snapshot_rejects_hash_mismatch(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $project = (new project_repository())->create($context, $USER->id, 'Integrity publication');
        $project = $DB->get_record('local_interactembedcreator_project', ['id' => $project->id], '*', MUST_EXIST);
        $publication = (new publication_builder())->publish($project, $USER->id);
        $fs = get_file_storage();
        $entry = $fs->get_file(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            '/',
            'index.html'
        );
        $entry->delete();
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'publication',
            'itemid' => $publication->id,
            'filepath' => '/',
            'filename' => 'index.html',
        ], '<!doctype html><title>Changed</title>');

        $this->expectException(\moodle_exception::class);
        publication_api::get_snapshot($publication->id, $context);
    }

    /**
     * Lifecycle operations are reversible and revision restoration preserves history.
     */
    public function test_project_lifecycle_and_revision_recovery(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $repository = new project_repository();
        $project = $repository->create($context, $USER->id, 'Lifecycle');
        $firstrevisionid = $project->currentrevision;
        $document = $repository->get_current_document($project);
        $document->metadata->title = 'Changed title';
        $lockversion = $document->_lockversion;
        unset($document->_revision, $document->_lockversion);
        $repository->save(
            $project->id,
            $USER->id,
            json_encode($document, JSON_THROW_ON_ERROR),
            $lockversion,
            false
        );

        $result = $repository->restore_revision($project->id, $firstrevisionid, $USER->id);
        $this->assertSame(3, $result['revision']);
        $revisions = $repository->list_revisions($project->id, $USER->id);
        $this->assertCount(3, $revisions);
        $restoredproject = $DB->get_record('local_interactembedcreator_project', ['id' => $project->id], '*', MUST_EXIST);
        $restored = $repository->get_current_document($restoredproject);
        $this->assertSame('Lifecycle', $restored->metadata->title);

        $repository->set_archived($project->id, $USER->id, true);
        $this->assertCount(0, $repository->list($context, $USER->id));
        $this->assertCount(1, $repository->list($context, $USER->id, true));
        $archivedproject = $DB->get_record('local_interactembedcreator_project', ['id' => $project->id], '*', MUST_EXIST);
        $archiveddocument = $repository->get_current_document($archivedproject);
        $archivedlock = $archiveddocument->_lockversion;
        unset($archiveddocument->_revision, $archiveddocument->_lockversion);
        try {
            $repository->save(
                $project->id,
                $USER->id,
                json_encode($archiveddocument, JSON_THROW_ON_ERROR),
                $archivedlock,
                false
            );
            $this->fail('An archived project must not accept saves.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('projectisarchived', $exception->errorcode);
        }
        $repository->set_archived($project->id, $USER->id, false);
        $this->assertCount(1, $repository->list($context, $USER->id));
        $renamed = $repository->rename($project->id, $USER->id, 'Lifecycle renamed');
        $this->assertSame('Lifecycle renamed', $renamed->name);
        $this->assertSame(1, $repository->count($context, $USER->id, false, 'renamed'));
        $this->assertCount(1, $repository->list($context, $USER->id, false, 'renamed', 0, 12));
        $this->assertSame(0, $repository->count($context, $USER->id, false, 'missing'));
        $renameddocument = $repository->get_current_document($renamed);
        $this->assertSame('Lifecycle renamed', $renameddocument->metadata->title);
    }

    /**
     * Duplication and source round-trips create independent IDs and copy media.
     */
    public function test_duplicate_and_source_package_round_trip(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $repository = new project_repository();
        $project = $repository->create($context, $USER->id, 'Portable project');
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $project->id,
            'filepath' => '/images/',
            'filename' => 'sample.svg',
        ], '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $copy = $repository->duplicate($project->id, $USER->id, 'Portable copy');
        $this->assertNotSame($project->uuid, $copy->uuid);
        $this->assertNotFalse(get_file_storage()->get_file(
            $context->id,
            'local_interactembedcreator',
            'asset',
            $copy->id,
            '/images/',
            'sample.svg'
        ));
        $copieddocument = $repository->get_current_document($copy);
        $this->assertSame($copy->uuid, $copieddocument->projectId);

        $package = new source_package();
        $archive = $package->export($project, $USER->id);
        $this->assertFileExists($archive);
        $imported = $package->import($archive, $context, $USER->id, 'Imported source');
        $this->assertNotSame($project->uuid, $imported->uuid);
        $this->assertNotFalse(get_file_storage()->get_file(
            $context->id,
            'local_interactembedcreator',
            'asset',
            $imported->id,
            '/images/',
            'sample.svg'
        ));
        $importeddocument = $repository->get_current_document($imported);
        $this->assertSame($imported->uuid, $importeddocument->projectId);
        $this->assertSame('Imported source', $importeddocument->metadata->title);
    }

    /**
     * Publications retain immutable versions while availability controls only new consumer copies.
     */
    public function test_publication_availability_is_reversible(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $project = (new project_repository())->create($context, $USER->id, 'Versioned publication');
        $project = $DB->get_record('local_interactembedcreator_project', ['id' => $project->id], '*', MUST_EXIST);
        $publication = (new publication_builder())->publish($project, $USER->id);
        $manager = new publication_manager();

        $this->assertCount(1, publication_api::list_for_context($context));
        $manager->set_available($publication->id, $USER->id, false);
        $this->assertCount(0, publication_api::list_for_context($context));
        $retained = $manager->list($project->id, $USER->id);
        $this->assertCount(1, $retained);
        $this->assertSame('withdrawn', reset($retained)->status);
        $this->assertNotEmpty(get_file_storage()->get_area_files(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            'id',
            false
        ));

        $manager->set_available($publication->id, $USER->id, true);
        $this->assertCount(1, publication_api::list_for_context($context));
        $snapshot = publication_api::get_snapshot($publication->id, $context);
        $this->assertSame($publication->packagehash, $snapshot->packagehash);
    }

    /**
     * The bundled mini-course creates an independent editable project with its asset.
     */
    public function test_official_showcase_template_is_editable_and_localized(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $repository = new project_repository();
        $project = $repository->create($context, $USER->id, 'Official mini-course', 'showcase');
        $document = $repository->get_current_document($project);

        $this->assertCount(6, $document->scenes);
        $this->assertSame($project->uuid, $document->projectId);
        $this->assertNotFalse(get_file_storage()->get_file(
            $context->id,
            'local_interactembedcreator',
            'asset',
            $project->id,
            '/',
            'creator-workflow.svg'
        ));
        $sceneids = array_map(static fn($scene) => $scene->id, $document->scenes);
        $this->assertCount(6, array_unique($sceneids));

        $publication = (new publication_builder())->compile_official_showcase('en');
        $this->assertArrayHasKey('index.html', $publication);
        $this->assertStringContainsString("fetch('project.json', {credentials: 'same-origin'})", $publication['player.js']);
        $this->assertStringContainsString('projectIsReady(project)', $publication['player.js']);
        $manifest = json_decode($publication['interactembed-manifest.json'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('en', $manifest['locale']);
        $this->assertSame('0.4.2', $manifest['runtime']['version']);

        // Plugin strings follow Moodle's installed language packs; the bundled showcase also ships all three locales.
        $spanishjson = file_get_contents(__DIR__ . '/../showcase/es/project.json');
        $this->assertNotFalse($spanishjson);
        $this->assertStringContainsString('Descubrir Creator InteractEmbed', $spanishjson);
    }

    /**
     * Source imports reject unexpected archive entries before creating a project.
     */
    public function test_source_package_rejects_unexpected_files_without_orphan(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $repository = new project_repository();
        $project = $repository->create($context, $USER->id, 'Import guard');
        $document = $repository->get_current_document($project);
        unset($document->_revision, $document->_lockversion);
        $archive = make_request_directory() . '/unexpected-source.zip';
        $created = get_file_packer('application/zip')->archive_to_pathname([
            'interactembed-source.json' => [json_encode([
                'format' => 'interactembed-creator-source',
                'version' => 1,
                'name' => 'Unexpected',
            ], JSON_THROW_ON_ERROR)],
            'project.json' => [json_encode($document, JSON_THROW_ON_ERROR)],
            'unexpected.php' => ['not allowed'],
        ], $archive, false);
        $this->assertTrue($created);
        $before = $DB->count_records('local_interactembedcreator_project');
        try {
            (new source_package())->import($archive, $context, $USER->id);
            $this->fail('Unexpected source entries must be rejected.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidsourcepackage', $exception->errorcode);
        }
        $this->assertSame($before, $DB->count_records('local_interactembedcreator_project'));
    }
}
