<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JsonDocument;

class SyncLogSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:sync-search {--json} {--brand=} {confirmation_code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private $found = null;
    protected $jsonOutput = false;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->jsonOutput = $this->option('json');

        $ccode = $this->argument('confirmation_code');

        if (empty($ccode)) {
            if (!$this->jsonOutput) {
                $this->error('No confirmation code given');
            } else {
                $this->line(json_encode(['error' => 'No confirmation code given']));
            }
            return 42;
        }

        if (!$this->jsonOutput) {
            $this->info('Searching for ' . $ccode);
        }

        $brandId = null;
        if (!empty($this->option('brand'))) {
            $brandId = $this->option('brand');
        }

        $docs = JsonDocument::where('document_type', 'brand_file_sync');
        if (!empty($brandId)) {
            $docs = $docs->where('ref_id', $brandId);
        }
        $docs = $docs->orderBy('created_at', 'desc')->get();

        $cnt = $docs->count();

        if ($cnt === 0) {
            if (!$this->jsonOutput) {
                $this->warn('No Sync Logs found');
            } else {
                $this->line(json_encode(['error' => 'No sync logs found']));
            }
            return 43;
        }
        $bar = null;

        if (!$this->jsonOutput) {
            $bar = $this->output->createProgressBar($cnt);

            $bar->start();
        }

        $docs->each(function ($item) use ($ccode, $bar) {
            $document = $item->document;
            if (!is_array($document)) {
                $json = json_decode($document, true);
            } else {
                $json = $document;
            }
            foreach ($json as $conf => $msgs) {
                if ($conf == $ccode) {
                    if (!$this->jsonOutput) {
                        $this->found = $msgs;
                    } else {
                        $this->found = [
                            'sync_date' => $item->created_at->setTimezone('America/Chicago')->toIso8601String(),
                            'messages' => $msgs,
                        ];
                    }
                    return false;
                }
            }
            if (!$this->jsonOutput) {
                $bar->advance();
            }
        });
        if (!$this->jsonOutput) {
            $bar->finish();
            $this->line('');
        }

        if (empty($this->found)) {
            if (!$this->jsonOutput) {
                $this->error('No Sync Log found for ' . $ccode);
            } else {
                $this->line(json_encode(['error' => 'No sync logs found for ' . $ccode]));
            }
            return 44;
        }

        if (!$this->jsonOutput) {
            foreach ($this->found as $arr) {
                $this->info(json_encode($arr));
            }
        } else {
            $this->line(json_encode($this->found));
        }
    }
}
