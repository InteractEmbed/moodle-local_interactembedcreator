# Change log

## 1.0.0 — 2026-09-07

- First stable release of the contextual InteractEmbed library and visual
  HTML5 presentation editor.
- Provide system, category and course scopes, reusable immutable publications,
  accessible 16:9 authoring, media, quizzes, branching and multilingual help.
- Integrate with the InteractEmbed Activity and Block through portable,
  sandboxed package snapshots.
- Support native Moodle backup, restore, privacy and context lifecycle APIs.
- Validate the coordinated suite on IOMAD/Moodle 4.5 and Moodle 5.2, plus the
  public Moodle 4.5–5.2 MariaDB/PostgreSQL CI matrix.

## 0.9.0-rc17 — 2026-09-07

- Correct the plugin-file callback PHPDoc for system-context publications so
  the strict Moodle Plugin CI documentation check passes.

## 0.9.0-rc16 — 2026-09-07

- Delete course- and category-scoped project graphs and files when their Moodle
  context is deleted, preventing orphaned revisions and publications.
- Reuse the same lifecycle cleanup for Moodle Privacy API erasure requests.
- Add real course and category deletion regression coverage.

## 0.9.0-rc15 — 2026-09-07

- Allow publication and asset files stored in the site or category library to
  be served when Moodle invokes the pluginfile callback without a course.
- Add regression coverage for the system-context pluginfile contract.

## 0.9.0-rc14 — 2026-09-07

- Include course-scoped projects, revision history, assets, thumbnails, and
  immutable publications in Moodle course backup and restore.
- Remap restored project, publication, and user identifiers so a course can be
  copied safely on the same site or transferred to another Moodle site.

## 0.9.0-rc13 — 2026-09-07

- Complete PHP API and Mustache template documentation required by Moodle CI.
- Remove duplicate language identifiers in English, French, and Spanish.
- Use Moodle confirmation dialogs for destructive editor actions and pass the
  JavaScript lint rules on every supported Moodle branch.
- Keep the mini-course localization test independent of optional core language
  packs in the test environment.

## 0.9.0-rc12 — 2026-09-07

- Rename database tables to the full Frankenstyle component prefix required by
  Moodle, with an in-place upgrade that preserves projects, revisions, and
  publications.

## 0.9.0-rc11 — 2026-09-02

- Queue early progress initialisation until validated project data is ready.
- Validate the project response and message nonce before reporting progress.
- Require the hardened InteractEmbed activity beta9 and block beta33.
- Validate signed cookieless CORS delivery with HTTP CSP sandboxing in both
  consumers on Moodle/IOMAD 4.5 and Moodle 5.2.

## 0.9.0-rc9 — 2026-09-02

- Add responsive sizing and an accessible fullscreen control to the bundled
  interactive mini-course.

## 0.9.0-rc7 — 2026-09-02

- Add system, category, and course library scopes with independent contextual
  copies and inherited publication discovery.

## 0.9.0-rc1 — 2026-09-01

- First release candidate of the visual authoring library, source package
  format, immutable publications, consumer API v1, and multilingual guide.
