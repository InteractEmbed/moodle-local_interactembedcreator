# InteractEmbed Creator

InteractEmbed Creator provides a contextual Moodle library and a visual editor
for accessible, reusable HTML5 presentations. Projects may live at system,
course-category, or course level. Publishing creates an immutable HTML5 package
that can be copied into the InteractEmbed activity or block.

## Requirements

- Moodle 4.5 through 5.2.
- `mod_interactembed` 0.9.0-beta9 (`2026090203`) or later.
- `block_interactembed` 0.9.0-beta33 (`2026090202`) or later.

Both consumers remain usable without Creator. Creator deliberately requires
both so every publication has the two supported Moodle destinations available.

## Installation

Install the ZIP through **Site administration > Plugins > Install plugins**, or
copy this directory to `local/interactembedcreator`, then complete the normal
Moodle upgrade. No Composer or Node command is required on the target server.

## Security model

Published content is executed by the consumers in a sandboxed iframe. The
standard profile never grants `allow-same-origin`. Package resources are served
through revision-scoped signed URLs without Moodle cookies. Communication with
the activity is limited to the versioned `interactembed:init` and
`interactembed:progress` message protocol with a per-player nonce.

Only trusted roles should receive authoring and publishing capabilities; these
capabilities carry Moodle's `RISK_XSS` flag. Editable source imports validate
archive paths, entry count, expanded size, manifest, JSON schema, and allowed
asset locations before a project is created.

## Languages and help

The interface and built-in user guide are supplied in English, French, and
Spanish. The guide is available directly from the InteractEmbed library.

## Privacy

The plugin implements Moodle's Privacy API for projects, revision authors,
publications, and related files. It does not require an external service.

## License

GNU GPL v3 or later.

## Development and support

- [Source repository](https://github.com/InteractEmbed/moodle-local_interactembedcreator)
- [Issue tracker](https://github.com/InteractEmbed/moodle-local_interactembedcreator/issues)
- [Private vulnerability reporting](https://github.com/InteractEmbed/moodle-local_interactembedcreator/security/advisories/new)

See `CONTRIBUTING.md` for contribution expectations and `SECURITY.md` for the
security-reporting policy.
