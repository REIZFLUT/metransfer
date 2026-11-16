<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('transfers:prune')->hourly()->withoutOverlapping();

Schedule::command('geoip:update')
    ->weekly()
    ->when(fn () => filled(config('geoblock.maxmind.license_key')));
