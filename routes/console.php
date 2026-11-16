<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('transfers:prune')->hourly()->withoutOverlapping();

Schedule::command('geoip:update')
    ->weekly()
    ->when(fn () => (bool) config('geoblock.enabled'));
