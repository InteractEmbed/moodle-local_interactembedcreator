# Change log

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
