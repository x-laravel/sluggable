# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-14

### Added

- `Contracts\Sluggable` interface and `Concerns\HasSlug` trait: language-aware slug generation from
  one or more attributes, numbered duplicates (`slug-2`), uniqueness checked across global scopes,
  optional regeneration when the source changes, all tunable per model.
- Slug history with `whereSlug()`, `findBySlug()` and route model binding that resolve old slugs,
  a configurable reservation period for old slugs and pruning through `model:prune`, scheduled by
  the package.
- Retry with savepoints when a generated slug loses a race on the unique index.
- `Events\SlugChanged`, dispatched after commit, and `slugChanging` / `slugChanged` model events.
- `Rules\Slug` format rule, `Rules\UniqueSlug` rule that also sees reserved slugs, and the `slug`
  string alias, with Turkish and English messages.

[1.0.0]: https://github.com/X-Laravel/sluggable/releases/tag/v1.0.0
