<?php

namespace App\Commands\Repo;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class AddCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'repo:add {--to-dir=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Adds mirrored local git repo to the passed directory.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! $this->option('to-dir')) {
            $this->error("specify --to-dir=/paht/to/put/repo");
            return Command;
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
