<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use App\Models\ZipCode;

class ZipScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zip:scan {--file=} {--add-missing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Given a file with one zip per line check for zip existence in database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $fileName = $this->option('file');
        if (empty($fileName)) {
            $this->warn('No filename given');
            return;
        }
        $addMissing = $this->option('add-missing');

        $fileContents = file_get_contents($fileName);
        $zips = explode("\n", $fileContents);
        $zips = array_map(function ($item) {
            return trim($item);
        }, $zips);

        $out = [];

        foreach ($zips as $zip) {
            $out[$zip] = ZipCode::where('zip', $zip)->first();
            if (empty($out[$zip])) {
                $this->info('Zip: ' . $zip . ' not found.');
                if ($addMissing) {
                    $this->info('Attempting to add ' . $zip);
                    Artisan::call('zip:lookup', [
                        '--zip' => $zip
                    ]);
                }
            }
        }
    }
}
