<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk on which uploaded transfer files are stored. The
    | "local" disk stores files privately in storage/app/private.
    |
    */

    'disk' => env('TRANSFERS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | chunk_size: size (bytes) of each uploaded chunk. PHP's post_max_size and
    | the web server's body size limit must be larger than this value.
    | max_file_size: maximum size (bytes) of a single file, 0 = unlimited.
    |
    */

    'chunk_size' => (int) env('TRANSFERS_CHUNK_SIZE', 10 * 1024 * 1024),

    'max_file_size' => (int) env('TRANSFERS_MAX_FILE_SIZE', 0),

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | Selectable expiry options in days (0 = never expires) and the default.
    |
    */

    'expiry_options' => [1, 3, 7, 14, 30, 90, 0],

    'default_expiry_days' => (int) env('TRANSFERS_DEFAULT_EXPIRY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    |
    | Unpublished drafts (e.g. aborted uploads) older than this many hours
    | are deleted by the transfers:prune command.
    |
    */

    'draft_lifetime_hours' => (int) env('TRANSFERS_DRAFT_LIFETIME_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Download notifications
    |--------------------------------------------------------------------------
    |
    | Minimum number of minutes between two download notification mails for
    | the same transfer.
    |
    */

    'notification_cooldown_minutes' => (int) env('TRANSFERS_NOTIFICATION_COOLDOWN', 60),

];
