<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use App\Models\GpsCoord;
use App\Models\DigitalSubmission;

class UpdateDigitalWithGpsCoordsFromInteraction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps:update-digital {--countonly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Looks for Digital Submissions that do not have a paired gps_coord entry and trys to fix that';

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
        $onlyCount = $this->option('countonly');
        $submissions = DB::select('SELECT ds.id, i.id as interaction_id, i.notes FROM  digital_submission ds LEFT JOIN gps_coords gc on gc.type_id = ds.id AND gc.ref_type_id = 4 RIGHT JOIN interactions i on i.event_id = ds.event_id AND i.interaction_type_id = 6 WHERE gc.id is null and ds.id is not null and i.id is not null and i.notes is not null');
        if ($onlyCount || count($submissions) == 0) {
            $this->info('Located ' . count($submissions) . ' entries to update');
            return 42;
        }
        $bar = $this->output->createProgressBar(count($submissions));

        $bar->start();

        $cnt = 0;

        foreach ($submissions as $submission) {
            $notes = $submission->notes;
            if ($notes) {
                $notes = json_decode($notes, true);
                if (isset($notes['user_location_data'])) {
                    $coords = $notes['user_location_data'][0];
                    if (isset($coords['coords'])) {
                        $coords = $coords['coords'];
                        $lat = $coords['latitude'];
                        $lon = $coords['longitude'];
                        $cnt += 1;
                        $c = new GpsCoord();
                        $c->created_at = now('America/Chicago');
                        $c->updated_at = now('America/Chicago');
                        $c->type_id = $submission->id;
                        $c->coords = $lat . ',' . $lon;
                        $c->ref_type_id = 4;
                        $c->gps_coord_type_id = 1;
                        $c->save();
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->line('');
        $this->info('Updated ' . $cnt . ' records.');
    }
}
