<?php

namespace App\Commands\Show;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Command\Command as Output;

class DiskCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'show:disk';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Shows available disks.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        collect(config('filesystems.disks'))
            ->each(fn ($data, $name) => $this->warn($name));

        return Output::SUCCESS;
    }
}
