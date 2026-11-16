<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('transfers:prune')->hourly()->withoutOverlapping();
