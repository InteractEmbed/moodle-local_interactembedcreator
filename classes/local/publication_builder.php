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

namespace local_interactembedcreator\local;

use context;
use moodle_exception;
use stdClass;

/**
 * Compiles project revisions into immutable standalone HTML5 publications.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publication_builder {
    /** @var string Runtime version embedded in publication metadata. */
    public const ENGINE_VERSION = '0.4.2';

    /**
     * Compiles the bundled read-only mini-course without creating database records.
     *
     * @param string $lang
     * @return array
     */
    public function compile_official_showcase(string $lang): array {
        $lang = in_array($lang, ['en', 'fr', 'es'], true) ? $lang : 'en';
        $stringmanager = get_string_manager();
        $name = $stringmanager->get_string('showcaseprojectname', 'local_interactembedcreator', null, $lang);
        $document = official_template::document(project_repository::uuid(), $name, $lang);
        return $this->compile($document, project_repository::uuid(), 1, $lang);
    }

    /**
     * Publishes the current revision of a project.
     *
     * @param stdClass $project
     * @param int $userid
     * @return stdClass
     */
    public function publish(stdClass $project, int $userid): stdClass {
        global $DB;

        $context = context::instance_by_id($project->contextid, MUST_EXIST);
        require_capability('local/interactembedcreator:publish', $context);
        if ($project->status === 'archived') {
            throw new moodle_exception('projectisarchived', 'local_interactembedcreator');
        }
        $revision = $DB->get_record(
            'local_iec_revision',
            ['id' => $project->currentrevision, 'projectid' => $project->id],
            '*',
            MUST_EXIST
        );
        project_repository::validate_document($revision->contentjson, $project->uuid);
        $document = json_decode($revision->contentjson, true, 512, JSON_THROW_ON_ERROR);
        $assetfiles = get_file_storage()->get_area_files(
            $context->id,
            'local_interactembedcreator',
            'asset',
            $project->id,
            'filepath, filename',
            false
        );

        $publicationno = 1 + (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(publicationno), 0)
               FROM {local_iec_publication}
              WHERE projectid = :projectid',
            ['projectid' => $project->id]
        );
        $uuid = project_repository::uuid();
        $files = $this->compile($document, $uuid, $publicationno, $project->defaultlocale);
        $transaction = $DB->start_delegated_transaction();
        $publication = (object) [
            'uuid' => $uuid,
            'projectid' => $project->id,
            'revisionid' => $revision->id,
            'publicationno' => $publicationno,
            'status' => 'ready',
            'entrypoint' => 'index.html',
            'packagehash' => str_repeat('0', 64),
            'engineversion' => self::ENGINE_VERSION,
            'createdby' => $userid,
            'timecreated' => time(),
        ];
        $publication->id = $DB->insert_record('local_iec_publication', $publication);

        $fs = get_file_storage();
        try {
            foreach ($files as $path => $contents) {
                $parts = explode('/', $path);
                $filename = array_pop($parts);
                $filepath = '/' . ($parts ? implode('/', $parts) . '/' : '');
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'local_interactembedcreator',
                    'filearea' => 'publication',
                    'itemid' => $publication->id,
                    'filepath' => $filepath,
                    'filename' => $filename,
                ], $contents);
            }
            foreach ($assetfiles as $asset) {
                $relativepath = ltrim($asset->get_filepath(), '/') . $asset->get_filename();
                $parts = explode('/', $relativepath);
                $filename = array_pop($parts);
                $filepath = '/assets/' . ($parts ? implode('/', $parts) . '/' : '');
                $fs->create_file_from_storedfile([
                    'contextid' => $context->id,
                    'component' => 'local_interactembedcreator',
                    'filearea' => 'publication',
                    'itemid' => $publication->id,
                    'filepath' => $filepath,
                    'filename' => $filename,
                ], $asset);
            }
            $publishedfiles = $fs->get_area_files(
                $context->id,
                'local_interactembedcreator',
                'publication',
                $publication->id,
                'filepath, filename',
                false
            );
            $publication->packagehash = \local_interactembedcreator\publication_api::calculate_hash($publishedfiles);
            $DB->update_record('local_iec_publication', $publication);
            $revision->revisiontype = 'published';
            $DB->update_record('local_iec_revision', $revision);
            $project->status = 'published';
            $project->timemodified = time();
            $DB->update_record('local_iec_project', $project);
            $transaction->allow_commit();
        } catch (\Throwable $exception) {
            $fs->delete_area_files(
                $context->id,
                'local_interactembedcreator',
                'publication',
                $publication->id
            );
            $transaction->rollback($exception);
        }

        return $publication;
    }

    /**
     * Creates a temporary ZIP for a publication.
     *
     * @param stdClass $publication
     * @return string
     */
    public function create_zip(stdClass $publication): string {
        global $DB;

        $project = $DB->get_record('local_iec_project', ['id' => $publication->projectid], '*', MUST_EXIST);
        $context = context::instance_by_id($project->contextid, MUST_EXIST);
        require_capability('local/interactembedcreator:view', $context);
        $area = get_file_storage()->get_area_files(
            $context->id,
            'local_interactembedcreator',
            'publication',
            $publication->id,
            'filepath, filename',
            false
        );
        if (!$area) {
            throw new moodle_exception('filenotfound', 'error');
        }
        $files = [];
        foreach ($area as $file) {
            $files[ltrim($file->get_filepath(), '/') . $file->get_filename()] = $file;
        }
        $tempdir = make_request_directory(false);
        $zippath = $tempdir . '/interactembed-' . $publication->uuid . '.zip';
        $packer = get_file_packer('application/zip');
        if (!$packer->archive_to_pathname($files, $zippath)) {
            throw new moodle_exception('errorcreatingfile', 'error');
        }
        return $zippath;
    }

    /**
     * Compiles the deterministic runtime files.
     *
     * @param array $document
     * @param string $publicationuuid
     * @param int $publicationno
     * @param string $locale
     * @return array
     */
    private function compile(array $document, string $publicationuuid, int $publicationno, string $locale): array {
        $locale = in_array($locale, ['en', 'fr', 'es'], true) ? $locale : 'en';
        $stringmanager = get_string_manager();
        $localized = static function (string $identifier) use ($stringmanager, $locale): string {
            return $stringmanager->get_string($identifier, 'local_interactembedcreator', null, $locale);
        };
        $labels = [
            'previous' => $localized('playerprevious'),
            'next' => $localized('playernext'),
            'finish' => $localized('playerfinish'),
            'check' => $localized('checkanswer'),
            'correct' => $localized('quizfeedbackcorrect'),
            'incorrect' => $localized('quizfeedbackincorrect'),
            'loaderror' => $localized('playerloaderror'),
            'scene' => $localized('playerscene'),
            'skip' => $localized('playerskip'),
            'presentation' => $localized('playerpresentation'),
            'continue' => $localized('playercontinue'),
            'captions' => $localized('playercaptions'),
            'transcript' => $localized('playertranscript'),
        ];
        $document['settings']['locale'] = $locale;
        $document['settings']['labels'] = $labels;
        $projectjson = json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );
        $hasscore = false;
        foreach ($document['scenes'] as $scene) {
            foreach ($scene['elements'] as $element) {
                if (($element['type'] ?? '') === 'quiz') {
                    $hasscore = true;
                    break 2;
                }
            }
        }
        $manifest = [
            'schema' => 'interactembed-publication',
            'version' => 1,
            'publicationId' => $publicationuuid,
            'publicationNumber' => $publicationno,
            'entrypoint' => 'index.html',
            'title' => $document['metadata']['title'] ?? '',
            'locale' => $locale,
            'aspectRatio' => '16:9',
            'runtime' => ['name' => 'interactembed-runtime', 'version' => self::ENGINE_VERSION],
            'capabilities' => ['progress' => true, 'score' => $hasscore, 'branching' => true],
        ];
        return [
            'index.html' => $this->index_html($document['metadata']['title'] ?? 'InteractEmbed', $locale, $labels),
            'project.json' => $projectjson,
            'player.js' => $this->player_javascript(),
            'styles.css' => $this->player_styles(),
            'interactembed-manifest.json' => json_encode(
                $manifest,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            ),
        ];
    }

    /**
     * Returns the standalone entrypoint.
     *
     * @param string $title
     * @param string $locale
     * @param array $labels
     * @return string
     */
    private function index_html(string $title, string $locale, array $labels): string {
        $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $previous = htmlspecialchars($labels['previous'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $next = htmlspecialchars($labels['next'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $skip = htmlspecialchars($labels['skip'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $presentation = htmlspecialchars($labels['presentation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<!doctype html>\n<html lang=\"{$locale}\">\n<head>\n<meta charset=\"utf-8\">\n" .
            "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n" .
            '<meta http-equiv="Content-Security-Policy" content="default-src \'self\' data: blob:; ' .
            "script-src 'self'; style-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:\">\n" .
            "<title>{$title}</title>\n<link rel=\"stylesheet\" href=\"styles.css\">\n</head>\n" .
            '<body><a class="skip" href="#stage">' . $skip . '</a><main id="app">' .
            '<div id="stage" tabindex="-1" aria-live="polite"></div>' .
            '<nav aria-label="' . $presentation . '"><button id="previous" type="button">' . $previous . '</button>' .
            '<output id="position"></output><button id="next" type="button">' . $next . '</button></nav>' .
            "</main><script src=\"player.js\"></script></body>\n</html>\n";
    }

    /**
     * Returns the small portable player runtime.
     *
     * @return string
     */
    private function player_javascript(): string {
        return <<<'JS'
'use strict';
let project;
let sceneIndex = 0;
let nonce = null;
let pendingStatus = null;
const scores = {};
const stage = document.getElementById('stage');
const previous = document.getElementById('previous');
const next = document.getElementById('next');
const position = document.getElementById('position');
const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
const fitElementText = (node, requestedSize) => {
    const stageScale = stage.clientWidth / 1920;
    const preferredSize = clamp(Number(requestedSize || 36), 12, 200) * stageScale;
    const minimumSize = Math.min(preferredSize, Math.max(6, 12 * stageScale));
    let fittedSize = preferredSize;
    node.style.fontSize = `${fittedSize}px`;
    for (let attempt = 0; attempt < 100 && fittedSize > minimumSize &&
            (node.scrollHeight > node.clientHeight + 1 || node.scrollWidth > node.clientWidth + 1); attempt++) {
        fittedSize = Math.max(minimumSize, fittedSize - Math.max(.25, preferredSize / 100));
        node.style.fontSize = `${fittedSize}px`;
    }
};
const projectIsReady = value => Boolean(value && Array.isArray(value.scenes) && value.scenes.length &&
    value.scenes.every(scene => scene && Array.isArray(scene.elements)));
const label = (name, fallback) => project?.settings?.labels?.[name] || fallback;
const quizModels = () => project.scenes.flatMap(scene => scene.elements).filter(model => model.type === 'quiz');
const scoreMax = () => quizModels().reduce((total, model) => total + clamp(Number(model.content?.points || 1), 1, 100), 0);
const currentScore = () => Object.values(scores).reduce((total, value) => total + value, 0);
const report = status => {
    if (!nonce || !projectIsReady(project)) {
        pendingStatus = status;
        return;
    }
    const payload = {type: 'interactembed:progress', version: 1, nonce, status};
    const maximum = scoreMax();
    if (maximum > 0) {
        payload.score = currentScore();
        payload.scoremax = maximum;
    }
    window.parent.postMessage(payload, '*');
};
const goToScene = sceneId => {
    const target = project.scenes.findIndex(scene => scene.id === sceneId);
    if (target >= 0) {
        sceneIndex = target;
        render();
    }
};
const advance = () => {
    if (sceneIndex < project.scenes.length - 1) {
        sceneIndex++;
        render();
    } else {
        report('completed');
    }
};
const render = () => {
    const scene = project.scenes[sceneIndex];
    stage.replaceChildren();
    stage.style.backgroundColor = scene.style?.backgroundColor || '#ffffff';
    stage.style.backgroundImage = scene.style?.backgroundImage ? `url("assets/${scene.style.backgroundImage}")` : 'none';
    stage.style.backgroundPosition = 'center';
    stage.style.backgroundRepeat = 'no-repeat';
    stage.style.backgroundSize = 'cover';
    stage.setAttribute('aria-label', scene.title || `${label('scene', 'Scene')} ${sceneIndex + 1}`);
    for (const model of scene.elements) {
        const isTextLink = model.type === 'text' && /^https?:\/\//i.test(model.content?.href || '');
        const node = document.createElement(model.type === 'button' ? 'button' : isTextLink ? 'a' : 'div');
        node.className = `element element-${model.type}`;
        node.style.left = `${model.transform.x / 1920 * 100}%`;
        node.style.top = `${model.transform.y / 1080 * 100}%`;
        node.style.width = `${model.transform.width / 1920 * 100}%`;
        node.style.height = `${model.transform.height / 1080 * 100}%`;
        node.style.transform = `rotate(${Number(model.transform.rotation || 0)}deg)`;
        node.setAttribute('aria-label', model.accessibility?.label || model.type);
        if (model.type === 'image' && model.accessibility?.decorative) {
            node.setAttribute('aria-hidden', 'true');
        }
        if (model.type === 'text' || model.type === 'button') {
            node.textContent = model.content?.text || '';
            node.style.color = model.style?.color || '#172033';
            node.style.background = model.style?.background || 'transparent';
            node.style.borderRadius = `${Number(model.style?.borderRadius || 0)}px`;
            node.style.fontWeight = model.style?.fontWeight || 'normal';
            node.style.fontStyle = model.style?.fontStyle || 'normal';
            node.style.textDecoration = model.style?.textDecoration || 'none';
            node.style.textAlign = model.style?.textAlign || 'left';
            node.style.justifyContent = model.style?.textAlign === 'center' ? 'center' :
                model.style?.textAlign === 'right' ? 'flex-end' : 'flex-start';
            if (isTextLink) {
                node.href = model.content.href;
                node.target = '_blank';
                node.rel = 'noopener noreferrer';
            }
            if (model.type === 'button') {
                node.addEventListener('click', () => {
                    const action = model.content?.action || 'none';
                    if (action === 'next') {
                        advance();
                    } else if (action === 'previous') {
                        sceneIndex = Math.max(0, sceneIndex - 1);
                        render();
                    } else if (action === 'scene') {
                        goToScene(model.content?.targetSceneId);
                    } else if (action === 'url' && /^https?:\/\//i.test(model.content?.href || '')) {
                        window.open(model.content.href, '_blank', 'noopener,noreferrer');
                    }
                });
            }
        } else if (model.type === 'shape') {
            node.style.background = model.style?.background || '#dce8fb';
            node.style.borderRadius = `${Number(model.style?.borderRadius || 16)}px`;
        } else if (model.type === 'quiz') {
            const fieldset = document.createElement('fieldset');
            const legend = document.createElement('legend');
            legend.textContent = model.content?.question || '';
            fieldset.append(legend);
            const groupName = `quiz-${model.id}`;
            const mode = model.content?.selectionMode || 'single';
            (model.content?.answers || []).forEach((answer, answerIndex) => {
                const answerLabel = document.createElement('label');
                const input = document.createElement('input');
                input.type = mode === 'multiple' ? 'checkbox' : 'radio';
                input.name = groupName;
                input.value = String(answerIndex);
                answerLabel.append(input, document.createTextNode(` ${answer}`));
                fieldset.append(answerLabel);
            });
            const check = document.createElement('button');
            check.type = 'button';
            check.textContent = model.content?.checkLabel || label('check', 'Check');
            const feedback = document.createElement('output');
            feedback.setAttribute('aria-live', 'polite');
            let branchTarget = '';
            check.addEventListener('click', () => {
                if (branchTarget) {
                    goToScene(branchTarget);
                    return;
                }
                const selected = [...fieldset.querySelectorAll('input:checked')]
                    .map(input => Number(input.value)).sort((left, right) => left - right);
                const expected = (model.content?.correctIndexes || [Number(model.content?.correctIndex || 0)])
                    .map(Number).sort((left, right) => left - right);
                const correct = selected.length === expected.length && selected.every((value, index) => value === expected[index]);
                feedback.textContent = correct ? model.content?.correctFeedback || label('correct', 'Correct') :
                    model.content?.incorrectFeedback || label('incorrect', 'Try again');
                scores[model.id] = correct ? clamp(Number(model.content?.points || 1), 1, 100) : 0;
                report('inprogress');
                branchTarget = correct ? model.content?.correctSceneId || '' : model.content?.incorrectSceneId || '';
                if (branchTarget) {
                    check.textContent = label('continue', 'Continue');
                }
            });
            fieldset.append(check, feedback);
            node.append(fieldset);
        } else if (model.type === 'image') {
            const image = document.createElement('img');
            image.alt = model.accessibility?.decorative ? '' : model.accessibility?.alt || '';
            image.src = `assets/${model.content?.assetPath || ''}`;
            image.style.cssText = `height:100%;object-fit:${model.content?.fit || 'contain'};width:100%`;
            node.append(image);
        } else if (model.type === 'audio' || model.type === 'video') {
            const media = document.createElement(model.type);
            media.controls = model.content?.controls !== false;
            media.loop = model.content?.loop === true;
            media.muted = model.content?.muted === true;
            media.preload = 'metadata';
            media.src = `assets/${model.content?.assetPath || ''}`;
            if (model.type === 'video' && model.content?.posterPath) {
                media.poster = `assets/${model.content.posterPath}`;
            }
            if (model.type === 'video' && model.content?.captionsPath) {
                const track = document.createElement('track');
                track.kind = 'captions';
                track.label = label('captions', 'Captions');
                track.srclang = project.settings?.locale || 'en';
                track.src = `assets/${model.content.captionsPath}`;
                track.default = true;
                media.append(track);
            }
            media.style.cssText = 'flex:1;min-height:0;width:100%';
            node.append(media);
            if (model.content?.transcript) {
                const details = document.createElement('details');
                details.className = 'transcript';
                const summary = document.createElement('summary');
                summary.textContent = label('transcript', 'Transcript');
                const transcript = document.createElement('p');
                transcript.textContent = model.content.transcript;
                details.append(summary, transcript);
                node.append(details);
            }
        }
        stage.append(node);
        if (model.type === 'text' || model.type === 'button') {
            fitElementText(node, model.style?.fontSize);
        }
    }
    previous.disabled = sceneIndex === 0;
    previous.textContent = label('previous', 'Previous');
    next.textContent = sceneIndex === project.scenes.length - 1 ? label('finish', 'Finish') : label('next', 'Next');
    position.textContent = `${sceneIndex + 1} / ${project.scenes.length}`;
    previous.closest('nav').hidden = project.settings?.navigation === false;
};
window.addEventListener('message', event => {
    const data = event.data;
    if (event.source === window.parent && data?.type === 'interactembed:init' && data.version === 1 &&
            typeof data.nonce === 'string' && data.nonce.length >= 8 && data.nonce.length <= 256) {
        nonce = data.nonce;
        const status = pendingStatus || 'inprogress';
        pendingStatus = null;
        report(status);
    }
});
previous.addEventListener('click', () => { sceneIndex = Math.max(0, sceneIndex - 1); render(); });
next.addEventListener('click', advance);
let resizeFrame = null;
window.addEventListener('resize', () => {
    window.cancelAnimationFrame(resizeFrame);
    resizeFrame = window.requestAnimationFrame(() => {
        if (project) render();
    });
});
fetch('project.json', {credentials: 'same-origin'})
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (!projectIsReady(data)) throw new Error('Invalid project data');
        project = data;
        render();
        if (nonce) {
            const status = pendingStatus || 'inprogress';
            pendingStatus = null;
            report(status);
        }
    })
    .catch(() => { stage.textContent = project?.settings?.labels?.loaderror || 'The content could not be loaded.'; });
JS;
    }

    /**
     * Returns portable player styling.
     *
     * @return string
     */
    private function player_styles(): string {
        return <<<'CSS'
* { box-sizing: border-box; }
html, body { background: #edf1f6; font-family: Arial, sans-serif; margin: 0; min-height: 100%; }
body { display: grid; min-height: 100vh; place-items: center; }
.skip { background: #fff; left: -999px; padding: .7rem; position: fixed; top: .5rem; z-index: 2; }
.skip:focus { left: .5rem; }
#app { width: min(100vw, 1200px); }
#stage {
    aspect-ratio: 16 / 9;
    background: #fff;
    box-shadow: 0 1rem 3rem rgba(15, 30, 50, .18);
    overflow: hidden;
    position: relative;
}
.element {
    align-items: center;
    display: flex;
    overflow: hidden;
    padding: .2rem;
    position: absolute;
    white-space: pre-wrap;
}
.element-text, .element-button { overflow-wrap: anywhere; }
.element-quiz { align-items: stretch; padding: clamp(.4rem, 1vw, 1rem); }
.element-quiz fieldset {
    background: rgba(255, 255, 255, .82);
    border: 1px solid rgba(23, 32, 51, .12);
    border-radius: .75rem;
    display: flex;
    flex-direction: column;
    gap: clamp(.25rem, .7vw, .65rem);
    justify-content: center;
    margin: 0;
    min-width: 0;
    padding: clamp(.65rem, 1.5vw, 1.25rem);
    width: 100%;
}
.element-quiz legend { font-weight: 700; margin-bottom: .35rem; padding: 0; }
.element-quiz label { align-items: flex-start; display: flex; gap: .45rem; }
.element-quiz input { flex: 0 0 auto; margin-top: .18rem; }
.element-quiz button { align-self: flex-start; margin-top: .35rem; }
.element-quiz output { display: block; min-width: 0; text-align: left; }
.element-audio, .element-video { align-items: stretch; flex-direction: column; }
.transcript {
    background: rgba(255, 255, 255, .96);
    color: #172033;
    flex: 0 0 auto;
    max-height: 42%;
    overflow: auto;
    padding: .35rem .55rem;
    width: 100%;
}
.transcript summary { cursor: pointer; font-weight: 700; }
.transcript p { font-size: clamp(.75rem, 1.25vw, 1rem); margin: .35rem 0 0; }
nav { align-items: center; display: flex; gap: 1rem; justify-content: center; padding: 1rem; }
button {
    background: #245ca8;
    border: 0;
    border-radius: .4rem;
    color: #fff;
    font: inherit;
    padding: .65rem 1rem;
}
button:disabled { background: #8793a4; }
output { min-width: 5rem; text-align: center; }
@media (prefers-reduced-motion: reduce) {
    * { scroll-behavior: auto !important; transition: none !important; }
}
CSS;
    }
}
