// This file is part of Moodle - http://moodle.org/

/**
 * Initial visual editor for InteractEmbed schema v1 projects.
 *
 * @module     local_interactembedcreator/editor
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const CANVAS_WIDTH = 1920;
const CANVAS_HEIGHT = 1080;

let config;
let documentState;
let activeSceneIndex = 0;
let selectedElementId = null;
let selectedElementIds = new Set();
let saveTimer = null;
let saving = false;
let dirty = false;
let historyPast = [];
let historyFuture = [];
let inputHistoryKey = null;
let zoomPercent = 100;
let previewMode = false;
let focusMode = false;
let clipboardElements = [];
let pasteOffset = 0;
let gridSize = 20;
let gridVisible = true;
let snapEnabled = true;

const element = id => document.getElementById(id);

const uuid = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }
    return `id-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
};

const currentScene = () => documentState.scenes[activeSceneIndex];

const assetByPath = path => config.assets.find(asset => asset.path === path) || null;

const currentSceneStyle = () => {
    const scene = currentScene();
    scene.style = scene.style || {};
    return scene.style;
};

const safeHttpUrl = value => {
    if (!/^https?:\/\//i.test(value)) {
        return false;
    }
    try {
        const parsed = new URL(value);
        return ['http:', 'https:'].includes(parsed.protocol);
    } catch (error) {
        return false;
    }
};

const sceneIndexById = sceneId => documentState.scenes.findIndex(scene => scene.id === sceneId);

const textJustification = alignment => alignment === 'center' ? 'center' : alignment === 'right' ? 'flex-end' : 'flex-start';

const selectedElements = () => currentScene().elements.filter(item => selectedElementIds.has(item.id));

const selectedElement = () => currentScene().elements.find(item => item.id === selectedElementId) || null;

const clearSelection = () => {
    selectedElementId = null;
    selectedElementIds = new Set();
};

const selectOnly = id => {
    selectedElementId = id;
    selectedElementIds = new Set([id]);
};

const toggleSelection = id => {
    if (selectedElementIds.has(id)) {
        selectedElementIds.delete(id);
        if (selectedElementId === id) {
            selectedElementId = [...selectedElementIds].at(-1) || null;
        }
    } else {
        selectedElementIds.add(id);
        selectedElementId = id;
    }
};

const editableSnapshot = () => JSON.stringify({scenes: documentState.scenes, settings: documentState.settings});

const restoreSnapshot = snapshot => {
    const restored = JSON.parse(snapshot);
    documentState.scenes = restored.scenes;
    documentState.settings = restored.settings || documentState.settings;
    activeSceneIndex = Math.min(activeSceneIndex, documentState.scenes.length - 1);
    const available = new Set(currentScene().elements.map(item => item.id));
    selectedElementIds = new Set([...selectedElementIds].filter(id => available.has(id)));
    if (!selectedElement()) {
        selectedElementId = [...selectedElementIds].at(-1) || null;
    }
    inputHistoryKey = null;
};

const updateHistoryButtons = () => {
    element('iec-undo').disabled = historyPast.length === 0;
    element('iec-redo').disabled = historyFuture.length === 0;
};

const recordHistory = () => {
    const snapshot = editableSnapshot();
    if (historyPast.at(-1) !== snapshot) {
        historyPast.push(snapshot);
        historyPast = historyPast.slice(-80);
    }
    historyFuture = [];
    updateHistoryButtons();
};

const beginInputHistory = key => {
    if (inputHistoryKey === key) {
        return;
    }
    recordHistory();
    inputHistoryKey = key;
};

const endInputHistory = () => {
    inputHistoryKey = null;
};

const undo = () => {
    if (!historyPast.length) {
        return;
    }
    historyFuture.push(editableSnapshot());
    restoreSnapshot(historyPast.pop());
    markDirty();
    updateHistoryButtons();
    render();
};

const redo = () => {
    if (!historyFuture.length) {
        return;
    }
    historyPast.push(editableSnapshot());
    restoreSnapshot(historyFuture.pop());
    markDirty();
    updateHistoryButtons();
    render();
};

const markDirty = () => {
    dirty = true;
    element('iec-save-status').textContent = '';
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => save(true), 1800);
};

const save = async autosave => {
    if (saving || !dirty) {
        return;
    }
    saving = true;
    element('iec-save-status').textContent = config.strings.saving;
    try {
        const requests = Ajax.call([{
            methodname: 'local_interactembedcreator_save_project',
            args: {
                projectid: config.projectId,
                contentjson: JSON.stringify(documentState),
                baselockversion: documentState._lockversion,
                autosave,
            },
        }]);
        const response = await requests[0];
        documentState._revision = response.revision;
        documentState._lockversion = response.lockversion;
        dirty = false;
        element('iec-save-status').textContent = config.strings.saved;
    } catch (error) {
        if (String(error?.errorcode || '').includes('conflict')) {
            Notification.alert('', config.strings.saveconflict);
        } else {
            Notification.exception(error);
        }
    } finally {
        saving = false;
    }
};

const sceneLabel = index => config.strings.scene.replace('__NUMBER__', String(index + 1));

const renderSceneList = () => {
    const list = element('iec-scene-list');
    list.replaceChildren();
    documentState.scenes.forEach((scene, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'iec-scene-button';
        button.setAttribute('aria-current', index === activeSceneIndex ? 'true' : 'false');
        const thumb = document.createElement('span');
        thumb.className = 'iec-scene-thumb';
        thumb.textContent = String(index + 1);
        thumb.style.backgroundColor = scene.style?.backgroundColor || '#f7f8fb';
        const backgroundAsset = assetByPath(scene.style?.backgroundImage || '');
        if (backgroundAsset) {
            thumb.style.backgroundImage = `url("${backgroundAsset.url}")`;
        }
        const label = document.createElement('span');
        label.textContent = scene.title || sceneLabel(index);
        button.append(thumb, label);
        button.addEventListener('click', () => {
            activeSceneIndex = index;
            clearSelection();
            render();
        });
        list.append(button);
    });
};

const applyPosition = (node, transform) => {
    node.style.left = `${transform.x / CANVAS_WIDTH * 100}%`;
    node.style.top = `${transform.y / CANVAS_HEIGHT * 100}%`;
    node.style.width = `${transform.width / CANVAS_WIDTH * 100}%`;
    node.style.height = `${transform.height / CANVAS_HEIGHT * 100}%`;
    node.style.transform = `rotate(${transform.rotation || 0}deg)`;
};

const selectionBounds = models => {
    const left = Math.min(...models.map(model => model.transform.x));
    const top = Math.min(...models.map(model => model.transform.y));
    const right = Math.max(...models.map(model => model.transform.x + model.transform.width));
    const bottom = Math.max(...models.map(model => model.transform.y + model.transform.height));
    return {x: left, y: top, width: right - left, height: bottom - top};
};

const axisSnap = (value, size, axis, excludedIds) => {
    let snapped = snapEnabled ? Math.round(value / gridSize) * gridSize : value;
    let guide = null;
    if (!snapEnabled) {
        return {value: snapped, guide};
    }
    const canvasSize = axis === 'x' ? CANVAS_WIDTH : CANVAS_HEIGHT;
    const candidates = [0, canvasSize / 2, canvasSize];
    for (const model of currentScene().elements) {
        if (excludedIds.has(model.id)) {
            continue;
        }
        const start = model.transform[axis];
        const length = axis === 'x' ? model.transform.width : model.transform.height;
        candidates.push(start, start + length / 2, start + length);
    }
    const movingAnchors = [0, size / 2, size];
    let closest = 13;
    for (const candidate of candidates) {
        for (const anchor of movingAnchors) {
            const distance = Math.abs(value + anchor - candidate);
            if (distance < closest) {
                closest = distance;
                snapped = candidate - anchor;
                guide = candidate;
            }
        }
    }
    return {value: snapped, guide};
};

const showGuides = (stage, vertical, horizontal) => {
    stage.querySelectorAll('.iec-snap-guide').forEach(node => node.remove());
    if (vertical !== null) {
        const guide = document.createElement('span');
        guide.className = 'iec-snap-guide iec-snap-guide--vertical';
        guide.style.left = `${vertical / CANVAS_WIDTH * 100}%`;
        stage.append(guide);
    }
    if (horizontal !== null) {
        const guide = document.createElement('span');
        guide.className = 'iec-snap-guide iec-snap-guide--horizontal';
        guide.style.top = `${horizontal / CANVAS_HEIGHT * 100}%`;
        stage.append(guide);
    }
};

const beginPointerOperation = (event, model, mode) => {
    event.preventDefault();
    event.stopPropagation();
    const stage = element('iec-stage');
    const rect = stage.getBoundingClientRect();
    const startX = event.clientX;
    const startY = event.clientY;
    const models = mode === 'move' ? selectedElements() : [model];
    const originals = new Map(models.map(item => [item.id, {...item.transform}]));
    const bounds = selectionBounds(models);
    const excludedIds = new Set(models.map(item => item.id));
    let historyRecorded = false;

    const move = moveEvent => {
        if (!historyRecorded) {
            recordHistory();
            historyRecorded = true;
        }
        const dx = (moveEvent.clientX - startX) / rect.width * CANVAS_WIDTH;
        const dy = (moveEvent.clientY - startY) / rect.height * CANVAS_HEIGHT;
        if (mode === 'move') {
            const xSnap = axisSnap(bounds.x + dx, bounds.width, 'x', excludedIds);
            const ySnap = axisSnap(bounds.y + dy, bounds.height, 'y', excludedIds);
            const targetX = Math.max(0, Math.min(CANVAS_WIDTH - bounds.width, xSnap.value));
            const targetY = Math.max(0, Math.min(CANVAS_HEIGHT - bounds.height, ySnap.value));
            const appliedX = targetX - bounds.x;
            const appliedY = targetY - bounds.y;
            for (const item of models) {
                const original = originals.get(item.id);
                item.transform.x = original.x + appliedX;
                item.transform.y = original.y + appliedY;
                const node = stage.querySelector(`[data-element-id="${item.id}"]`);
                if (node) {
                    applyPosition(node, item.transform);
                }
            }
            showGuides(stage, xSnap.guide, ySnap.guide);
        } else {
            const original = originals.get(model.id);
            const width = snapEnabled ? Math.round((original.width + dx) / gridSize) * gridSize : original.width + dx;
            const height = snapEnabled ? Math.round((original.height + dy) / gridSize) * gridSize : original.height + dy;
            model.transform.width = Math.max(80, Math.min(CANVAS_WIDTH - original.x, width));
            model.transform.height = Math.max(60, Math.min(CANVAS_HEIGHT - original.y, height));
            applyPosition(event.currentTarget.closest('.iec-element') || event.currentTarget, model.transform);
        }
    };
    const end = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        showGuides(stage, null, null);
        if (historyRecorded) {
            markDirty();
        }
        window.setTimeout(render, 0);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end, {once: true});
};

const renderStage = () => {
    const stage = element('iec-stage');
    stage.replaceChildren();
    stage.style.width = `${960 * zoomPercent / 100}px`;
    stage.classList.toggle('has-grid', gridVisible && !previewMode);
    stage.style.setProperty('--iec-grid-x', `${gridSize / CANVAS_WIDTH * 100}%`);
    stage.style.setProperty('--iec-grid-y', `${gridSize / CANVAS_HEIGHT * 100}%`);
    const sceneStyle = currentSceneStyle();
    const backgroundAsset = assetByPath(sceneStyle.backgroundImage || '');
    const backgroundLayers = [];
    const backgroundSizes = [];
    const backgroundPositions = [];
    const backgroundRepeats = [];
    if (gridVisible && !previewMode) {
        backgroundLayers.push(
            'linear-gradient(to right, rgba(55, 105, 178, .12) 1px, transparent 1px)',
            'linear-gradient(to bottom, rgba(55, 105, 178, .12) 1px, transparent 1px)'
        );
        backgroundSizes.push('var(--iec-grid-x) var(--iec-grid-y)', 'var(--iec-grid-x) var(--iec-grid-y)');
        backgroundPositions.push('0 0', '0 0');
        backgroundRepeats.push('repeat', 'repeat');
    }
    if (backgroundAsset) {
        backgroundLayers.push(`url("${backgroundAsset.url}")`);
        backgroundSizes.push('cover');
        backgroundPositions.push('center');
        backgroundRepeats.push('no-repeat');
    }
    stage.style.backgroundColor = sceneStyle.backgroundColor || '#ffffff';
    stage.style.backgroundImage = backgroundLayers.join(', ');
    stage.style.backgroundSize = backgroundSizes.join(', ');
    stage.style.backgroundPosition = backgroundPositions.join(', ');
    stage.style.backgroundRepeat = backgroundRepeats.join(', ');
    currentScene().elements.forEach(model => {
        const node = document.createElement('div');
        const isSelected = selectedElementIds.has(model.id);
        node.className = `iec-element${isSelected ? ' is-selected' : ''}${selectedElementIds.size > 1 && isSelected
            ? ' is-multiselected'
            : ''}`;
        node.tabIndex = previewMode ? -1 : 0;
        node.dataset.elementId = model.id;
        node.setAttribute('role', 'group');
        node.setAttribute('aria-label', model.accessibility?.label || model.type);
        applyPosition(node, model.transform);
        if (model.type === 'text' || model.type === 'button') {
            node.textContent = model.content?.text || '';
            node.style.color = model.style?.color || '#172033';
            node.style.background = model.style?.background || 'transparent';
            node.style.borderRadius = `${Number(model.style?.borderRadius || 0)}px`;
            node.style.fontSize = `${Math.max(12, Number(model.style?.fontSize || 36)) / CANVAS_WIDTH * stage.clientWidth}px`;
            node.style.fontWeight = model.style?.fontWeight || 'normal';
            node.style.fontStyle = model.style?.fontStyle || 'normal';
            node.style.textDecoration = model.style?.textDecoration || 'none';
            node.style.justifyContent = textJustification(model.style?.textAlign);
            node.style.textAlign = model.style?.textAlign || 'left';
        } else if (model.type === 'shape') {
            node.style.background = model.style?.background || '#dce8fb';
            node.style.borderRadius = `${Number(model.style?.borderRadius || 16)}px`;
        } else if (model.type === 'quiz') {
            const quiz = document.createElement('div');
            const question = document.createElement('strong');
            question.textContent = model.content?.question || '';
            const answers = document.createElement('ol');
            for (const answer of model.content?.answers || []) {
                const item = document.createElement('li');
                item.textContent = answer;
                answers.append(item);
            }
            quiz.append(question, answers);
            node.append(quiz);
        } else if (model.type === 'image') {
            const asset = assetByPath(model.content?.assetPath);
            const image = document.createElement('img');
            image.alt = model.accessibility?.decorative ? '' : model.accessibility?.alt || '';
            image.draggable = false;
            image.src = asset?.url || '';
            image.style.cssText = `height:100%;object-fit:${model.content?.fit || 'contain'};width:100%`;
            node.append(image);
        } else if (model.type === 'audio' || model.type === 'video') {
            const asset = assetByPath(model.content?.assetPath);
            const media = document.createElement(model.type);
            media.controls = model.content?.controls !== false;
            media.loop = model.content?.loop === true;
            media.muted = model.content?.muted === true;
            media.preload = 'metadata';
            media.src = asset?.url || '';
            const poster = assetByPath(model.content?.posterPath || '');
            if (model.type === 'video' && poster) {
                media.poster = poster.url;
            }
            const captions = assetByPath(model.content?.captionsPath || '');
            if (model.type === 'video' && captions) {
                const track = document.createElement('track');
                track.kind = 'captions';
                track.src = captions.url;
                track.default = true;
                media.append(track);
            }
            media.style.cssText = 'height:100%;width:100%';
            node.append(media);
        }
        node.addEventListener('click', event => {
            if (previewMode) {
                return;
            }
            event.stopPropagation();
            render();
        });
        node.addEventListener('pointerdown', event => {
            if (previewMode) {
                return;
            }
            if (event.shiftKey || event.ctrlKey || event.metaKey) {
                event.preventDefault();
                event.stopPropagation();
                toggleSelection(model.id);
                return;
            }
            if (!selectedElementIds.has(model.id)) {
                selectOnly(model.id);
            }
            beginPointerOperation(event, model, 'move');
        });
        node.addEventListener('keydown', event => {
            const delta = event.shiftKey ? 10 : 2;
            if (event.altKey || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            recordHistory();
            const models = selectedElements();
            const bounds = selectionBounds(models);
            let dx = 0;
            let dy = 0;
            if (event.key === 'ArrowLeft') {
                dx = -Math.min(delta, bounds.x);
            } else if (event.key === 'ArrowRight') {
                dx = Math.min(delta, CANVAS_WIDTH - bounds.x - bounds.width);
            } else if (event.key === 'ArrowUp') {
                dy = -Math.min(delta, bounds.y);
            } else {
                dy = Math.min(delta, CANVAS_HEIGHT - bounds.y - bounds.height);
            }
            for (const item of models) {
                item.transform.x += dx;
                item.transform.y += dy;
            }
            markDirty();
            renderStage();
        });
        if (model.id === selectedElementId && selectedElementIds.size === 1) {
            const handle = document.createElement('span');
            handle.className = 'iec-element__resize';
            handle.setAttribute('aria-hidden', 'true');
            handle.addEventListener('pointerdown', event => beginPointerOperation(event, model, 'resize'));
            node.append(handle);
        }
        stage.append(node);
    });
};

const populateSceneTargets = (select, value, allowNone) => {
    select.replaceChildren();
    if (allowNone) {
        const none = document.createElement('option');
        none.value = '';
        none.textContent = config.strings.targetscenenone;
        select.append(none);
    }
    documentState.scenes.forEach((scene, index) => {
        const option = document.createElement('option');
        option.value = scene.id;
        option.textContent = `${index + 1}. ${scene.title || sceneLabel(index)}`;
        select.append(option);
    });
    select.value = value || '';
};

const renderProperties = () => {
    const models = selectedElements();
    const model = models.length === 1 ? models[0] : null;
    const scene = currentScene();
    const sceneStyle = currentSceneStyle();
    element('iec-scene-title').value = scene.title || sceneLabel(activeSceneIndex);
    element('iec-scene-background-color').value = sceneStyle.backgroundColor || '#ffffff';
    element('iec-scene-background-image').value = sceneStyle.backgroundImage || '';
    element('iec-scene-up').disabled = activeSceneIndex === 0;
    element('iec-scene-down').disabled = activeSceneIndex === documentState.scenes.length - 1;
    element('iec-delete-scene').disabled = documentState.scenes.length === 1;
    element('iec-show-navigation').checked = documentState.settings?.navigation !== false;
    element('iec-property-empty').hidden = models.length > 0;
    element('iec-property-form').hidden = models.length !== 1;
    element('iec-multi-properties').hidden = models.length < 2;
    element('iec-selection-count').textContent = config.strings.selectedcount.replace('__COUNT__', String(models.length));
    element('iec-selection-count').hidden = models.length === 0;
    for (const id of ['iec-copy', 'iec-cut', 'iec-duplicate']) {
        element(id).disabled = models.length === 0;
    }
    element('iec-delete-element').disabled = models.length === 0;
    element('iec-paste').disabled = clipboardElements.length === 0;
    const canAlign = models.length >= 2;
    const canDistribute = models.length >= 3;
    for (const id of ['iec-align-left', 'iec-align-center', 'iec-align-right', 'iec-align-top',
        'iec-align-middle', 'iec-align-bottom']) {
        element(id).disabled = !canAlign;
    }
    element('iec-distribute-horizontal').disabled = !canDistribute;
    element('iec-distribute-vertical').disabled = !canDistribute;
    const isText = model?.type === 'text' || model?.type === 'button';
    const isButton = model?.type === 'button';
    const isQuiz = model?.type === 'quiz';
    const isMedia = model?.type === 'audio' || model?.type === 'video';
    element('iec-text-properties').hidden = !isText;
    element('iec-quiz-properties').hidden = !isQuiz;
    element('iec-button-properties').hidden = !isButton;
    element('iec-image-properties').hidden = model?.type !== 'image';
    element('iec-media-properties').hidden = !isMedia;
    element('iec-video-properties').hidden = model?.type !== 'video';
    element('iec-accessibility-properties').hidden = !model;
    element('iec-element-actions').hidden = !model;
    if (isText) {
        element('iec-text-content').value = model.content?.text || '';
    }
    if (isButton) {
        const action = model.content?.action || 'none';
        element('iec-button-action').value = action;
        element('iec-button-scene-wrap').hidden = action !== 'scene';
        element('iec-button-url-wrap').hidden = action !== 'url';
        populateSceneTargets(element('iec-button-scene'), model.content?.targetSceneId, false);
        element('iec-button-url').value = model.content?.href || '';
    }
    if (isQuiz) {
        const mode = model.content?.selectionMode || 'single';
        element('iec-quiz-question').value = model.content?.question || '';
        element('iec-quiz-answers').value = (model.content?.answers || []).join('\n');
        element('iec-quiz-answers').disabled = mode === 'truefalse';
        element('iec-quiz-mode').value = mode;
        const correctIndexes = model.content?.correctIndexes || [Number(model.content?.correctIndex || 0)];
        element('iec-quiz-correct').value = correctIndexes.map(index => Number(index) + 1).join(', ');
        element('iec-quiz-points').value = String(Number(model.content?.points || 1));
        element('iec-quiz-correct-feedback').value = model.content?.correctFeedback || '';
        element('iec-quiz-incorrect-feedback').value = model.content?.incorrectFeedback || '';
        populateSceneTargets(element('iec-quiz-correct-scene'), model.content?.correctSceneId, true);
        populateSceneTargets(element('iec-quiz-incorrect-scene'), model.content?.incorrectSceneId, true);
    }
    if (model) {
        for (const property of ['x', 'y', 'width', 'height', 'rotation']) {
            element(`iec-transform-${property}`).value = String(Math.round(Number(model.transform[property] || 0)));
        }
        element('iec-font-size').value = String(Number(model.style?.fontSize || 36));
        element('iec-text-color').value = model.style?.color || '#172033';
        element('iec-background-color').value = model.style?.background === 'transparent'
            ? '#ffffff'
            : model.style?.background || '#ffffff';
        element('iec-image-alt').value = model.accessibility?.alt || '';
        element('iec-image-alt').disabled = model.accessibility?.decorative === true;
        element('iec-image-decorative').checked = model.accessibility?.decorative === true;
        element('iec-image-fit').value = model.content?.fit || 'contain';
        element('iec-text-link').value = model.content?.href || '';
        element('iec-accessibility-label').value = model.accessibility?.label || '';
        if (isMedia) {
            element('iec-media-controls').checked = model.content?.controls !== false;
            element('iec-media-loop').checked = model.content?.loop === true;
            element('iec-media-muted').checked = model.content?.muted === true;
            element('iec-media-transcript').value = model.content?.transcript || '';
            element('iec-media-poster').value = model.content?.posterPath || '';
            element('iec-media-captions').value = model.content?.captionsPath || '';
        }
    }
    element('iec-text-style-properties').hidden = !isText;
    element('iec-text-link-properties').hidden = model?.type !== 'text';
    for (const [id, active] of [
        ['iec-text-bold', model?.style?.fontWeight === 'bold'],
        ['iec-text-italic', model?.style?.fontStyle === 'italic'],
        ['iec-text-underline', model?.style?.textDecoration === 'underline'],
    ]) {
        element(id).setAttribute('aria-pressed', active ? 'true' : 'false');
        element(id).classList.toggle('active', active);
    }
};

const layerLabel = model => {
    const content = model.type === 'quiz' ? model.content?.question : model.content?.text;
    return `${config.strings[model.type] || model.type}${content ? ` — ${String(content).slice(0, 32)}` : ''}`;
};

const renderLayers = () => {
    const list = element('iec-layer-list');
    list.replaceChildren();
    [...currentScene().elements].reverse().forEach(model => {
        const row = document.createElement('div');
        row.className = `iec-layer-row${selectedElementIds.has(model.id) ? ' is-selected' : ''}`;
        const select = document.createElement('button');
        select.type = 'button';
        select.className = 'iec-layer-select';
        select.textContent = layerLabel(model);
        select.addEventListener('click', event => {
            if (event.shiftKey || event.ctrlKey || event.metaKey) {
                toggleSelection(model.id);
            } else {
                selectOnly(model.id);
            }
            render();
        });
        const forward = document.createElement('button');
        forward.type = 'button';
        forward.className = 'btn btn-sm btn-outline-secondary';
        forward.textContent = '↑';
        forward.title = config.strings.bringforward;
        forward.setAttribute('aria-label', `${config.strings.bringforward}: ${layerLabel(model)}`);
        forward.addEventListener('click', () => moveLayerForId(model.id, 'forward'));
        const backward = document.createElement('button');
        backward.type = 'button';
        backward.className = 'btn btn-sm btn-outline-secondary';
        backward.textContent = '↓';
        backward.title = config.strings.sendbackward;
        backward.setAttribute('aria-label', `${config.strings.sendbackward}: ${layerLabel(model)}`);
        backward.addEventListener('click', () => moveLayerForId(model.id, 'backward'));
        row.append(select, forward, backward);
        list.append(row);
    });
};

const render = () => {
    renderSceneList();
    renderStage();
    renderProperties();
    renderLayers();
};

const addScene = () => {
    recordHistory();
    documentState.scenes.push({
        id: uuid(),
        title: sceneLabel(documentState.scenes.length),
        style: {backgroundColor: '#ffffff', backgroundImage: ''},
        elements: [],
    });
    activeSceneIndex = documentState.scenes.length - 1;
    clearSelection();
    markDirty();
    render();
};

const duplicateScene = () => {
    recordHistory();
    const duplicate = structuredClone(currentScene());
    duplicate.id = uuid();
    duplicate.title = `${duplicate.title || sceneLabel(activeSceneIndex)} ${config.strings.copysuffix}`;
    duplicate.elements = duplicate.elements.map(model => ({...model, id: uuid()}));
    documentState.scenes.splice(activeSceneIndex + 1, 0, duplicate);
    activeSceneIndex++;
    clearSelection();
    markDirty();
    render();
};

const deleteScene = () => {
    if (documentState.scenes.length <= 1 || !window.confirm(config.strings.deletesceneconfirm)) {
        return;
    }
    recordHistory();
    documentState.scenes.splice(activeSceneIndex, 1);
    activeSceneIndex = Math.min(activeSceneIndex, documentState.scenes.length - 1);
    clearSelection();
    markDirty();
    render();
};

const moveScene = direction => {
    const target = activeSceneIndex + (direction === 'up' ? -1 : 1);
    if (target < 0 || target >= documentState.scenes.length) {
        return;
    }
    recordHistory();
    const [scene] = documentState.scenes.splice(activeSceneIndex, 1);
    documentState.scenes.splice(target, 0, scene);
    activeSceneIndex = target;
    markDirty();
    render();
};

const addText = () => {
    recordHistory();
    const model = {
        id: uuid(),
        type: 'text',
        transform: {x: 360, y: 360, width: 1200, height: 240, rotation: 0},
        content: {text: config.strings.scene.replace('__NUMBER__', String(activeSceneIndex + 1))},
        style: {fontSize: 54, color: '#172033', textAlign: 'center'},
        accessibility: {label: 'Text'},
    };
    currentScene().elements.push(model);
    selectOnly(model.id);
    markDirty();
    render();
};

const addShape = () => {
    recordHistory();
    const model = {
        id: uuid(),
        type: 'shape',
        transform: {x: 560, y: 340, width: 800, height: 400, rotation: 0},
        content: {},
        style: {background: '#dce8fb', borderRadius: 24},
        accessibility: {label: 'Shape'},
    };
    currentScene().elements.push(model);
    selectOnly(model.id);
    markDirty();
    render();
};

const addButton = () => {
    recordHistory();
    const model = {
        id: uuid(),
        type: 'button',
        transform: {x: 710, y: 820, width: 500, height: 130, rotation: 0},
        content: {text: config.strings.buttondefault, action: 'next', targetSceneId: '', href: ''},
        style: {fontSize: 40, color: '#ffffff', background: '#245ca8', textAlign: 'center', borderRadius: 16},
        accessibility: {label: config.strings.buttondefault},
    };
    currentScene().elements.push(model);
    selectOnly(model.id);
    markDirty();
    render();
};

const addQuiz = () => {
    recordHistory();
    const model = {
        id: uuid(),
        type: 'quiz',
        transform: {x: 360, y: 250, width: 1200, height: 580, rotation: 0},
        content: {
            question: config.strings.quizdefaultquestion,
            answers: [config.strings.quizdefaultanswer1, config.strings.quizdefaultanswer2],
            selectionMode: 'single',
            correctIndex: 0,
            correctIndexes: [0],
            points: 1,
            checkLabel: config.strings.checkanswer,
            correctFeedback: config.strings.quizfeedbackcorrect,
            incorrectFeedback: config.strings.quizfeedbackincorrect,
            correctSceneId: '',
            incorrectSceneId: '',
        },
        style: {background: '#ffffff', color: '#172033'},
        accessibility: {label: config.strings.quizdefaultquestion},
    };
    currentScene().elements.push(model);
    selectOnly(model.id);
    markDirty();
    render();
};

const populateAssets = () => {
    const select = element('iec-media-select');
    const backgroundSelect = element('iec-scene-background-image');
    const posterSelect = element('iec-media-poster');
    const captionsSelect = element('iec-media-captions');
    select.replaceChildren();
    backgroundSelect.replaceChildren();
    posterSelect.replaceChildren();
    captionsSelect.replaceChildren();
    for (const [target, label] of [
        [backgroundSelect, config.strings.noimage],
        [posterSelect, config.strings.noasset],
        [captionsSelect, config.strings.noasset],
    ]) {
        const none = document.createElement('option');
        none.value = '';
        none.textContent = label;
        target.append(none);
    }
    for (const asset of config.assets) {
        if (['image/', 'audio/', 'video/'].some(prefix => asset.mimetype.startsWith(prefix))) {
            const option = document.createElement('option');
            option.value = asset.path;
            option.textContent = asset.name;
            option.dataset.mimetype = asset.mimetype;
            select.append(option);
        }
        if (asset.mimetype.startsWith('image/')) {
            const backgroundOption = document.createElement('option');
            backgroundOption.value = asset.path;
            backgroundOption.textContent = asset.name;
            backgroundSelect.append(backgroundOption);
            const posterOption = backgroundOption.cloneNode(true);
            posterSelect.append(posterOption);
        }
        if (asset.mimetype === 'text/vtt' || asset.path.toLowerCase().endsWith('.vtt')) {
            const captionsOption = document.createElement('option');
            captionsOption.value = asset.path;
            captionsOption.textContent = asset.name;
            captionsSelect.append(captionsOption);
        }
    }
    element('iec-add-media').disabled = select.options.length === 0;
};

const addMedia = () => {
    const select = element('iec-media-select');
    const option = select.selectedOptions[0];
    if (!option) {
        return;
    }
    recordHistory();
    const mimetype = option.dataset.mimetype || '';
    const type = mimetype.startsWith('image/') ? 'image' : mimetype.startsWith('audio/') ? 'audio' : 'video';
    const model = {
        id: uuid(),
        type,
        transform: {
            x: type === 'audio' ? 360 : 480,
            y: type === 'audio' ? 760 : 220,
            width: type === 'audio' ? 1200 : 960,
            height: type === 'audio' ? 240 : 620,
            rotation: 0,
        },
        content: {
            assetPath: option.value,
            fit: type === 'image' ? 'contain' : undefined,
            controls: type !== 'image' ? true : undefined,
            loop: type !== 'image' ? false : undefined,
            muted: type !== 'image' ? false : undefined,
            posterPath: type === 'video' ? '' : undefined,
            captionsPath: type === 'video' ? '' : undefined,
            transcript: type !== 'image' ? '' : undefined,
        },
        style: {},
        accessibility: {
            label: option.textContent,
            alt: type === 'image' ? option.textContent : '',
            decorative: false,
        },
    };
    currentScene().elements.push(model);
    selectOnly(model.id);
    markDirty();
    render();
};

const cloneModels = (models, offset) => models.map(model => {
    const duplicate = structuredClone(model);
    duplicate.id = uuid();
    duplicate.transform.x = Math.max(0, Math.min(CANVAS_WIDTH - duplicate.transform.width,
        duplicate.transform.x + offset));
    duplicate.transform.y = Math.max(0, Math.min(CANVAS_HEIGHT - duplicate.transform.height,
        duplicate.transform.y + offset));
    return duplicate;
});

const duplicateSelected = () => {
    const models = selectedElements();
    if (!models.length) {
        return;
    }
    recordHistory();
    const duplicates = cloneModels(models, 32);
    currentScene().elements.push(...duplicates);
    selectedElementIds = new Set(duplicates.map(item => item.id));
    selectedElementId = duplicates.at(-1).id;
    markDirty();
    render();
};

const deleteSelected = () => {
    if (!selectedElementIds.size) {
        return;
    }
    if (selectedElementIds.size > 1 && !window.confirm(config.strings.deleteelementsconfirm.replace(
        '__COUNT__', String(selectedElementIds.size)))) {
        return;
    }
    recordHistory();
    currentScene().elements = currentScene().elements.filter(item => !selectedElementIds.has(item.id));
    clearSelection();
    markDirty();
    render();
};

const copySelected = () => {
    const models = selectedElements();
    if (!models.length) {
        return;
    }
    clipboardElements = structuredClone(models);
    pasteOffset = 0;
    renderProperties();
};

const cutSelected = () => {
    if (!selectedElementIds.size) {
        return;
    }
    copySelected();
    deleteSelected();
};

const pasteClipboard = () => {
    if (!clipboardElements.length) {
        return;
    }
    recordHistory();
    pasteOffset = Math.min(160, pasteOffset + 32);
    const pasted = cloneModels(clipboardElements, pasteOffset);
    currentScene().elements.push(...pasted);
    selectedElementIds = new Set(pasted.map(item => item.id));
    selectedElementId = pasted.at(-1).id;
    markDirty();
    render();
};

const alignSelection = alignment => {
    const models = selectedElements();
    if (models.length < 2) {
        return;
    }
    recordHistory();
    const bounds = selectionBounds(models);
    for (const model of models) {
        if (alignment === 'left') {
            model.transform.x = bounds.x;
        } else if (alignment === 'center') {
            model.transform.x = bounds.x + (bounds.width - model.transform.width) / 2;
        } else if (alignment === 'right') {
            model.transform.x = bounds.x + bounds.width - model.transform.width;
        } else if (alignment === 'top') {
            model.transform.y = bounds.y;
        } else if (alignment === 'middle') {
            model.transform.y = bounds.y + (bounds.height - model.transform.height) / 2;
        } else if (alignment === 'bottom') {
            model.transform.y = bounds.y + bounds.height - model.transform.height;
        }
    }
    markDirty();
    render();
};

const distributeSelection = axis => {
    const models = selectedElements();
    if (models.length < 3) {
        return;
    }
    recordHistory();
    const position = axis === 'horizontal' ? 'x' : 'y';
    const dimension = axis === 'horizontal' ? 'width' : 'height';
    const sorted = [...models].sort((first, second) => first.transform[position] - second.transform[position]);
    const start = sorted[0].transform[position];
    const end = sorted.at(-1).transform[position] + sorted.at(-1).transform[dimension];
    const occupied = sorted.reduce((total, model) => total + model.transform[dimension], 0);
    const available = end - start - occupied;
    if (available >= 0) {
        const gap = available / (sorted.length - 1);
        let cursor = start;
        for (const model of sorted) {
            model.transform[position] = cursor;
            cursor += model.transform[dimension] + gap;
        }
    } else {
        const lastStart = sorted.at(-1).transform[position];
        const step = (lastStart - start) / (sorted.length - 1);
        sorted.forEach((model, index) => {
            model.transform[position] = start + step * index;
        });
    }
    markDirty();
    render();
};

const moveLayerForId = (id, direction) => {
    const elements = currentScene().elements;
    const index = elements.findIndex(item => item.id === id);
    if (index < 0) {
        return;
    }
    let target = index;
    if (direction === 'front') {
        target = elements.length - 1;
    } else if (direction === 'back') {
        target = 0;
    } else if (direction === 'forward') {
        target = Math.min(elements.length - 1, index + 1);
    } else if (direction === 'backward') {
        target = Math.max(0, index - 1);
    }
    if (target === index) {
        return;
    }
    recordHistory();
    const [model] = elements.splice(index, 1);
    elements.splice(target, 0, model);
    selectOnly(id);
    markDirty();
    render();
};

const moveLayer = direction => {
    if (selectedElementId) {
        moveLayerForId(selectedElementId, direction);
    }
};

const updateTextStyle = (property, value) => {
    const model = selectedElement();
    if (!model || !['text', 'button'].includes(model.type)) {
        return;
    }
    recordHistory();
    model.style = model.style || {};
    model.style[property] = value;
    markDirty();
    render();
};

const toggleTextStyle = (property, enabledValue, disabledValue) => {
    const model = selectedElement();
    if (!model || !['text', 'button'].includes(model.type)) {
        return;
    }
    updateTextStyle(property, model.style?.[property] === enabledValue ? disabledValue : enabledValue);
};

const formatTextList = ordered => {
    const model = selectedElement();
    if (!model || !['text', 'button'].includes(model.type)) {
        return;
    }
    recordHistory();
    const lines = String(model.content?.text || '').split(/\r?\n/).map(line => line.replace(/^\s*(?:[•*-]|\d+\.)\s+/, ''));
    model.content.text = lines.map((line, index) => `${ordered ? `${index + 1}.` : '•'} ${line}`).join('\n');
    model.accessibility.label = model.content.text.slice(0, 100);
    markDirty();
    render();
};

const findAccessibilityIssues = () => {
    const issues = [];
    documentState.scenes.forEach((scene, sceneIndex) => {
        scene.elements.forEach(model => {
            const add = (message, field) => issues.push({sceneIndex, elementId: model.id, message, field});
            if (model.type === 'image' && !model.accessibility?.decorative && !String(model.accessibility?.alt || '').trim()) {
                add(config.strings.issueimagealt, 'iec-image-alt');
            }
            if (model.type === 'audio' && !String(model.content?.transcript || '').trim()) {
                add(config.strings.issueaudiotranscript, 'iec-media-transcript');
            }
            if (model.type === 'video' && !String(model.content?.captionsPath || '').trim()) {
                add(config.strings.issuevideocaptions, 'iec-media-captions');
            }
            if (model.type === 'button' && !String(model.content?.text || '').trim()) {
                add(config.strings.issuebuttontext, 'iec-text-content');
            }
            if (!(model.type === 'image' && model.accessibility?.decorative) &&
                    !String(model.accessibility?.label || '').trim()) {
                add(config.strings.issuelabel, 'iec-accessibility-label');
            }
        });
    });
    return issues;
};

const checkAccessibility = () => {
    const status = element('iec-accessibility-status');
    const issues = findAccessibilityIssues();
    status.hidden = false;
    status.classList.toggle('is-success', issues.length === 0);
    if (!issues.length) {
        status.textContent = config.strings.accessibilitypassed;
        return;
    }
    const first = issues[0];
    status.textContent = `${config.strings.accessibilityissues.replace('__COUNT__', String(issues.length))} ${first.message}`;
    activeSceneIndex = first.sceneIndex;
    selectOnly(first.elementId);
    render();
    window.setTimeout(() => element(first.field)?.focus(), 0);
};

const updateZoom = value => {
    zoomPercent = Math.max(50, Math.min(150, Number(value) || 100));
    element('iec-zoom').value = String(zoomPercent);
    element('iec-zoom-value').textContent = `${zoomPercent}%`;
    renderStage();
};

const updateGrid = () => {
    gridVisible = element('iec-show-grid').checked;
    snapEnabled = element('iec-snap-grid').checked;
    gridSize = Math.max(10, Math.min(80, Number(element('iec-grid-size').value) || 20));
    renderStage();
};

const togglePreview = () => {
    previewMode = !previewMode;
    element('iec-editor').classList.toggle('is-preview', previewMode);
    element('iec-preview').textContent = previewMode ? config.strings.exitpreview : config.strings.preview;
    clearSelection();
    render();
};

const toggleWorkspacePanel = panel => {
    const editor = element('iec-editor');
    const className = panel === 'scenes' ? 'is-scenes-collapsed' : 'is-properties-collapsed';
    const button = element(panel === 'scenes' ? 'iec-toggle-scenes' : 'iec-toggle-properties');
    const collapsed = editor.classList.toggle(className);
    button.setAttribute('aria-pressed', collapsed ? 'false' : 'true');
};

const setFocusMode = enabled => {
    focusMode = enabled;
    element('iec-editor').classList.toggle('is-focus-mode', enabled);
    const button = element('iec-focus-mode');
    button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    button.textContent = `⛶ ${enabled ? config.strings.exitfocusmode : config.strings.focusmode}`;
    if (enabled) {
        element('iec-stage').focus();
    } else {
        button.focus();
    }
};

const bindTransformProperties = () => {
    const limits = {
        x: [0, CANVAS_WIDTH],
        y: [0, CANVAS_HEIGHT],
        width: [80, CANVAS_WIDTH],
        height: [60, CANVAS_HEIGHT],
        rotation: [-360, 360],
    };
    for (const property of Object.keys(limits)) {
        const field = element(`iec-transform-${property}`);
        field.addEventListener('input', event => {
            const model = selectedElement();
            if (!model) {
                return;
            }
            beginInputHistory(`transform-${property}-${model.id}`);
            const [minimum, maximum] = limits[property];
            model.transform[property] = Math.max(minimum, Math.min(maximum, Number(event.target.value) || 0));
            if (property === 'x') {
                model.transform.x = Math.min(model.transform.x, CANVAS_WIDTH - model.transform.width);
            } else if (property === 'y') {
                model.transform.y = Math.min(model.transform.y, CANVAS_HEIGHT - model.transform.height);
            } else if (property === 'width') {
                model.transform.width = Math.min(model.transform.width, CANVAS_WIDTH - model.transform.x);
            } else if (property === 'height') {
                model.transform.height = Math.min(model.transform.height, CANVAS_HEIGHT - model.transform.y);
            }
            markDirty();
            renderStage();
        });
        field.addEventListener('blur', endInputHistory);
    }
};

export const init = suppliedConfig => {
    config = suppliedConfig;
    documentState = structuredClone(config.document);
    delete documentState._revision;
    delete documentState._lockversion;
    documentState._revision = config.document._revision;
    documentState._lockversion = config.document._lockversion;

    element('iec-add-scene').addEventListener('click', addScene);
    element('iec-duplicate-scene').addEventListener('click', duplicateScene);
    element('iec-delete-scene').addEventListener('click', deleteScene);
    element('iec-scene-up').addEventListener('click', () => moveScene('up'));
    element('iec-scene-down').addEventListener('click', () => moveScene('down'));
    element('iec-scene-title').addEventListener('input', event => {
        beginInputHistory(`scene-title-${currentScene().id}`);
        currentScene().title = event.target.value.slice(0, 255);
        markDirty();
        renderSceneList();
    });
    element('iec-scene-title').addEventListener('blur', endInputHistory);
    element('iec-scene-background-color').addEventListener('change', event => {
        recordHistory();
        currentSceneStyle().backgroundColor = event.target.value;
        markDirty();
        render();
    });
    element('iec-scene-background-image').addEventListener('change', event => {
        recordHistory();
        currentSceneStyle().backgroundImage = event.target.value;
        markDirty();
        render();
    });
    element('iec-show-navigation').addEventListener('change', event => {
        recordHistory();
        documentState.settings = documentState.settings || {};
        documentState.settings.navigation = event.target.checked;
        markDirty();
    });
    element('iec-add-text').addEventListener('click', addText);
    element('iec-add-shape').addEventListener('click', addShape);
    element('iec-add-button').addEventListener('click', addButton);
    element('iec-add-quiz').addEventListener('click', addQuiz);
    element('iec-add-media').addEventListener('click', addMedia);
    element('iec-undo').addEventListener('click', undo);
    element('iec-redo').addEventListener('click', redo);
    element('iec-copy').addEventListener('click', copySelected);
    element('iec-cut').addEventListener('click', cutSelected);
    element('iec-paste').addEventListener('click', pasteClipboard);
    element('iec-duplicate').addEventListener('click', duplicateSelected);
    element('iec-delete-element').addEventListener('click', deleteSelected);
    element('iec-layer-front').addEventListener('click', () => moveLayer('front'));
    element('iec-layer-forward').addEventListener('click', () => moveLayer('forward'));
    element('iec-layer-backward').addEventListener('click', () => moveLayer('backward'));
    element('iec-layer-back').addEventListener('click', () => moveLayer('back'));
    for (const alignment of ['left', 'center', 'right', 'top', 'middle', 'bottom']) {
        element(`iec-align-${alignment}`).addEventListener('click', () => alignSelection(alignment));
    }
    element('iec-distribute-horizontal').addEventListener('click', () => distributeSelection('horizontal'));
    element('iec-distribute-vertical').addEventListener('click', () => distributeSelection('vertical'));
    element('iec-zoom').addEventListener('input', event => updateZoom(event.target.value));
    element('iec-show-grid').addEventListener('change', updateGrid);
    element('iec-snap-grid').addEventListener('change', updateGrid);
    element('iec-grid-size').addEventListener('change', updateGrid);
    element('iec-preview').addEventListener('click', togglePreview);
    element('iec-toggle-scenes').addEventListener('click', () => toggleWorkspacePanel('scenes'));
    element('iec-toggle-properties').addEventListener('click', () => toggleWorkspacePanel('properties'));
    element('iec-focus-mode').addEventListener('click', () => setFocusMode(!focusMode));
    element('iec-check-accessibility').addEventListener('click', checkAccessibility);
    element('iec-save').addEventListener('click', () => save(false));
    element('iec-stage').addEventListener('click', () => {
        clearSelection();
        render();
    });
    element('iec-text-content').addEventListener('input', event => {
        const model = selectedElement();
        if (!model || !['text', 'button'].includes(model.type)) {
            return;
        }
        beginInputHistory(`text-${model.id}`);
        model.content.text = event.target.value;
        model.accessibility.label = event.target.value.slice(0, 100);
        markDirty();
        renderStage();
    });
    element('iec-quiz-question').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'quiz') {
            return;
        }
        beginInputHistory(`question-${model.id}`);
        model.content.question = event.target.value;
        model.accessibility.label = event.target.value.slice(0, 100);
        markDirty();
        renderStage();
    });
    element('iec-quiz-mode').addEventListener('change', event => {
        const model = selectedElement();
        if (model?.type !== 'quiz') {
            return;
        }
        recordHistory();
        model.content.selectionMode = event.target.value;
        if (event.target.value === 'truefalse') {
            model.content.answers = [config.strings.true, config.strings.false];
            model.content.correctIndexes = [0];
        } else if (event.target.value === 'single') {
            model.content.correctIndexes = [Number(model.content.correctIndexes?.[0] || 0)];
        }
        model.content.correctIndex = Number(model.content.correctIndexes?.[0] || 0);
        markDirty();
        render();
    });
    element('iec-quiz-answers').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'quiz') {
            return;
        }
        beginInputHistory(`answers-${model.id}`);
        const answers = event.target.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean).slice(0, 10);
        model.content.answers = answers.length >= 2 ? answers : model.content.answers;
        model.content.correctIndex = Math.min(Number(model.content.correctIndex || 0), model.content.answers.length - 1);
        markDirty();
        renderStage();
    });
    element('iec-quiz-correct').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'quiz') {
            return;
        }
        beginInputHistory(`correct-${model.id}`);
        const indexes = [...new Set(event.target.value.split(',').map(value => Number(value.trim()) - 1)
            .filter(index => Number.isInteger(index) && index >= 0 && index < model.content.answers.length))];
        const valid = indexes.length > 0 && (model.content.selectionMode === 'multiple' || indexes.length === 1);
        event.target.setCustomValidity(valid ? '' : config.strings.quizcorrectinvalid);
        if (!valid) {
            return;
        }
        model.content.correctIndexes = indexes;
        model.content.correctIndex = indexes[0];
        markDirty();
    });
    element('iec-quiz-points').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'quiz') {
            return;
        }
        beginInputHistory(`points-${model.id}`);
        model.content.points = Math.max(1, Math.min(100, Number(event.target.value) || 1));
        markDirty();
    });
    for (const [id, property] of [
        ['iec-quiz-correct-feedback', 'correctFeedback'],
        ['iec-quiz-incorrect-feedback', 'incorrectFeedback'],
    ]) {
        element(id).addEventListener('input', event => {
            const model = selectedElement();
            if (model?.type !== 'quiz') {
                return;
            }
            beginInputHistory(`${property}-${model.id}`);
            model.content[property] = event.target.value;
            markDirty();
        });
    }
    for (const [id, property] of [
        ['iec-quiz-correct-scene', 'correctSceneId'],
        ['iec-quiz-incorrect-scene', 'incorrectSceneId'],
    ]) {
        element(id).addEventListener('change', event => {
            const model = selectedElement();
            if (model?.type !== 'quiz') {
                return;
            }
            recordHistory();
            model.content[property] = event.target.value;
            markDirty();
        });
    }
    element('iec-button-action').addEventListener('change', event => {
        const model = selectedElement();
        if (model?.type !== 'button') {
            return;
        }
        recordHistory();
        model.content.action = event.target.value;
        if (event.target.value === 'scene' && sceneIndexById(model.content.targetSceneId) < 0) {
            model.content.targetSceneId = documentState.scenes[Math.min(activeSceneIndex + 1,
                documentState.scenes.length - 1)].id;
        }
        markDirty();
        renderProperties();
    });
    element('iec-button-scene').addEventListener('change', event => {
        const model = selectedElement();
        if (model?.type !== 'button') {
            return;
        }
        recordHistory();
        model.content.targetSceneId = event.target.value;
        markDirty();
    });
    element('iec-button-url').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'button') {
            return;
        }
        const href = event.target.value.trim();
        const valid = href === '' || safeHttpUrl(href);
        event.target.setCustomValidity(valid ? '' : config.strings.linkinvalid);
        if (!valid) {
            return;
        }
        beginInputHistory(`button-url-${model.id}`);
        model.content.href = href;
        markDirty();
    });
    for (const id of ['iec-text-content', 'iec-quiz-question', 'iec-quiz-answers', 'iec-quiz-correct',
        'iec-quiz-points', 'iec-quiz-correct-feedback', 'iec-quiz-incorrect-feedback', 'iec-button-url']) {
        element(id).addEventListener('blur', endInputHistory);
    }
    element('iec-font-size').addEventListener('change', event => {
        const model = selectedElement();
        if (!model) {
            return;
        }
        recordHistory();
        model.style.fontSize = Math.max(12, Math.min(200, Number(event.target.value) || 36));
        markDirty();
        render();
    });
    element('iec-text-bold').addEventListener('click', () => toggleTextStyle('fontWeight', 'bold', 'normal'));
    element('iec-text-italic').addEventListener('click', () => toggleTextStyle('fontStyle', 'italic', 'normal'));
    element('iec-text-underline').addEventListener('click', () => toggleTextStyle(
        'textDecoration', 'underline', 'none'));
    for (const alignment of ['left', 'center', 'right']) {
        element(`iec-text-align-${alignment}`).addEventListener('click', () => updateTextStyle('textAlign', alignment));
    }
    element('iec-text-bullets').addEventListener('click', () => formatTextList(false));
    element('iec-text-numbers').addEventListener('click', () => formatTextList(true));
    element('iec-text-link').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'text') {
            return;
        }
        const href = event.target.value.trim();
        const valid = href === '' || /^https?:\/\//i.test(href);
        event.target.setCustomValidity(valid ? '' : config.strings.linkinvalid);
        if (!valid) {
            return;
        }
        beginInputHistory(`link-${model.id}`);
        model.content.href = href;
        markDirty();
    });
    element('iec-text-link').addEventListener('blur', event => {
        if (event.target.validationMessage) {
            event.target.reportValidity();
        }
        endInputHistory();
    });
    for (const [id, property] of [['iec-text-color', 'color'], ['iec-background-color', 'background']]) {
        element(id).addEventListener('change', event => {
            const model = selectedElement();
            if (!model) {
                return;
            }
            recordHistory();
            model.style[property] = event.target.value;
            markDirty();
            render();
        });
    }
    element('iec-image-alt').addEventListener('input', event => {
        const model = selectedElement();
        if (model?.type !== 'image') {
            return;
        }
        beginInputHistory(`alt-${model.id}`);
        model.accessibility.alt = event.target.value;
        markDirty();
    });
    element('iec-image-alt').addEventListener('blur', endInputHistory);
    element('iec-image-decorative').addEventListener('change', event => {
        const model = selectedElement();
        if (model?.type !== 'image') {
            return;
        }
        recordHistory();
        model.accessibility.decorative = event.target.checked;
        if (event.target.checked) {
            model.accessibility.alt = '';
        }
        markDirty();
        render();
    });
    element('iec-image-fit').addEventListener('change', event => {
        const model = selectedElement();
        if (model?.type !== 'image') {
            return;
        }
        recordHistory();
        model.content.fit = event.target.value;
        markDirty();
        render();
    });
    for (const [id, property] of [
        ['iec-media-controls', 'controls'],
        ['iec-media-loop', 'loop'],
        ['iec-media-muted', 'muted'],
    ]) {
        element(id).addEventListener('change', event => {
            const model = selectedElement();
            if (!model || !['audio', 'video'].includes(model.type)) {
                return;
            }
            recordHistory();
            model.content[property] = event.target.checked;
            markDirty();
            renderStage();
        });
    }
    for (const [id, property] of [
        ['iec-media-poster', 'posterPath'],
        ['iec-media-captions', 'captionsPath'],
    ]) {
        element(id).addEventListener('change', event => {
            const model = selectedElement();
            if (model?.type !== 'video') {
                return;
            }
            recordHistory();
            model.content[property] = event.target.value;
            markDirty();
            renderStage();
        });
    }
    element('iec-media-transcript').addEventListener('input', event => {
        const model = selectedElement();
        if (!model || !['audio', 'video'].includes(model.type)) {
            return;
        }
        beginInputHistory(`transcript-${model.id}`);
        model.content.transcript = event.target.value;
        markDirty();
    });
    element('iec-media-transcript').addEventListener('blur', endInputHistory);
    element('iec-accessibility-label').addEventListener('input', event => {
        const model = selectedElement();
        if (!model) {
            return;
        }
        beginInputHistory(`accessibility-label-${model.id}`);
        model.accessibility = model.accessibility || {};
        model.accessibility.label = event.target.value.slice(0, 500);
        markDirty();
    });
    element('iec-accessibility-label').addEventListener('blur', endInputHistory);
    bindTransformProperties();
    window.addEventListener('keydown', event => {
        const interactive = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName);
        const command = event.ctrlKey || event.metaKey;
        if (event.key === 'Escape' && focusMode) {
            event.preventDefault();
            setFocusMode(false);
        } else if (command && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            if (event.shiftKey) {
                redo();
            } else {
                undo();
            }
        } else if (command && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            redo();
        } else if (command && event.shiftKey && event.key.toLowerCase() === 'd' && !interactive) {
            event.preventDefault();
            duplicateScene();
        } else if (command && event.key.toLowerCase() === 'd' && !interactive) {
            event.preventDefault();
            duplicateSelected();
        } else if (command && event.key.toLowerCase() === 'c' && !interactive) {
            event.preventDefault();
            copySelected();
        } else if (command && event.key.toLowerCase() === 'x' && !interactive) {
            event.preventDefault();
            cutSelected();
        } else if (command && event.key.toLowerCase() === 'v' && !interactive) {
            event.preventDefault();
            pasteClipboard();
        } else if (command && event.key.toLowerCase() === 'a' && !interactive) {
            event.preventDefault();
            selectedElementIds = new Set(currentScene().elements.map(item => item.id));
            selectedElementId = currentScene().elements.at(-1)?.id || null;
            render();
        } else if (event.altKey && event.key === 'ArrowUp' && !interactive) {
            event.preventDefault();
            moveLayer('forward');
        } else if (event.altKey && event.key === 'ArrowDown' && !interactive) {
            event.preventDefault();
            moveLayer('backward');
        } else if (event.altKey && event.key === 'PageUp' && !interactive) {
            event.preventDefault();
            moveScene('up');
        } else if (event.altKey && event.key === 'PageDown' && !interactive) {
            event.preventDefault();
            moveScene('down');
        } else if (event.key === 'F2' && !interactive) {
            event.preventDefault();
            element('iec-scene-title').focus();
            element('iec-scene-title').select();
        } else if (event.key === 'Escape' && !interactive) {
            clearSelection();
            render();
        } else if (!interactive && ['Delete', 'Backspace'].includes(event.key)) {
            event.preventDefault();
            deleteSelected();
        }
    });
    window.addEventListener('beforeunload', event => {
        if (dirty) {
            event.preventDefault();
        }
    });
    populateAssets();
    updateHistoryButtons();
    updateZoom(100);
    updateGrid();
    render();
};
