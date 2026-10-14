# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
