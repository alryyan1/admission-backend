<?php

namespace App\Console\Commands;

use App\Models\Bed;
use Illuminate\Console\Command;

class ReleaseAllBeds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beds:release-all
        {--force : Skip the confirmation prompt}
        {--include-occupied : Also mark beds with a currently admitted patient as available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark beds as available, for resetting the facility map in a dev/demo environment';

    public function handle(): int
    {
        $withActiveAdmission = Bed::query()->whereHas('currentAdmission')->count();

        if ($withActiveAdmission > 0 && ! $this->option('include-occupied')) {
            $this->warn(
                "{$withActiveAdmission} bed(s) have an active admission and will be left as-is. ".
                'Pass --include-occupied to release those too (this does NOT discharge the admission, '.
                'so its bed_id will keep pointing at a bed now marked available).'
            );
        }

        if (! $this->option('force') && ! $this->confirm('Mark beds as available now?')) {
            $this->info('Aborted. Nothing was changed.');

            return self::SUCCESS;
        }

        $query = Bed::query();

        if (! $this->option('include-occupied')) {
            $query->whereDoesntHave('currentAdmission');
        }

        $count = $query->update(['status' => 'available']);

        $this->info("{$count} bed(s) marked available.");

        return self::SUCCESS;
    }
}
