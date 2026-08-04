# CODEBUDDY.md

This file provides guidance to CodeBuddy Code when working with code in this repository.

## Overview

Argon (Enhanced By Asunano) is a classic **WordPress PHP theme** (not a block theme, no Composer/autoloader/build framework). It is a drop-in theme: upload the folder to `wp-content/themes/` and activate it. Minimum WordPress `4.4`.

- This repo is a **fork** of `solstice23/argon-theme`, maintained at `github.com/Asunano/argon-theme` (branch master). The upstream `solstice23` repo has frozen 1.x and moved development to its `dev` branch — do not assume upstream structure here.
- GPL-3.0. You **must keep the "Argon" theme name and link in the footer** (you may remove the author credit, but not the Argon name/link). See `footer.php`.
- Text domain: `argon`. Translations live in `languages/` (`en_US`, `ru_RU`, `zh_TW`). `zh_CN` falls back to the bundled strings.

## Versioning (release-critical)

- The **`Version:` header in `style.css` is the single source of truth** for the theme version. On a tag push, `.github/workflows/release.yml` aborts the release if `style.css` Version does not exactly match the `vX.Y.Z` git tag.
- `version.json` is auto-synced by the release workflow (do not hand-edit it for releases). It feeds the in-theme update checker (`theme-update-checker/`).
- **To cut a release:** bump the `Version:` line in `style.css` to the new version, commit, then push a tag `vX.Y.Z`.

## Common commands

There is **no PHP test suite and no PHP linter configured** (no `composer.json`, no `phpunit`). Validate PHP manually:

- **PHP syntax check** (if PHP CLI is installed): `php -l functions.php` (run per file you edit).
- **Build Gutenberg blocks** (the only npm-based build in the repo):
  - `cd gutenberg && npm install`
  - `cd gutenberg && npm run build`  (production build → writes `gutenberg/dist/`)
  - `cd gutenberg && npm start`     (watch/dev mode)
  - Built with `cgb-scripts` (Create Guten Block). Source is `gutenberg/src/*`; committed output is `gutenberg/dist/{blocks.build.js, blocks.editor.build.css, blocks.style.build.css}`.
- **Theme CSS (reproducible):** `assets/scss/argon.scss` is the Sass source; compile to `assets/css/argon.min.css` with `sass assets/scss/argon.scss assets/css/argon.min.css`. (No `sass` CLI in this sandbox — install it or build locally.)
- **Merged bundles (NOT reproducible in-repo):** `assets/argon_css_merged.css` and `assets/argon_js_merged.js` are hand-assembled, committed bundles with **no build script in the repo**. They concatenate the theme CSS/JS with vendored libraries from `assets/vendor/` (fancybox5, highlight, pickr, nucleo, font-awesome, jquery, jquery-pjax-plus, dragula, headroom, etc.). **Do not attempt to regenerate them from `assets/vendor/`** — the exact concatenation order/transform is not captured here, and a naive rebuild will diverge from the shipped bundle. To change frontend behavior/appearance, edit the real sources (`style.css`, `assets/scss/`, `argontheme.js`) and also patch the corresponding section of the merged bundle directly; flag the divergence for the maintainer to re-bundle.
- **CDN gotcha:** when the `argon_assets_path` option is a CDN value (`jsdelivr` / `sourcegcdn` / `jsdelivr_gcore` / …), `$GLOBALS['assets_path']` resolves to the jsDelivr/sourcegcdn URL and **local asset edits will not load**. Set the option to the default (local `template_url`) to test changes locally.
- **Release packaging** is fully automated by `release.yml` on tag push (builds Gutenberg, zips everything except `.git`, `.github`, `node_modules`, `*.md`, `CODEBUDDY.md`, etc., syncs `version.json`, creates the GitHub Release with `argon-theme.zip`).

## Architecture

### Entry points and the "spine" (`functions.php`)

`functions.php` (~139KB) is the spine and is loaded on every request. Key responsibilities, in load order:

