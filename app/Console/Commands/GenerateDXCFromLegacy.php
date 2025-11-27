<?php

namespace App\Console\Commands;

use App\Models\DXCLegacy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GenerateDXCFromLegacy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:dxc_legacy {--start_date=} {--end_date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DXC Calls from Legacy';

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
        $client = new Client();
        $start_date = ($this->option('start_date'))
            ? $this->option('start_date')
            : Carbon::now('America/Chicago')->subMinutes(50)->format('Y-m-d H:i:s');
        $end_date = ($this->option('end_date'))
            ? $this->option('end_date')
            : Carbon::now('America/Chicago')->subMinutes(20)->format('Y-m-d H:i:s');
        $username = 'dxcProcessWavLookup';
        $password = 'bQSb^u%lA!^aAe01xFJ^';
        //$response = $client->get('https://ws.dxc-inc.com/dxc2/dxcProcessWavesLookup.php?startTime=2019-09-10 15:00:00&endTime=2019-09-11 15:30:00',
        $response = $client->get(
            'https://ws.dxc-inc.com/dxc2/dxcProcessWavesLookup.php',
            [
            'auth' => [
                $username,
                $password
            ],
            'query' => [
                'startTime' => $start_date,
                'endTime' => $end_date
             ]

        ]
        );

        Log::info('--------------------------Starting DXCLegacy Command--------------------------');
        Log::info($response->getStatusCode());

        if($response->getStatusCode() == 200){
            $body = (string) $response->getBody();
            $calls = json_decode($body);
            Log::info(var_dump($calls));
            if($calls->result == 'Success'){
                foreach ($calls->data as $call) {
                    //$dxc_legacy = app()->make('stdClass');
                    //Avoiding storing the same info twice when $call->insert_at == $end_date
                    $insert_at = Carbon::parse(
                        $call->dt_insert,
                        'America/Chicago'
                    )->format('Y-m-d H:i:s');

                    if($insert_at == $end_date){
                        $search = DXCLegacy::where('cic_call_id_keys', $call->cic_call_id_keys)->first();
                        if($search){ continue; }
                    }
                    
                    $dxc_legacy = new DXCLegacy();
                    $dxc_legacy->tpv_type = $call->tpv_type;
                    $brand_lang = explode('-',$call->form_name);
                    $dxc_legacy->brand = trim( $brand_lang[0] );
                    $dxc_legacy->language = trim( end( $brand_lang ) );
                    $dxc_legacy->cic_call_id_keys = $call->cic_call_id_keys;
                    $dxc_legacy->call_time = $call->call_time;
                    $dxc_legacy->confirmation_code = $call->ver_code;
                    $dxc_legacy->insert_at = $insert_at;
                    $dxc_legacy->call_segments = $call->call_segments;
                    $dxc_legacy->save();
                    //Log::info(print_r($dxc_legacy));  
                }
            }
        }else{
            Log::error('There has been an error trying to connect to DXC with params start_date = '.$start_date.' and end_date ='.$end_date);
            Log::info('--------------------------End DXCLegacy Command--------------------------');
        }
    }
}
