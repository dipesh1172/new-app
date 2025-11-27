<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Console Kernel.
 */
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     */
    protected function schedule(Schedule $schedule)
    {
        // List of outbound only calls that were not successful, also accepts array of emails
        // $schedule->command('check:outbound-queue --dryrun --limit=1440')->timezone('America/Los_Angeles')->dailyAt('02:00')->emailOutputTo('engineering@tpv.com', true);

        //$schedule->command('queue:generate-stats-jobs')->onOneServer()->everyMinute()->timezone('America/Los_Angeles')->between('05:55', '10:00');
        //$schedule->command('queue:clear "" stats')->onOneServer()->timezone('America/Los_Angeles')->dailyAt('10:05');

        $schedule->command('auth:reset-expired-pw-tokens')->onOneServer()->everyThirtyMinutes();

        // Automatically create new permissions from route names daily
        // $schedule->command('permissions:update-from-routes')->onOneServer()->timezone('America/Los_Angeles')->dailyAt('01:00');

        // Focus Required
        $schedule->command('reset:tokens')->onOneServer()->timezone('America/Los_Angeles')->daily();
        // $schedule->command('live:enrollments')->onOneServer()->everyFiveMinutes()->withoutOverlapping();
        // $schedule->command('create:enrollment')->onOneServer()->withoutOverlapping()->everyThirtyMinutes();
        $schedule->command('twilio:logout')->onOneServer()->timezone('America/Los_Angeles')->daily();

        //update last sales dates
        // $schedule->command('brand:sales:update')->onOneServer()->timezone('America/Los_Angeles')->hourly()->between('05:00', '10:00');

        // Twilio Specific
        // $schedule->command('clear:wrapping')->everyMinute();

        // Clear out old voip resets so we get fresh info
        $schedule->command('voip:reset')->onOneServer()->monthlyOn(1, '05:00')->timezone('America/Chicago');

        // HRTPV Commands
        // $schedule->command(
        //     'process:hireflow'
        // )->everyFiveMinutes()->withoutOverlapping()->between(
        //     '9:00',
        //     '21:00'
        // )->timezone('America/Chicago');

        // Stats
        // $schedule->command('InboundCallData:update')->everyFiveMinutes()->withoutOverlapping()->between(
        //     '7:00',
        //     '24:00'
        // )->timezone('America/Chicago');
        // $schedule->command('twilio:call:dashboard')->everyMinute();
        $schedule->command(
            'twilio:call:dashboard --clean'
        )->onOneServer()->withoutOverlapping()->everyThirtyMinutes();
        // $schedule->command('daily:stats')
        //     ->everyThirtyMinutes()->withoutOverlapping()->between(
        //         '7:00',
        //         '24:00'
        //     )->timezone('America/Chicago');
        // $schedule->command(
        //     'stats:product --hours=72'
        // )->timezone('America/Chicago')->daily();
        // $schedule->command('stats:product')->everyMinute()->withoutOverlapping()->between(
        //     '7:00',
        //     '24:00'
        // )->timezone('America/Chicago');

        // Recordings
        // $schedule->command('get:recordings')->everyFiveMinutes()->withoutOverlapping()->between(
        //     '7:00',
        //     '24:00'
        // )->timezone('America/Chicago');
        // $schedule->command('get:recordings --nightly')
        //     ->onOneServer()->timezone('America/Los_Angeles')->dailyAt('3:00');

        // EZTPV
        // $schedule->command('eztpv:generateContracts')->everyFiveMinutes();
        // $schedule->command('email:EztpvContactErrors')->dailyAt('07:00');

        // QA
        // $schedule->command('qa:flagging')->hourly()->withoutOverlapping()->between(
        //     '7:00',
        //     '24:00'
        // )->timezone('America/Chicago');

        // Surveys
        // $schedule->command('survey:finalize')
        //     ->everyMinute()
        //     ->withoutOverlapping()->between(
        //         '7:00',
        //         '24:00'
        //     )->timezone('America/Chicago');

        // Client/Brand Specific
        // - Green Mountain
        $schedule->command('gm:survey:noreport')
            ->onOneServer()->timezone('America/Los_Angeles')->dailyAt('3:00');
        $schedule->command('gm:survey:report')
            ->onOneServer()->timezone('America/Los_Angeles')->dailyAt('3:00');

        // $schedule->command('entel:nightly_report --nightly')
        //     ->onOneServer()->timezone('America/Los_Angeles')->dailyAt('3:00');
        // $schedule->command('gm:survey:alert')
        //     ->timezone('America/Los_Angeles')->everyFiveMinutes();

        //Generate DXCCalls
        // $schedule->command('dcx:generateCalls')
        //     ->timezone('America/Los_Angeles')->everyFiveMinutes();

        //Generate DXC calls records from LEgacy
        // $schedule->command('generate:dxc_legacy')->onOneServer()
        //     ->timezone('America/Los_Angeles')->everyThirtyMinutes()->between(
        //         '7:00',
        //         '24:00'
        //     );

        //Calculate the distance between sales agent and client
        $schedule->command('calculate:distance:sa:client --nightly')->onOneServer()
            ->timezone('America/Los_Angeles')->dailyAt('3:00');

        // $schedule->command('products:remove-expired')->onOneServer()->timezone('America/Los_Angeles')->dailyAt('01:00');

        // - Dynegy
        // $schedule->command('dynegy:rates')->dailyAt('3:00');

        // Disabled until we can test this in production.
        // $schedule->command('deactivate:tpv-agents')
        //  ->timezone('America/Los_Angeles')->dailyAt('3:00');

        // $schedule->command('deactivate:sales-agents')
        //     ->onOneServer()
        //     ->timezone('America/Los_Angeles')->dailyAt('3:00');

        // $schedule->command(
        //     'outbound:call:queue'
        // )->everyMinute()->withoutOverlapping()->timezone(
        //     'America/Chicago'
        // )->between(
        //     '8:00',
        //     '19:45'
        // );

        // $schedule->command(
        //     'brand:file:sync'
        // )->hourly()->onOneServer()->withoutOverlapping()->timezone(
        //     'America/Los_Angeles'
        // )->between(
        //     '1:00',
        //     '7:00'
        // );

        /*
        // Live Agent Dashboard
        // Since you can't schedule a job to run less than every minute
        $schedule->call(function () {
            $dt = Carbon\Carbon::now();
            $x = 60 / 5;

            do {
                $exitCode = Artisan::call('liveagent:dashboard', []);
                time_sleep_until($dt->addSeconds(5)->timestamp);
            } while ($x-- > 0);
        })->everyMinute();
        */
        /*
        // NotReady Agent Dashboard
        // Since you can't schedule a job to run less than every minute
        $schedule->call(function () {
            $dt = Carbon\Carbon::now();
            $x = 12; // 60 / 5

            do {
                $exitCode = Artisan::call('notreadyagent:dashboard', []);
                time_sleep_until($dt->addSeconds(5)->timestamp);
            } while ($x-- > 0);
        })->everyMinute();
        */
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        include base_path('routes/console.php');
    }
}
