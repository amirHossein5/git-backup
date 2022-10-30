<?php

use Symfony\Component\Console\Command\Command;

it('shows available disks', function () {
    $artisan = $this->artisan('show:disk');

    foreach (config('filesystems.disks') as $disk) {
        $artisan->expectsOutput($disk['driver']);
    }

    $artisan->assertExitCode(Command::SUCCESS);
});
