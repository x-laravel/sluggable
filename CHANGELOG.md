# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-19

### Added

- `Contracts\HasSlug` interface, through which a model names the attribute or attributes its slug
  is built from, and `Concerns\Sluggable` trait.
- Language-aware slug generation with numbered duplicates (`slug-2`), uniqueness checked across
  global scopes, optional regeneration when the source changes, all tunable per model.
- Retry with savepoints when a generated slug loses a race on the unique index.
- Slug history: old slugs resolve through `whereSlug()`, `findBySlug()` and route model binding and
  stay reserved for their record for a configurable period.
- Pruning of old history rows through `model:prune`, scheduled by the package with a configurable
  frequency, age and chunk size.
- `Events\SlugChanged`, dispatched after commit, and `slugChanging` / `slugChanged` model events.
- `Rules\Slug` format rule, `Rules\UniqueSlug` rule that also sees reserved slugs, and the `slug`
  string alias, with Turkish and English messages.
- Configurable connection, history table and history model.

[1.0.0]: https://github.com/x-laravel/sluggable/releases/tag/v1.0.0
