// This file is part of Moodle - http://moodle.org/.

/**
 * Responsive and accessible controls for the bundled Creator mini-course.
 *
 * @module     local_interactembedcreator/showcase
 * @copyright  2026 Michel Cardinal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialises the mini-course frame.
 *
 * @param {Object} config Element identifiers and translated labels.
 */
export const init = config => {
    const shell = document.getElementById(config.shellid);
    const player = document.getElementById(config.playerid);
    const button = document.getElementById(config.buttonid);
    const label = button?.querySelector('.iec-showcase-fullscreen-label');
    if (!shell || !player || !button || !label) {
        return;
    }

    let previousFocus = null;
    let contentObserver = null;

    const isFallback = () => shell.classList.contains('iec-showcase-fallback-fullscreen');
    const isFullscreen = () => document.fullscreenElement === shell || isFallback();

    const updateControl = () => {
        const active = isFullscreen();
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        label.textContent = active ? config.exitlabel : config.enterlabel;
        if (active) {
            player.style.height = '';
        }
    };

    const fitPlayer = () => {
        if (isFullscreen()) {
            return;
        }
        try {
            const framedocument = player.contentDocument;
            const stage = framedocument?.getElementById('stage');
            const navigation = framedocument?.querySelector('nav');
            if (!stage || !navigation) {
                return;
            }
            const height = Math.ceil(stage.getBoundingClientRect().height +
                navigation.getBoundingClientRect().height);
            if (height > 0) {
                player.style.height = `${height}px`;
            }
        } catch (error) {
            // The CSS fallback keeps the player usable if same-origin measurement is unavailable.
        }
    };

    const leaveFallback = () => {
        shell.classList.remove('iec-showcase-fallback-fullscreen');
        shell.removeAttribute('role');
        shell.removeAttribute('aria-modal');
        shell.removeAttribute('aria-label');
        document.body.classList.remove('iec-showcase-fallback-open');
        updateControl();
        fitPlayer();
        previousFocus?.focus();
    };

    const enterFallback = () => {
        shell.classList.add('iec-showcase-fallback-fullscreen');
        shell.setAttribute('role', 'dialog');
        shell.setAttribute('aria-modal', 'true');
        shell.setAttribute('aria-label', config.enterlabel);
        document.body.classList.add('iec-showcase-fallback-open');
        updateControl();
        button.focus();
    };

    button.addEventListener('click', async() => {
        if (isFallback()) {
            leaveFallback();
            return;
        }
        if (document.fullscreenElement === shell) {
            await document.exitFullscreen();
            return;
        }
        previousFocus = document.activeElement;
        try {
            if (shell.requestFullscreen) {
                await shell.requestFullscreen();
            } else {
                enterFallback();
            }
        } catch (error) {
            enterFallback();
        }
    });

    document.addEventListener('fullscreenchange', () => {
        updateControl();
        if (!isFullscreen()) {
            fitPlayer();
            previousFocus?.focus();
        }
    });

    shell.addEventListener('keydown', event => {
        if (event.key === 'Escape' && isFallback()) {
            event.preventDefault();
            leaveFallback();
        }
        if (event.key === 'Tab' && isFallback() && event.target !== button) {
            event.preventDefault();
            button.focus();
        }
    });

    const observeContent = () => {
        fitPlayer();
        try {
            contentObserver?.disconnect();
            const framedocument = player.contentDocument;
            const stage = framedocument?.getElementById('stage');
            const navigation = framedocument?.querySelector('nav');
            if (stage && navigation && window.ResizeObserver) {
                contentObserver = new ResizeObserver(fitPlayer);
                contentObserver.observe(stage);
                contentObserver.observe(navigation);
            }
        } catch (error) {
            // The CSS fallback remains active.
        }
    };

    player.addEventListener('load', observeContent);

    if (window.ResizeObserver) {
        new ResizeObserver(fitPlayer).observe(shell);
    }
    window.addEventListener('resize', fitPlayer);
    observeContent();
    window.setTimeout(observeContent, 250);
    updateControl();
};
