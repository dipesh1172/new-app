<?php

namespace App\Console\Commands;

use League\Flysystem\Filesystem;
use League\Flysystem\Config;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\Survey;
use App\Models\Script;
use App\Models\State;
use App\Models\ProviderIntegration;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumber;
use App\Models\Brand;

/**
 * NRG and GM Retail import for retail surveys
 * 
 */
class ImportSurveysNrgRetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nrg:surveysimportretail {--brand=} {--script=} {--emailto=}';

    /**
     * NRG Retail import for retail surveys
     *
     * @var string
     */
    protected $description = 'Import Nrg Retail Surveys';

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
  
            try {
                $brand_id = $this->option('brand');
                $script_id = $this->option('script');
                $this->info('Running in '.config('app.env'));
                Log::info('Starting import...');
                Log::info($this->options());
    
                $pi = ProviderIntegration::where(
                    'brand_id',
                    $brand_id
                    )->where(
                        'provider_integration_type_id',
                        2
                    )->where(
                        'service_type_id',
                        31
                    )->first();
                    if (!$pi) {
                        echo 'Unable to get provider integration credentials.';
                        return;
                    }
     
                if ('production' != env('APP_ENV')) {
                    $config = [
                        'host' => $pi->hostname,
                        'username' => $pi->username,
                        'password' => $pi->password,
                        'port' => 21,
                        'root' => '/staging',
                        'ssl' => false,
                        'passive' => true,
                        'timeout' => 30,
                    ];
 
                } else {
                    $config = [
                        'host' => $pi->hostname,
                        'username' => $pi->username,
                        'password' => $pi->password,
                        'port' => 21,
                        'root' => '/',
                        'ssl' => false,
                        'passive' => true,
                        'timeout' => 30,
                    ];
                }
    
                $adapter = new Ftp($config);
                $filesystem = new Filesystem($adapter);
                $files = $filesystem->listContents('/');
                $this->info('Processing '.count($files).' filesystem entries');
                foreach ($files as $file) {
                    if ($file['type'] === 'file' and $file['extension'] === 'txt' and substr_count(strtolower($file['filename']),'nrgretailfiledrop_') == 1)  {
                        $contents = $filesystem->read($file['path']);
                        $totalrecs = $this->process_file($brand_id, $contents,$script_id);
                       $filetimestamp = time();
                       $filesystem->copy($file['path'], 'processed/' . $file['filename'] . '_' . $filetimestamp . '.txt' );
                       if ($filesystem->has( 'processed/' . $file['filename'] . '_' . $filetimestamp . '.txt')) {
                        $filesystem->delete($file['path']);
                       }
                         $subject = 'NRG - Retail QC Callbacks - Data Import '. Carbon::now('America/Chicago')->format("Ymd-His");
                       if ('production' !== config('app.env')) {
                           $email_address = [
                               'curt@tpv.com',
                               'curt.cadwell@answernet.com'
                           ];
                       } else {
                           $email_address = [
                             'eduardo@tpv.com',
                             'report@tpv.com',
                             'dxc_autoemails@tpv.com',
                             'Lauren.Feldman@nrg.com',
                             'John.Rombach@nrg.com',
                             'justin.rostant@nrg.com',
                             'casey.norling@nrg.com',
                             'curt@tpv.com'
                            ];
                       } 
                       if ($this->option('emailto') !== null) {
                        $email_address = [$this->option('emailto')];
                       }
                       $message = 'NRG Retail Sales Data File Processed: ' . $file['path'] . ' ' . $totalrecs . ' Records were imported.';
                       $data = [
                           'subject' => '',
                           'content' => $message
                       ];
               
                       Mail::send(
                           'emails.generic',
                           $data,
                           function ($message) use ($subject, $email_address) {
                               $message->subject($subject);
                               $message->from('no-reply@tpvhub.com');
                               $message->to($email_address);
                           }
                       );
   
                   }
                }
 
            } catch (\Exception $e) {
               Log::info('Exception running survey import: ', [$e]);
               $errorList[] = ['lineNumber' => null, 'Error during survey import: ' . $e->getMessage()];
               print_r($errorList);
               return;
 
            }

     }

    private function process_file($brand_id, $contents, $script_id)
    {
        $lines = explode(PHP_EOL, $contents);
        $csv = [];
        foreach ($lines as $line) {
            if (strlen(trim($line)) > 0) {
                $csv[] = str_getcsv($line,'|');
            }
        }

        $header = null;
        $this->info('Processing '.(count($csv) - 1).' records.');
        foreach ($csv as $row) {
            if ($header === null) {
                $header = $row;
                $header = array_map( 'strtolower',str_ireplace(" ","_",$header));
                continue;
            }

            $data = array_combine($header, $row);
            $data['date_and_time_of_sale'] =  $data['date_of_sale'];
            $data['date_of_sale'] = substr($data['date_of_sale'],0,strpos($data['date_of_sale'],' '));
            $data['location_description'] = (empty($data['location_description']) ? 'the location where you enrolled' : $data['location_description']);
            $this->import_survey($brand_id, $data, $script_id);
        }
        return count($csv) - 1;  // remove header from count
    }

    private function import_survey($brand_id, $survey, $script_id)
    {
        $script = Script::where(
            'brand_id',
            $brand_id
            )->where(
             'id',
             $script_id   
            )->first();

        if ($script) {

            $custom_data = $survey;
         
            $state_lookup = State::where(
                'state_abbrev',
                strtoupper($survey['service_state'])
            )->first();
            if (!$state_lookup) {
                $this->error('State: ' . $state . '  was not found.');
                Log::error('State: ' . $state . ' was not found.');
                return; //skip if not found
                //                return 31;
            }

            $phone = null;
            if (isset($survey['billing_phone']) && strlen(trim($survey['billing_phone'])) > 0) {
                $phone = CleanPhoneNumber($survey['billing_phone']);
            }

            if ($phone === null || strlen($phone) !== 12) {
                if ($phone !== null) {
                    $this->error('Invalid phone number: '.$survey['billing_phone']);
                }

                return;
            }
            $pnl = PhoneNumberLookup::where(
                'phone_number_type_id',
                6
            )->join(
                'phone_numbers',
                'phone_numbers.id',
                'phone_number_lookup.phone_number_id'
            )->where(
                'phone_numbers.phone_number',
                $phone
            )->get();

            // allow duplicate btns for survey  

            // if (!$pnl->isEmpty()) {
            //     $this->info('Phone Number Lookup record exists: '.$phone);

            //     return;
            // }

            $s = new Survey();
            $s->brand_id = $brand_id;
            $s->script_id = $script->id;
            $s->created_at = Carbon::yesterday('America/Chicago');
            $s->refcode = $survey['enrollment_id'];
            $s->customer_enroll_date = Carbon::parse(substr($survey['date_of_sale'],0,strpos($survey['date_of_sale'],' ')) . ' 00:00:00','America/Chicago'); 
            $s->customer_first_name = $survey['customer_first_name'];
            $s->customer_last_name = $survey['customer_last_name'];
            $s->agency = $survey['marketer'];
            $s->account_number = $survey['utility_account_number'];
            $s->srvc_address = $survey['service_address'] . ', ' . $survey['service_city'] . ', ' . $survey['service_state'] . ', ' . $survey['service_zip'];
            $s->agent_name = $survey['rep_first_name'] . ' ' . $survey['rep_last_name'];
            $s->state_id = $state_lookup->id;
            $s->language_id = 1;
            $s->rep_id = $survey['rep_id'];
            $s->agency = $survey['location_description'];
            $s->referral_id = $survey['enrollment_id'];
            $s->custom_data = json_encode($custom_data);
            $s->save();

            // allow duplicate btns for survey  
            if ($s) {
                if (isset($phone)) {
                    $exists = PhoneNumber::where(
                        'phone_number',
                        $phone
                    )->withTrashed()->first();
                    if (!$exists) {
                        $pn = new PhoneNumber();
                        $pn->phone_number = $phone;
                        $pn->save();
                        $pnid = $pn->id;
                    } else {
                        $pnid = $exists->id;
                        if ($exists->trashed()) {
                            $exists->restore();
                        }
                    }

                    $pnl = new PhoneNumberLookup();
                    $pnl->phone_number_type_id = 6;
                    $pnl->type_id = $s->id;
                    $pnl->phone_number_id = $pnid;
                    $pnl->save();
                }
            }
        }
    }
}
