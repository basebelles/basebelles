# Base\*Belles (WordPress plugin)

This repository is the **Base\*Belles** WordPress plugin used on **[Basebelles.com](https://basebelles.com)**. It is the primary home for **custom blocks**, **data integrations**, and **site-specific behaviors** for that site live.

The plugin wires together the block editor, [Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/) block definitions, and backend helpers (notably MLB Stats API access and embed handling) so editors can drop in baseball-focused UI without duplicating PHP in the theme.

---

## Features

| Area              | Role |
|-------------------|------------|
| **Blocks**        | Registers a dedicated **Base\*Belles** block category and ships multiple **ACF-powered blocks** (see below). Each block has `block.json`, `render.php`, and block-scoped CSS. |
| **Custom data**   | Integrates with **MLB’s public Stats API** (`statsapi.mlb.com`) for Cleveland Guardians–oriented data (standings, schedules, game context, etc.), with caching and season/archive behavior defined in `features/class-api.php`. |
| **Embeds**        | Extends WordPress oEmbed/shortcode behavior for **Streamable** and **MLB video** URLs, including fallbacks when the oEmbed endpoint does not return markup (`features/class-embeds.php`, `helpers/class-streamable-oembed.php`). |
| **Site behavior** | Loads small feature modules under `features/` (comment moderation hooks, anti-spam, query tweaks for season archives, optional privacy-related HTTP filters, automatic updates policy, etc.). |
| **Belle directory** | A moderated public directory of Belles. A **WPForms** submission becomes a `belle` post in `pending` status; publishing approves it, trashing marks it spam. Roster choices for the "favorite current player" field are synced from the MLB Stats API daily. See [`docs/belles-setup.md`](docs/belles-setup.md). |

**ACF field definitions** (field groups, options screens, taxonomies, and other synced ACF JSON) live in **`acf-json/`** as the version-controlled source. Each file is a single JSON object named **`{key}.json`** (matching the top-level `"key"`). Saving a field group in wp-admin writes back to that folder via **`acf/settings/save_json`**. The plugin registers load/save paths in [`blocks/class-acf-json.php`](blocks/class-acf-json.php) (theme Local JSON paths remain; the plugin directory is appended for loading).

After deploy, use **Custom Fields → Sync** in wp-admin when ACF shows updates so the database matches the repo. A bundled **`blocks/acf-export-*.json`** full export remains optional for reference or one-off import.

If your exports include **`acfe_*`** keys from [ACF Extended](https://github.com/acf-extended/ACF-Extended), keep that plugin active where those features are required, or strip Extended-only properties if you standardize on vanilla ACF.

---

## Requirements

- **WordPress** (block editor–capable release; this plugin targets modern block + Site Editor usage).
- **Advanced Custom Fields PRO** — blocks use the `acf/` block type and `renderTemplate` in `block.json`; ACF must be active for blocks to register and render correctly.
- **PHP** compatible with your WordPress install (the codebase follows typical WordPress PHP patterns; `phpcs.xml.dist` is present for coding standards).

Optional but expected in production: object/transient caching as provided by WordPress for API responses (TTLs are defined in `features/class-api.php`).

---

## Custom blocks

All blocks are registered from `blocks/class-blocks.php` and appear under the **Base\*Belles** category in the inserter:

| Block directory | Purpose (high level) |
|-----------------|----------------------|
| `blocks/belles/` | Belle directory card grid |
| `blocks/results/` | Game / results presentation |
| `blocks/season-header/` | Season header UI |
| `blocks/season-stats-header/` | Season statistics header |
| `blocks/series/` | Series display |
| `blocks/standings/` | Standings |
| `blocks/today-game/` | Today’s game / probable pitchers |
| `blocks/streamable/` | Streamable-related block UI |

Shared front-end styling is coordinated via `basebelles.css` and per-block `block.css` files; handles are registered in `Basebelles_Blocks`.

---

## Repository layout

```
basebelles.php          # Plugin bootstrap, hooks, query var (season_year), style registration
basebelles.css          # Shared plugin styles
features/
  class-api.php                 # MLB Stats API client, caching, Guardians-focused helpers
  class-belles.php              # Belle post type, WPForms intake, moderation queue, roster sync
  class-comment-probation.php   # Comment moderation / anti-spam hooks
  class-embeds.php              # oEmbed providers, Streamable/MLB handlers, shortcode
  class-impostercide.php        # Blocks unauthenticated comments posted as registered users
  class-in-progress.php         # Dashboard widget: unpublished / in-progress posts
  class-no-tracking.php         # Strips plugin/theme telemetry from outbound HTTP requests
  class-series-generator.php    # Quick-draft generator for game-series posts (options page UI)
  class-upgrades.php            # Automatic plugin update policy
helpers/
  class-streamable-oembed.php   # Streamable oEmbed provider registration and response handling
acf-json/               # ACF Local JSON (field groups, options UI, taxonomies, etc.)
blocks/
  baseball.svg          # Block category icon
  class-acf-json.php    # acf/settings/load_json + save_json → plugin acf-json/
  class-blocks.php      # register_block_type() for each block; category; CSS enqueue
  */block.json          # Block metadata (ACF render templates)
  */render.php          # Server-side render
  */block.css           # Block styles
patterns/
  game-series.php       # Block pattern: game-series post scaffold
team-info/
  list.json             # Static team data
  logos/                # Per-team logo PNGs (slug-named, e.g. guardians.png)
```

---

## Installation

1. Clone or copy this directory into your WordPress `wp-content/plugins/` folder (folder name can remain `basebelles` or match your deployment convention).
2. Activate **Base\*Belles** in **Plugins** in wp-admin.
3. Ensure **ACF PRO** is installed and active. With Local JSON wired to `acf-json/`, open **Custom Fields** in wp-admin and **Sync** any definitions that show as available so the site database matches the repo.

There is no build step for the plugin itself—it ships as the PHP, CSS, and block assets committed here, with nothing compiled or bundled. Composer and npm are used only to install test tooling (see [Testing](#testing)); neither is needed to run the plugin, and neither is deployed.

---

## Configuration & data

- **Season and API behavior** are driven through ACF/options-style values consumed in `features/class-api.php` (e.g. season year, season type). Adjust those in WordPress per environment.
- **URLs / taxonomies**: The plugin registers the public query variable `season_year` and uses it with the `season-type` taxonomy archive query so season URLs do not collide with WordPress core’s reserved `year` query var (see `basebelles.php`).

---

## Development

- **Coding standards**: `phpcs.xml.dist` is provided for PHPCS runs against the PHP in this plugin.
- **Version**: The canonical plugin version is set in the plugin header in `basebelles.php` (keep internal `$version` usage in sync when bumping releases).

### Testing

Two suites, both dev-only. `.github/workflows/test.yaml` runs them on every push to `trunk` and on every pull request.

```bash
# PHP — block templates and panel logic
composer install
composer test          # or: vendor/bin/phpunit

# JavaScript — the today-game block script
npm install
npm test               # node --test, no test framework beyond jsdom
```

**What they cover.** `tests/php/PanelsTest.php` covers `Basebelles_Today_Game_Panels`: phase resolution across every `detailedState` MLB is known to send, delay labels and reasons, score/stats panel routing, doubleheader switcher labels, and which game a doubleheader opens on. `tests/php/StandingsBlockTest.php` and `tests/php/TodayGameBlockTest.php` include the block templates and assert on the markup they emit. `tests/js/` drives `today-game.js` in jsdom: game switching, state surviving a switch, and the delay badge and polling behaviour.

**What they are not.** These are unit tests, not WordPress integration tests. Nothing loads WordPress or touches a database — `tests/bootstrap.php` stubs the handful of WordPress functions the code calls and replaces `Basebelles_API` with a fake whose payloads each test sets. So they prove markup and branching, not that WordPress and the MLB API hand these templates the data they expect. `tests/Fixtures.php` is where the assumed payload shapes live; if `Basebelles_API`'s normalisation changes, update it there.

**Adding a test.** Drop a `*Test.php` in `tests/php/` (any class extending `PHPUnit\Framework\TestCase`), or a `*.test.mjs` in `tests/js/`. Both are picked up automatically. If you add a dev dependency, add it to the `EXCLUDE` list in `.github/workflows/deploy.yaml` too — that rsync runs `--delete` over the whole repo, so anything not excluded ends up in the live plugin directory.

---

## License

This project is licensed under the **GNU General Public License v2.0** — see [LICENSE](LICENSE).

---

## Author

**Ipstenu** — plugin URI and upstream repository: [github.com/basebelles/basebelles](https://github.com/basebelles/basebelles).
