# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.6.9] - 2026-10-16

### Added

- Game Matchup block for the game post template: the post's opponent (its Team term) as a card in that team's colors from `team-info/list.json`, with both teams' logos, the season type and home/away as pills, and an "All White Sox games" link to the team archive. Text switches from cream to dark whenever cream would fall below WCAG AA on the team color. In a column beside the featured image, the card matches the image's height and centers its content; in the footer it's a single row. A team missing from `list.json` gets the site's wine red and no logo, and a post without a team shows nothing (a placeholder in the editor).
- Game Post template: posts in the Games category now use their own `single-game` template, editable under Appearance > Editor > Templates, since WordPress has no per-category single templates. Until it's saved it starts as a copy of Single Posts. A template made for one specific post still wins. Needs WordPress 6.7+.

### Changed

- Season-type archives for spring training, the regular season, and a post season the Guardians missed use the same layout as team archives: a title, a one-line summary of how the season went, and a stat strip. The old four-cell standings grid is gone. Once a phase is over, games back, last ten and streak drop out, and the regular season shows its playoff result in their place.
- Team archive headers use each club's colors as the background and stripe, with text that switches from cream to dark ink when cream would fail WCAG AA contrast, or to black when ink would too (Phillies red), so all 30 teams pass.
- Post-season status works for any team, not only the Guardians: it's built from that team's own schedule and uses NL round names for National League clubs. An eliminated team's tag names the round and the team that beat them.
- The team color and contrast logic now lives in one place (`helpers/class-team-colors.php`), shared by the team archive header and the Game Matchup block.

## [1.6.8] - 2026-10-16

### Added

- Today's Game shows the players of the game under "The Guardians win/lose" once a game is final: the Guardians' top performers from MLB's feed ("José Ramírez 2-4, HR, 3 RBI") and the W/L/SV pitchers. This shows in the Game Hub and in the standalone Today's Game block.

### Changed

- The Post Season archive header now shows where the Guardians are in the bracket instead of a standings bar: the round, both teams with seeds, the series score, the next game, the post-season record, and a path from Wild Card to World Series. Past seasons show how the run ended.
- Team archive headers lead with the team: logo, name, league and season, then a stat strip that changes with the season type. Regular season shows record, division rank, run differential, games back, streak and last 10; post season shows the Wild Card result (or a bye), the last series result ("ALDS: Lost 1 – 3"), the regular-season record and the Guardians' next game; spring training shows record and win percentage. A tag beside the name says how the playoffs are going ("ALDS · Tied 2-2", "Eliminated"), or "Missed playoffs" for a team that didn't make it. Teams that played the Guardians show how that series ended ("Eliminated in ALDS by CLE"); other playoff teams show their seed, since only the Guardians' series are tracked.
- The Spring Training and Regular Season archive bar hides any stat that has no data.
- The Game Hub's post-season series card drops its "Road here" line, since the summary at the top of the page already covers earlier rounds. The "* If necessary" note stays.
- Today's Game shows the final result headline ("The Guardians win") larger: 3rem, up from 1.3rem.

### Fixed

- Stats with no data showed as "GB: —" in the season archive header instead of being left out.
- Today's Game showed the first-pitch forecast under every tab; it now shows only on Status.

## [1.6.7] - 2026-10-15

### Added

- Today's Game shows the first-pitch forecast at the ballpark (temperature, conditions, wind, rain chance) before the game starts, with a note for retractable-roof and domed parks. Uses MLB's venue coordinates and the OpenWeather 5-day forecast, fetched server-side with the `OPEN_WEATHER_MAP` key from `wp-config.php`.
- Season Settings has a Standing field on the Regular Season and Post Season tabs ("AL Central champs", "AL champions"). The regular-season standing shows after the record on the season timeline; the postseason standing replaces the live last-round result.
- Game posts tagged with their series result (Series Win, Series Lost, and series-split/tie slugs) show it as a colored pill on the homepage: in place of Read More on the lead post, after the date in the rows. The tag's name is the label.

### Changed

- Homepage posts (Home Posts pattern) are now "Belle News": a heading with an All Belle News link, the newest post large (220px diamond, pills, excerpt, Read it), then the next four as compact two-column rows (76px diamonds, no excerpt). In the rows the category and opponent move from the image into a colored line above the title. The dot separators are gone.
- Season Header is now a season timeline: one bar with spring training, regular season and postseason sized to their real dates, finished parts filled, the current part filled up to a "Today" pin, and month ticks underneath. Each part shows its record (or its dates before it starts); the postseason shows the last decided round from the live postseason status, falling back to the Post Season record field. A line at the top says what's on now ("Now: ALCS · Tied 1-1"). On phones the labels become a list under the bar. The off-season picture now sits under the bar instead of replacing it.
- Today's Game header says "Today" instead of the date on game day, matching the postseason series card. Doubleheader headings do the same.
- Today's Game Plays and Players tabs are disabled (dashed, with a tooltip) until they have something to show: Plays until first pitch, Players until MLB posts lineups. A live poll switches them on.
- Today's Game countdown is a navy scoreboard chip under a small "First pitch in" label, so it reads as a clock instead of competing with the game time. It fills in on page load instead of showing "…" for the first second.
- The first-pitch forecast only shows alongside the countdown (within a day of first pitch), and goes away the moment the countdown runs out instead of waiting for MLB to mark the game Live. Games further out no longer ask OpenWeather at all.
- Game Hub series card: the round heading and the series status ("Tied 1-1") are centered.
- The first-pitch forecast icon sits on a blue-gray disc, so pale cloud emoji don't vanish on the white card.

## [1.6.6] - 2026-10-14

### Changed

- Swapped BG and FG colors for season header, as it's now on cream BG and not red.

## [1.6.5] - 2026-10-14

### Added

- Add the Game Hub block, a front-page section that follows the season: Today's Game with the division table in the regular season, the series card in the postseason, Guardians news in Spring Training, and a Spring Training countdown with news in the off season.
- Add a "Season complete" standings card for when the Guardians are eliminated, win the World Series or miss the postseason. It shows the regular-season finish and each round reached.
- Add an optional Off Season Image setting that replaces the season header's default off-season picture.
- Show the year of the Guardians' last World Series win in the off-season header.
- Label probable pitchers' lines as postseason or regular-season stats.
- Take postseason game dates, venues and series length from MLB's schedule in the Series Generator, mark "if necessary" games, and warn when MLB hasn't published the series yet.

### Changed

- Hide Today's Game and the standings in the off season, with a note in the editor. Archive pages for a given season still show its standings.
- Switch the season header, Today's Game and the standings to the off season at the same moment.
- Load each block's CSS only on pages that use the block, in the editor too.
- Stop repeating the postseason series status in the Today's Game header.
- Enlarge the text in the standings ticker and Today's Game, and let Today's Game fill its container.

### Removed

- Remove the Wild Card season type. The Wild Card round is part of Post Season.

### Fixed

- Fix team logos stretching in the results and series blocks.
