<?php

use XLaravel\Sluggable\Models\SlugHistory;

return [

    /*
    |--------------------------------------------------------------------------
    | Slug Generation
    |--------------------------------------------------------------------------
    |
    | How a slug is built from a model's source attributes.
    |
    | separator     Character placed between words and before the number that
    |               keeps a slug unique ("concert-2").
    | language      Language used to transliterate letters to ASCII. Use "tr"
    |               so that "ı" becomes "i" and "İ" becomes "i".
    | max_length    Longest slug the generator returns, in characters.
    | first_suffix  Number appended to the first duplicate: "concert",
    |               "concert-2", "concert-3".
    | regenerate    Build a new slug when the source attributes change. Keep it
    |               off so shared links stay stable; a model can turn it on
    |               with a $regenerateSlug property.
    | retries       How many times a save is retried when another request takes
    |               the generated slug at the same moment.
    |
    */

    'separator' => '-',

    'language' => 'en',

    'max_length' => 255,

    'first_suffix' => 2,

    'regenerate' => false,

    'retries' => 3,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | connection  Database connection of the history table. Use the same
    |             connection as your models so both are written in one
    |             transaction. Null uses the default connection.
    |
    */

    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Slug History
    |--------------------------------------------------------------------------
    |
    | When a slug changes, the old one is kept so that old links still find
    | the record. A model can opt out with a $slugHistory property.
    |
    | enabled      Keep old slugs and resolve them. When disabled, nothing is
    |              written, old slugs are not resolved and nothing is reserved;
    |              existing rows are left as they are.
    | reserve_for  Days an old slug stays reserved for its record; no other
    |              record can take it in that time. Null reserves it for as
    |              long as the row exists, 0 does not reserve it at all. A
    |              model can override it with a $slugReservedFor property.
    | table        Name of the history table.
    | model        Eloquent model of the table. It must extend the package's
    |              own model: XLaravel\Sluggable\Models\SlugHistory.
    |
    */

    'history' => [
        'enabled' => true,
        'reserve_for' => 90,
        'table' => 'slug_history',
        'model' => SlugHistory::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Old slugs are deleted from the history once they reach a given age. The
    | package schedules model:prune to remove them.
    |
    | enabled   Schedule the pruning.
    | after     Days after which history rows are deleted. Null keeps them
    |           forever.
    | schedule  When the pruning runs, written in one of two ways:
    |           A cron expression:
    |             "30 3 * * *"        every day at 03:30
    |
    |           The name of a Laravel schedule frequency method, with its
    |           arguments after a colon, separated by commas:
    |             "daily"             every day at 00:00
    |             "dailyAt:03:30"     every day at 03:30
    |             "twiceDaily:1,13"   every day at 01:00 and 13:00
    |             "weeklyOn:1,04:00"  every Monday at 04:00
    | chunk     Rows deleted by each query.
    |
    */

    'prune' => [
        'enabled' => true,
        'after' => 365,
        'schedule' => 'daily',
        'chunk' => 1000,
    ],

];
