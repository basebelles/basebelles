# Base*Belles plugin

## Versioning

- Any change to CSS needs a version bump, because `Basebelles::$version` cache-busts the enqueued
  CSS and JS. Without a bump, visitors keep the old stylesheet.
- Bump once per PR (patch level, e.g. 1.6.3 → 1.6.4), not once per commit. If the PR already
  carries an unreleased bump, further CSS changes in it don't need another.
- Use `npm run bump X.Y.Z`, which keeps `basebelles.php`, `package.json` and both copies in
  `package-lock.json` in step. Don't edit the versions by hand.
