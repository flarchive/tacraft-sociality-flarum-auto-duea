<?php

namespace TacraftSociality\AutoDuea;

use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;

return [
    (new Extend\Console())
        ->command(Console\StatusCommand::class)
        ->command(Console\RunCommand::class)
        ->command(Console\SweepCommand::class)
        ->schedule(Console\SweepCommand::class, function (Event $event) {
            // The command itself checks whether automatic deletion is enabled and
            // whether the configured interval has elapsed, so running it hourly
            // is cheap and keeps the setting fully in control.
            $event->hourly()->withoutOverlapping(30);
        }),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->get('/auto-duea/status', 'auto-duea.status', Api\StatusController::class)
        ->post('/auto-duea/run', 'auto-duea.run', Api\RunController::class),

    (new Extend\Settings())
        ->default('auto_duea_enabled', false)
        ->default('auto_duea_interval_hours', 24)
        ->default('auto_duea_grace_days', 7)
        ->default('auto_duea_batch', 100),
];
