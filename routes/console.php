<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Detection runs on a schedule, not on a page view.
 *
 * Every fifteen minutes is a deliberate cadence: often enough that a problem
 * is found the same morning it appears, rare enough that fourteen detectors
 * across every tenant stay cheap. Applying an action still re-runs a targeted
 * subset synchronously, so a finding retires itself the moment it is fixed
 * rather than waiting for the next tick.
 */
Schedule::command('nexabi:detect')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/**
 * The morning briefing, once a day.
 *
 * After the detector sweep rather than before it, so the digest reports what
 * the engine found this morning and not what it found last night. Skips any
 * tenant with nothing flagged.
 */
Schedule::command('nexabi:digest')
    ->dailyAt('07:30')
    ->withoutOverlapping();
