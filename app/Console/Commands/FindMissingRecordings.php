<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Interaction;
use Illuminate\Console\Command;

class FindMissingRecordings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'find:missing:recordings {--limit=25}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find interactions with missing recordings';

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
        $count = 1;
        $results = [];
        $headers = ['#', 'Date', 'Confirmation Code', 'Brand Name'];

        $interactions = Interaction::select(
            'interactions.id',
            'interactions.created_at',
            'interactions.event_id'
        )->leftJoin(
            'recordings',
            'interactions.id',
            'recordings.interaction_id'
        )->whereNull(
            'recordings.id'
        )->orderBy(
            'interactions.created_at',
            'desc'
        )->limit($this->option('limit'))->get();
        if ($interactions) {
            foreach ($interactions as $interaction) {
                $event = Event::select(
                    'events.id',
                    'events.confirmation_code',
                    'brands.name AS brand_name'
                )->leftJoin(
                    'brands',
                    'events.brand_id',
                    'brands.id'
                )->where(
                    'events.id',
                    $interaction->event_id
                )->first();
                if ($event) {
                    $results[] = [
                        $count++,
                        $interaction->created_at,
                        $event->confirmation_code,
                        $event->brand_name
                    ];
                }
            }

            $this->table($headers, $results);
        } else {
            $this->info('No interactions were found.');
        }
    }
}
