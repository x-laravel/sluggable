# Sluggable for Laravel

<p align="center">
<a href="https://github.com/X-Laravel/sluggable/actions/workflows/tests.yml"><img src="https://github.com/X-Laravel/sluggable/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
<a href="https://packagist.org/packages/x-laravel/sluggable" rel="nofollow"><img src="https://img.shields.io/packagist/v/x-laravel/sluggable" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/x-laravel/sluggable" rel="nofollow"><img src="https://img.shields.io/packagist/php-v/x-laravel/sluggable" alt="PHP Version"></a>
<a href="https://packagist.org/packages/x-laravel/sluggable" rel="nofollow"><img src="https://img.shields.io/packagist/dt/x-laravel/sluggable" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/x-laravel/sluggable" rel="nofollow"><img src="https://img.shields.io/packagist/l/x-laravel/sluggable" alt="License"></a>
</p>

Slugs for Eloquent models: generated from the model's own attributes with language-aware
transliteration, kept unique across soft-deleted and scoped rows, retried when two requests race
for the same slug, and remembered after they change so that old links keep finding the record.

## Requirements

| Package version | PHP | Laravel |
|-----------------|-----|---------|
| 1.x | 8.3+ | 12, 13 |

## Installation

```bash
composer require x-laravel/sluggable
php artisan vendor:publish --tag=sluggable-migrations
php artisan migrate
```

Publish the config to change the defaults:

```bash
php artisan vendor:publish --tag=sluggable-config
```

## Making a Model Sluggable

Implement the `Sluggable` interface, use the `HasSlug` trait and name the attribute the slug is built
from:

```php
use XLaravel\Sluggable\Concerns\HasSlug;
use XLaravel\Sluggable\Contracts\Sluggable;

class Event extends Model implements Sluggable
{
    use HasSlug;

    protected string|array $slugSource = 'title';
}
```

On every save the trait:

- generates a slug from the source when the column is empty: `Rock Konseri` becomes `rock-konseri`;
- numbers it when it is taken: `rock-konseri-2`, `rock-konseri-3`;
- lowercases a slug that was set by hand.

A slug that is already set is not touched when the source changes, so shared links stay stable.

Every behaviour can be tuned per model with a property:

| Property | Default | Meaning |
|----------|---------|---------|
| `$slugSource` | required | Attribute, or list of attributes joined with a space, the slug is built from |
| `$slugColumn` | `'slug'` | Column that holds the slug |
| `$regenerateSlug` | `regenerate` config | Build a new slug when the source changes |
| `$slugHistory` | `true` | Keep old slugs for this model |
| `$slugReservedFor` | `history.reserve_for` config | Days an old slug stays reserved; `null` forever, `0` not at all |
| `$slugMaxLength` | `max_length` config | Longest generated slug |

Override `slugSource()` when the source has to be computed, and `slugUniqueQuery()` when uniqueness
should be checked against a narrower query. By default it ignores every global scope, so
soft-deleted rows and rows hidden by tenant or approval scopes still count — as they do for the
database's unique index.

## Configuration

| Key | Default | Meaning |
|-----|---------|---------|
| `separator` | `'-'` | Between words and before the number |
| `language` | `'en'` | Transliteration language; use `'tr'` for Turkish |
| `max_length` | `255` | Longest generated slug |
| `first_suffix` | `2` | Number given to the first duplicate |
| `regenerate` | `false` | Build a new slug when the source changes |
| `retries` | `3` | Attempts when a generated slug loses a race |
| `history.enabled` | `true` | Keep and resolve old slugs |
| `history.connection` | `null` | Connection of the history table |
| `history.table` | `'slug_history'` | Name of the history table |
| `history.reserve_for` | `90` | Days an old slug stays reserved for its record |
| `history.prune_after` | `365` | Days after which history rows are pruned |

Put the history table on the same connection as your models, so a slug change and its history row
are written in one transaction.

## Slug History

When a slug changes, the old one is written to the history table. Looking a record up by slug checks
both the current slug and the history:

```php
$event = Event::findBySlug($slug);

$event = Event::query()->whereSlug($slug)->with('venue')->firstOrFail();
```

Compare the slug you were given with `$event->slug` to send the visitor to the current address.

Route model binding follows the history too when the route key is the slug column.

### Reservation

An old slug stays reserved for its record for `reserve_for` days: no other record can take it, and
the generator skips it. The record itself can always go back to an old slug. When the period ends,
another record may take the slug; the old record's history row is then removed and the address
belongs to the new record.

### Pruning

`SlugHistory` is prunable. Laravel only discovers prunable models in `app/Models`, so name it when
you schedule the command:

```php
Schedule::command('model:prune', ['--model' => [\XLaravel\Sluggable\Models\SlugHistory::class]])->daily();
```

A pruned row no longer resolves. Keep `prune_after` longer than `reserve_for`; a row that is pruned
earlier also ends its reservation.

Force-deleting a record removes its history. Soft-deleting keeps it.

## Validating Slugs Set by Hand

`Rules\Slug` checks the format: lowercase letters, digits and the separator, never at the edges or
twice in a row. `Rules\UniqueSlug` checks that no other record uses the slug or keeps it reserved:

```php
use XLaravel\Sluggable\Rules\Slug;
use XLaravel\Sluggable\Rules\UniqueSlug;

$request->validate([
    'slug' => ['required', new Slug, new UniqueSlug($event)],
]);
```

Pass the model instance to ignore its own current and old slugs, or the class name for a new record.
Laravel's `unique` rule cannot see the history table.

`slug` is also available as a string alias for the format check.

## Reacting to a Changed Slug

The trait dispatches `XLaravel\Sluggable\Events\SlugChanged` with the model and the old and new
slugs after the surrounding transaction commits. It also adds two model events for observers:

- `slugChanging` runs before the row is written; return `false` to cancel the save.
- `slugChanged` runs right after the row is written; `getOriginal()` still returns the old slug.

```php
Event::slugChanged(fn (Event $event) => Cache::forget("event:{$event->getOriginal('slug')}"));
```

Giving a new model its first slug does not count as a change.

## Concurrent Saves

Two requests can pick the same free slug at the same moment. When the trait generated the slug and
the insert fails on the slug's unique index, it generates a new one and tries again. A slug that was
set by hand is never retried. Inside an open transaction the attempt runs in a savepoint, so a
failed attempt does not break the surrounding transaction.

The retry lives in the trait's `save()` method. If your model overrides `save()`, call
`parent::save()` from it to keep the retry. A model cannot use this trait together with another
trait that also overrides `save()`.

## Customising the Messages

The rules come with Turkish and English messages:

```bash
php artisan vendor:publish --tag=sluggable-translations
```

## Testing

```bash
composer install
composer test
composer analyse
```

## License

Open source software licensed under the [MIT license](LICENSE).
