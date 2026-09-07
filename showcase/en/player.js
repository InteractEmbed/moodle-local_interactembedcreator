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
