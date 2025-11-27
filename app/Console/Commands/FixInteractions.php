<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Models\Interaction;
use App\Models\Audit;

class FixInteractions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:interactions {--show-sql} {--dry-run} {--brand=} {--date=} {--start-date=} {--end-date=} {--interaction-id=} {--confirmation-code} {--exclude-brands=}';

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $interactions = $this->getInteractions();

        $log = [];

        $ctr = 0;
        $totalInteractions = count($interactions);

        if($totalInteractions == 0) {
            $this->info('');
            $this->info('No interactions found. Exiting');

            exit -1;
        }

        foreach($interactions as $r) {
            $ctr++;
            $this->info('--------------------------------------------------');
            $this->info('[ ' . $ctr . ' / ' . $totalInteractions . ' ]');
            $this->info('');

            $audits = $this->getAudits($r['interaction_id']);

            // Find last result
            // These should be in descending order
            $audit_id = null;
            $result_id = null;

            $auditsFound = 'No';

            if($audits) {
                $this->info('');
                $this->info('Audits found');
                $this->info('Records: ' . count($audits));
                $this->info('');

                $auditsFound = 'Yes';
                $lastUpdate = null;

                $auditCtr = 0;
                $totalAudits = count($audits);

                foreach($audits as $audit) {

                    $auditCtr++;

                    if($auditCtr == 1) {
                        $lastUpdate = $audit->updated_at;
                    }

                    $auditCtrStr = '  [ ' . $auditCtr . ' / ' . $totalAudits . ' ]';

                    if($audit->event == 'created') { // If first audit is 'created', then it should also be the only audit record.
                        $this->info($auditCtrStr . " --  Record 'Created' audit entry");

                        $audit_id = $audit->id;
                        $newValues = is_string($audit->new_values)
                            ? json_decode($audit->new_values)
                            : $audit->new_values;
                        
                        if(array_key_exists('event_result_id', $newValues)) {
                            $this->info("      'event_result_id' setting found");
                            $result_id = $newValues['event_result_id'];
                        } else {
                            $this->info("      'event_result_id' setting NOT found. Result will be set to NULL");
                        }

                        break;
                    } else {
                        $this->info($auditCtrStr . " -- Record '" . $audit->event . "' audit entry");

                        $audit_id = $audit->id;
                        $newValues = is_string($audit->new_values)
                            ? json_decode($audit->new_values)
                            : $audit->new_values;
                        
                        if(array_key_exists('event_result_id', $newValues)) {
                            $this->info("      'event_result_id' setting found");
                            $result_id = $newValues['event_result_id'];

                            break;
                        } else {
                            $this->info("      'event_result_id' setting NOT found.");
                        }
                    }
                }
            } else {
                $this->info('');
                $this->info('Audits NOT found');
            }            

            $lr = [
                'confirmation_code'       => $r['confirmation_code'],
                'event_id'                => $r['event_id'],
                'interaction_id'          => $r['interaction_id'],
                'interaction_last_update' => $r['interaction_updated_at'],
                'event_result_id'         => $r['event_result_id'],
                'audits_found'            => $auditsFound,
                'audits_last_update'      => $lastUpdate,
                'audit_id'                => $audit_id,
                'audit_result_id'         => $result_id,
                'notes'                   => ''
            ];            

            $this->info('');

            // Update the record
            if(!$this->option('dry-run')) {
                if($audits) {                    
                    $this->info('Updating interaction...');

                    $insertQuery = '
                        UPDATE interactions
                        SET event_result_id = :result_id, updated_at = :date_updated
                        WHERE id = :interaction_id
                    ';

                    $bindings = [
                        'result_id' => $result_id,
                        'date_updated' => $lastUpdate,
                        'interaction_id' => $r['interaction_id']
                    ];

                    try {
                        DB::statement($insertQuery, $bindings);

                        $lr['notes'] = 'Interaction record updated successfully';
                        $this->info('  Done.');

                    } catch (\Exception $e) {
                        $lr['notes'] = $e->getMessage();
                    }
                }
            } else {
                $this->info('');
                $this->info('dry-run option set. Interaction record was not updated.');
                $lr['notes'] = 'dry-run option set. Interaction record was not updated.';
            }

            $log[] = $lr;
        }

        // print_r($log);

        $fileTimestamp = Carbon::now("America/Chicago")->format('Ymd-hIs');
        $file = fopen('interaction_audits-' . $fileTimestamp . '.csv', 'w');

        fputcsv($file, array_keys($log[0]));

        foreach($log as $l) {
            fputcsv($file, $l);
        }

        fclose($file);
    }

    /**
     * Get audits for interaction
     */
    public function getAudits($interaction) {

        $this->info('Getting audits for interaction: ' . $interaction);

        $data = Audit::where('auditable_id', $interaction)
            ->orderBy('created_at', 'desc')
            ->get();

        return $data;
    }

    /**
     * Get interaction list
     */
    public function getInteractions() {

        $this->info('Getting interactions...');

        if(
                !$this->option('brand') 
                && !$this->option('date') 
                && !$this->option('start-date') 
                && !$this->option('end-date') 
                && !$this->option('interaction-id')
                && !$this->option('confirmation-code')
        ) {
            $this->error("You must provide at least one of the following arguments:");
            $this->error("    --brand=");
            $this->error("    --interaction-id=");
            $this->error("    --confirmation-code=");
            $this->error("    --date=");
            $this->error("    --start-date= AND --end-date=");

            exit -1;
        }

        $data = Interaction::select(
            'events.brand_id',
            'events.confirmation_code',
            'events.id AS event_id',
            'interactions.id AS interaction_id',
            'interactions.updated_at AS interaction_updated_at',
            'interactions.event_result_id'
        )
        ->join('events', 'interactions.event_id', 'events.id');

        // Filter on brand?
        if($this->option('brand')) {
            $data = $data->where('events.brand_id', $this->option('brand'));
        }

        // Exclude brands?
        if($this->option('exclude-brands')) {
            $brands = explode('|', $this->option('exclude-brands'));
            $data = $data->whereNotIn('events.brand_id', $brands);
        }

        // Filter on interaction id?
        if($this->option('interaction-id')) {
            $data = $data->where('interactions.id', $this->option('interaction-id'));
        }

        // Filter on confirmation code?
        if($this->option('confirmation-code')) {
            $data = $data->where('events.confirmation_code', $this->option('confirmation-code'));
        }

        // Filter on date?
        if($this->option('date')) {

            $date = null;
            try {
                $date = Carbon::parse($this->option('date'));
            } catch(\Exception $e) {
                $this->error($e->getMessage());
                exit -1;
            }

            $data = $data->whereDate('interactions.created_at', $date);

            // Filter on date range?
        } else if($this->option('start-date') && $this->option('end-date')) {
        
            $start = null;
            $end = null;
            try {
                $start = Carbon::parse($this->option('start-date'));
                $end = Carbon::parse($this->option('end-date'));
            } catch(\Exception $e) {
                $this->error($e->getMessage());
                exit -1;
            }

            if($end->lt($start)) {
                $tmp = $start;
                $start = $end;
                $end = $tmp;
            }

            $data = $data
                ->whereDate('interactions.created_at', '>=', $start)
                ->whereDate('interactions.created_at', '<=', $end);
        }

        $data = $data
            ->orderBy('events.confirmation_code', 'asc')
            ->orderBy('interactions.created_at', 'asc');

        // Display SQL query.        
        if ($this->option('show-sql')) {
            $queryStr = str_replace(array('?'), array('\'%s\''), $data->toSql());
            $queryStr = vsprintf($queryStr, $data->getBindings());

            $this->info("");
            $this->info('QUERY:');
            $this->info($queryStr);
            $this->info("");
        }

        $data = $data
            ->get()
            ->toArray();
        

        $this->info("Records: " . count($data));

        return $data;
    }
}
