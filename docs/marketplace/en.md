# InteractEmbed Creator

## Short description

Create accessible, reusable interactive HTML5 presentations in Moodle and
publish them to the InteractEmbed Activity and Block without programming.

## Full description

InteractEmbed Creator is a native Moodle authoring library for interactive
16:9 HTML5 presentations. Authors can combine text, images, audio, video,
buttons, quizzes and branching navigation in a structured visual editor.

Projects can be stored at site, category or course level. Publishing creates
an immutable package that can be selected from the InteractEmbed Activity or
Block. Consumers retain their own portable snapshot, while the editable source
remains safely managed in the library. Course projects follow Moodle backup
and restore; shared site and category projects remain independent resources.

The plugin includes an accessibility checker and an interactive user guide.
Published content runs in the consumers' sandboxed, credentialless iframe.
Creator uses Moodle capabilities, Files API, Privacy API, backup and restore,
and context lifecycle events. It requires both the InteractEmbed Activity and
the InteractEmbed Block; those two plugins can still operate independently.

No external service, subscription, API key, Composer command or Node build is
required on the Moodle server.

## Installation

1. Install `mod_interactembed` 1.0.0.
2. Install `block_interactembed` 1.0.0.
3. Install `local_interactembedcreator` 1.0.0.
4. Complete the normal Moodle upgrade and review the role capabilities.

Supported Moodle versions: 4.5 through 5.2.

## Links

- Source: https://github.com/InteractEmbed/moodle-local_interactembedcreator
- Issues: https://github.com/InteractEmbed/moodle-local_interactembedcreator/issues
- Security: https://github.com/InteractEmbed/moodle-local_interactembedcreator/security/advisories/new
