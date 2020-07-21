<?php

namespace App\Console;

use App\Console\Commands\PutCommand;
use App\Console\Commands\SpywordsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use App\Console\Commands\MailerCommand;
use App\Console\Commands\PositionCommand;
use App\Console\Commands\SemanticCommand;
use App\Console\Commands\WordstatCommand;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        SemanticCommand::class,
        WordstatCommand::class,
        PositionCommand::class,
        SpywordsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
