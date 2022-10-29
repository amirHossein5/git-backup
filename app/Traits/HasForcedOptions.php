<?php

namespace App\Traits;

use Illuminate\Support\Arr;

trait HasForcedOptions
{
    protected function hasAllOptions(...$options): bool
    {
        $options = Arr::flatten($options);

        foreach ($options as $option) {
            if (! $this->option($option)) {
                $this->error("Option --{$option} is required.");

                return false;
            }
        }

        return true;
    }
}
