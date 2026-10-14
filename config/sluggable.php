<?php

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
    | Slug History
    |--------------------------------------------------------------------------
    |
    | When a slug changes, the old one is kept so that old links still find
    | the record. A model can opt out with a $slugHistory property.
    |
    | enabled      Keep old slugs and resolve them. When disabled, nothing is
    |              written, old slugs are not resolved and nothing is reserved;
    |              existing rows are left as they are.
    | connection   Database connection of the history table. Use the same
    |              connection as your models so both are written in one
    |              transaction. Null uses the default connection.
    | table        Name of the history table.
    | reserve_for  Days an old slug stays reserved for its record; no other
    |              record can take it in that time. Null reserves it for as
    |              long as the row exists, 0 does not reserve it at all. A
    |              model can override it with a $slugReservedFor property.
    | prune_after     Days after which history rows are deleted. Null keeps
    |                 them forever.
    | prune_schedule  How often the package schedules model:prune for the
    |                 history table: a schedule frequency such as "daily" or
    |                 "hourly", or a cron expression. Null leaves scheduling
    |                 to you.
    |
    */

    'history' => [
        'enabled' => true,
        'connection' => null,
        'table' => 'slug_history',
        'reserve_for' => 90,
        'prune_after' => 365,
        'prune_schedule' => 'daily',
    ],

];