1. **`argon_get_option($option, $default)`** — a request-local cached `get_option()` wrapper. Prefer this over raw `get_option()` to avoid repeated DB reads. Cache is only populated when the value differs from the default (so different callers' defaults don't collide).
2. **Asset path resolution** — `$GLOBALS['assets_path']` and `$GLOBALS['theme_version']` are set from the `argon_assets_path` option. Values: `jsdelivr` / `sourcegcdn` / `jsdelivr_gcore` / `jsdelivr_fastly` / `jsdelivr_cf` (CDN base URLs pinned to the theme version), `custom` (supports a `%theme_version%` token), or default (local `template_url`). Almost every enqueued asset URL uses `$GLOBALS['assets_path']` — **always reference assets through this global**, never a hardcoded path.
3. **i18n / locale** — `argon_locate_filter()` maps WP locales to `zh_CN`/`en_US`/`zh_TW`/`ru_RU`; filters `theme_locale` for the `argon` domain.
4. **Upgrade migrations** — a `version_compare` block near the top remaps old options when the stored `argon_last_version` is behind; add new migration branches there when you rename/repurpose options.
5. **AJAX handlers** — e.g. `upvote_post()` (article-level like, added by this fork; see "Enhanced" group below). All use `argon_verify_ajax_nonce()` and `wp_ajax_*` / `wp_ajax_nopriv_*` hooks.
6. **Pjax integration** — the theme uses jquery-pjax-plus and replaces only `#primary` without refreshing `<head>`. Consequences you must respect: `argon_enqueue_block_library_always()` force-enqueues `wp-block-library`/`wp-block-library-theme` on every page so galleries/tables keep their layout under Pjax; scripts that run during parse (e.g. `socialShare`) must not be `defer`ed. The `script_loader_tag` / `style_loader_tag` filters encode these defer/async rules — read them before changing how a vendor script loads.
7. `include_once(get_template_directory() . '/settings.php');` — the entire admin settings UI.

`functions.php` also `require_once`s bundled libraries: `theme-update-checker/plugin-update-checker.php` (self-update from GitHub), `emotions.php` (emoji/emotion dataset), `useragent-parser.php` (comment UA icons), `parsedown.php` (Markdown in comments).

### Settings page (`settings.php`, ~165KB)

Holds the whole admin UI (`themeoptions_page()`) and the option persistence layer: `argon_update_option()`, `argon_update_option_allow_tags()`, `argon_update_option_checkbox()`, `argon_update_themeoptions()`. Options follow the `argon_*` naming convention and are read via `argon_get_option()`. When adding a new user-facing option, add the field render + the save handler here and the read in `functions.php`/templates via `argon_get_option()`.

### Frontend asset pipeline (`header.php`)

`header.php` enqueues, in order: `assets/argon_css_merged.css`, `style.css` (the main theme stylesheet, which also carries the WP theme header), optional `googlefont` (async), and conditionally `fancybox5`, `highlight`, `highlight-style`, `pickr-style`. JS: `assets/argon_js_merged.js` is enqueued **synchronously in `<head>`** because it defines jQuery(`$`) and `socialShare` that inline scripts rely on; other vendor scripts (`fancybox5`, `highlight`, `highlight-ln`, `nouislider`, `pickr`) are `defer`ed via the `script_loader_tag` filter. The main theme behavior script `argontheme.js` is **not** enqueued — it is injected by an inline `<script defer>` in `footer.php:54` (so it won't show up in `header.php`'s enqueue list and is not subject to `script_loader_tag`). When editing frontend behavior/appearance, change `style.css` / `assets/scss/` / `argontheme.js` (sources), then patch the merged bundles as noted above.

### Template structure

- Root templates: `index.php`, `single.php`, `archive.php`, `page.php`, `search.php`, `404.php`, `comments.php`, `footer.php`, `sidebar.php`, plus feature templates `shuoshuo.php`, `msgboard.php`, `timeline.php`, `emotions.php`.
- `template-parts/`: `content-preview-1/2/3.php` (the three article-list layouts, selected by `argon_article_list_layout`), `content-single.php`, `content-shuoshuo*.php`, `share.php`, `emotion-keyboard.php`. `index.php` dispatches to these via `get_template_part()`.
- Shortcodes and many display helpers are defined inline in `functions.php`.

### Gutenberg blocks (`gutenberg/`)

Registered by `argon_init_gutenberg_blocks()` in `functions.php` (priority on `init`), which enqueues the **committed build** `gutenberg/dist/blocks.build.js` + `blocks.editor.build.css`. Source blocks live in `gutenberg/src/` as ES modules (`blocks.js` imports `alert`, `admonition`, `collapse`, `github`, `timeline`, `progressbar`, `todolist`, `tabpanel`, plus `i18n`). After editing a block source, run `npm run build` in `gutenberg/` so `dist/` is updated before release. (Note: `gutenberg/src/init.php` is leftover boilerplate from Create Guten Block and is **not** loaded — ignore it.)

### "Enhanced By Asunano" additions

This fork adds features on top of upstream, grouped under an **Enhanced** settings section (e.g. article-level like `upvote_post()`, structured data, real-time search). These are clearly marked with `Enhanced` banners in `functions.php`. When extending fork-only behavior, keep the code under those markers and gate behind the corresponding `argon_enable_*` option so upstream merges stay clean.

### Bundled third-party code

- `theme-update-checker/` — YahnisElsts plugin-update-checker (self-updating from GitHub releases). Treat as vendored; do not modify.
- `parsedown.php`, `useragent-parser.php` — vendored libraries.
- `assets/vendor/` — fancybox5, highlight.js (+ line-numbers), pickr, nouislider, etc. Loaded conditionally from `header.php`.
