<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\ScriptAnswer;
use App\Models\Brand;
use App\Models\State;
//use App\Models\Survey;

class NRGRetailSurveyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nrg:retailsurvey:report {--brand=} {--script=} {--emailto=} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'NRG Retail Survey Report';

    /**
     * Create a new command instance.
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
        $brand_id = $this->option('brand');
        $script_id = $this->option('script');
        $debug = $this->option('debug');
 
        if ($this->option('brand') == null) {
                $this->warn('brand must be set');
               return;
         }
 
         if ($this->option('script') == null) {
            $this->warn('script must be set');
            return;
         }

         $brand = Brand::where(
            'id',
            $brand_id
        )->first();

        if ($brand) {
            $this->info('Query for surveys');
            $results = StatsProduct::select(
                'stats_product.event_id',
                'stats_product.event_created_at',
                'stats_product.interaction_id',
                'stats_product.interaction_created_at',
                'stats_product.confirmation_code',
                'stats_product.btn',
                'stats_product.tpv_agent_name',
                'stats_product.tpv_agent_label',
                'surveys.refcode',
                'surveys.account_number',
                'surveys.customer_first_name',
                'surveys.customer_last_name',
                'states.state_abbrev',
                'stats_product.language',
                'stats_product.channel',
                DB::raw('DATE(surveys.customer_enroll_date) AS customer_enroll_date'),
                'surveys.referral_id',
                'surveys.srvc_address',
                'surveys.account_number',
                'surveys.agency',
                'surveys.enroll_source',
                'surveys.agent_vendor',
                'surveys.agent_name as survey_agent_name',
                'surveys.contr_acct_id',
                'surveys.rep_id',
                'surveys.custom_data',
                'stats_product.result',
                'stats_product.disposition_id',
                'stats_product.disposition_reason',
                'interactions.notes',
                'stats_product.interaction_time',
                'stats_product.service_city',
                'stats_product.service_zip',
                'stats_product.service_state',
                'stats_product.custom_fields'
            )->with(
                'interactions',
                'interactions.disposition'
            )->where(
                'stats_product_type_id',
                3
            )->leftJoin(
                'surveys',
                'stats_product.survey_id',
                'surveys.id'
            )->leftJoin(
                'states',
                'surveys.state_id',
                'states.id'
            )->leftJoin(
                'interactions',
                'stats_product.interaction_id',
                'interactions.id'
            )->where(
                'stats_product.brand_id',
                $brand->id
            )->where(
                'interaction_created_at',
                '>=',
                Carbon::now('America/Chicago')->subMonth()    //  get first day of current month
            )->where(
                'surveys.script_id',
                $script_id
            )->orderBy(
                'state_abbrev',
                'asc'
            )->get()->map(
                function ($item) {
                    $dt_attempt1 = null;
                    $dt_attempt2 = null;
                    $dt_attempt3 = null;

                    $attempt1 = null;
                    $attempt2 = null;
                    $attempt3 = null;

                    $attempt1_id = null;
                    $attempt2_id = null;
                    $attempt3_id = null;

                    for ($i = 0; $i < 3; ++$i) {
                        if (isset($item->interactions[$i])) {
                            if (null === $dt_attempt1) {
                                $attempt1_id = $item->interactions[$i]['id'];
                                $dt_attempt1 = $item->interactions[$i]['created_at']->toDateTimeString();
                                $attempt1 = (isset($item->interactions[$i]['disposition']))
                                    ? $item->interactions[$i]['disposition']['reason']
                                    : null;
                                continue;
                            }

                            if (null === $dt_attempt2) {
                                $attempt2_id = $item->interactions[$i]['id'];
                                $dt_attempt2 = $item->interactions[$i]['created_at']->toDateTimeString();
                                $attempt2 = (isset($item->interactions[$i]['disposition']))
                                    ? $item->interactions[$i]['disposition']['reason']
                                    : null;
                                continue;
                            }

                            if (null === $dt_attempt3) {
                                $attempt3_id = $item->interactions[$i]['id'];
                                $dt_attempt3 = $item->interactions[$i]['created_at']->toDateTimeString();
                                $attempt3 = (isset($item->interactions[$i]['disposition']))
                                    ? $item->interactions[$i]['disposition']['reason']
                                    : null;
                                continue;
                            }
                        }
                    }

                    $item->dt_attempt1 = $dt_attempt1;
                    $item->attempt1 = $attempt1;
                    $item->attempt1_id = $attempt1_id;

                    $item->dt_attempt2 = $dt_attempt2;
                    $item->attempt2 = $attempt2;
                    $item->attempt2_id = $attempt2_id;

                    $item->dt_attempt3 = $dt_attempt3;
                    $item->attempt3 = $attempt3;
                    $item->attempt3_id = $attempt3_id;


                    return $item;
                }
            );

//            print_r($results->toArray());
 //          exit();

            // DXC code below
            // SELECT brand_name, btn, caller_id_number AS ANI, LANGUAGE, tsr_id AS Agent_id, ALLTRIM(tsr_fname) + ' ' + ALLTRIM(tsr_lname) AS Agent_name, ;
            // bill_fname AS Customer_First_Name, bill_lname AS Customer_Last_Name, ;
            // email_address AS Email, ver_code AS TranID,  TTOC(dt_date,3) AS DATE, status_txt AS STATUS, product_code, center_id AS CenterID, ;
            // Operator_ID, call_time AS Duration, acct_num AS Account_Number, service_address1, service_address2, ;
            // service_city, service_state, service_zip, status_id AS Reason_Code, '' AS Reason_Description, ;
            // utility, vendor_name, dt_attempt1, attempt1, dt_attempt2, attempt2, dt_attempt3, attempt3, agent_polite, ;
            // cb_understand_chose_supplier AS understand_chose_supplier, cb_signed_form_call AS signed_form_call, cb_likely_to_recommend AS likely_to_recommend, ;
            // cb_comments AS additional_comments, agree_gm_to_supply, agent_gave_toc, ;
            // btn_lookup_provider_name AS carrier, btn_lookup_provider_linetype AS phone_type, ;
            // enrollment_id, enrollment_type, retail_location_id, retail_location, email_validation, used_ecl,location_description, ;
            // ALLTRIM(STR(rec_id)) + 'NRGRetail' AS rec_id ;
            // FROM "tmp_data" INTO CURSOR "DataCursor" READWRITE

            $headers = [
                'brand_name',
                'btn',
                'ani',
                'language',
                'agent_id',
                'agent_name',
                'customer_first_name',
                'customer_last_name',
                'email',
                'tranid',
                'date',
                'status',
                'product_code',
                'centerid',
                'operator_id',
                'duration',
                'account_number',
                'service_address1',
                'service_address2',
                'service_city',
                'service_state',
                'service_zip',
                'reason_code',
                'reason_description',
                'utility',
                'vendor_name',
                'dt_attempt1',
                'attempt1',
                'dt_attempt2',
                'attempt2',
                'dt_attempt3',
                'attempt3',
                'agent_polite',
                'understand_chose_supplier',
                'signed_form_call',
                'likely_to_recommend',
                'additional_comments',
                'agree_gm_to_supply',
                'agent_gave_toc',
                'agent_rating',
                'carrier',
                'phone_type',
                'enrollment_id',
                'enrollment_type',
                'retail_location_id',
                'retail_location',
                'email_validation',
                'used_ecl',
                'location_description',
                'rec_id',
            ];
 
            $data_array = $results->toArray();
            $data_written = (count($data_array) > 0)
                ? true
                : false;
            if ($data_written) {
                $this->info('Found Surveys');
                try {
                   $state_sav = 'XX';
                   foreach ($data_array as $result) {
                        if ($state_sav !== $result['state_abbrev']) {
                            if ($state_sav == 'XX') {
                                $state_sav = $result['state_abbrev'];
                                $this->info('Create survey report for ' . $state_sav);
                                $filename = 'nrg_res_dtd_retail_' . $state_sav . '_accumulation_report_' . date("Y_m_d_",time()) . time() . '.csv';
                                $path = public_path('tmp/' . $filename);
                                $file = fopen($path, 'w');
                                fputcsv($file, $headers);
                            } else {
                                if ($state_sav !== 'XX') {
                                    fclose($file);
                                    $subject = 'NRG - Res - DTD - Retail - Accumulation - Nightly State Report for ' .  $state_sav . ' ' . date('m-d-Y H:i:s');
                                    $this->info('Email survey report for ' . $state_sav);
                                    $this->send_email($subject,$path,$state_sav,$filename);
                                    unlink($path);
                                    $state_sav = $result['state_abbrev'];
                                    $this->info('Create survey report for ' .$state_sav);
                                    $filename = 'nrg_res_dtd_retail_' . $state_sav . '_accumulation_report_' . date("Y_m_d_",time()) . time() . '.csv';
                                    $path = public_path('tmp/' . $filename);
                                    $file = fopen($path, 'w');
                                    fputcsv($file, $headers);
                                }
                            }
                        }
                        $customdata = json_decode($result['custom_data'], true);
                        $customfields = json_decode($result['custom_fields'], true);
                        $agent_polite = '';
                        $cb_understand_chose_supplier = '';
                        $cb_signed__form_call = '';
                        $agent_gave_toc = '';
                        $customer_rating = '';
                        $customer_input = '';
                        foreach ($customfields as $custom_answers) {
                            if ($custom_answers['output_name'] === 'agent_polite') {
                                $agent_polite = $custom_answers['value'];
                            }
                            if ($custom_answers['output_name'] === 'cb_understand_chose_supplier') {
                                $cb_understand_chose_supplier = $custom_answers['value'];
                            }
                            if ($custom_answers['output_name'] === 'cb_signed__form_call') {
                                $cb_signed__form_call = $custom_answers['value'];
                            }
                            if ($custom_answers['output_name'] === 'agent_gave_toc') {
                                $agent_gave_toc = $custom_answers['value'];
                            }
                            if ($custom_answers['output_name'] === 'customer_rating') {
                                $customer_rating = $custom_answers['value'];
                            }
                            if ($custom_answers['output_name'] === 'customer_input') {
                                $customer_input = $custom_answers['value'];
                            }
                        }
                        // if ($result['confirmation_code'] == '20602170557') {
                        //     print_r($curt33);
                        //     print_r($customer_rating);
                        //     print_r($custom_answers['value']);
                        //     return;
                        // }

                        $array['brand_name'] = $brand->name;      // brand_name
                        $array['btn'] = (isset($result['btn']))
                            ? $result['btn'] : null;   // btn
                        $array['ani'] = null;  // ani
                        $array['language'] = $result['language'];   // language
                        $array['agent_id'] = (isset($result['rep_id']))  // agent_id
                            ? $result['rep_id'] : null;
                        $array['agent_name'] = (isset($result['survey_agent_name'])) // agent_name
                            ? $result['survey_agent_name'] : null;
                        $array['customer_first_name'] = $result['customer_first_name'];  // customer_first_name
                        $array['customer_last_name'] = $result['customer_last_name'];  // customer_last_name
                                                    
                        $array['customer_email'] = $customdata['customer_email'];    // email
                        $array['tran_id'] = $result['confirmation_code']; // tranid
                        $array['date'] = $customdata['date_and_time_of_sale']; // date
                        $array['status'] = ('Sale' == $result['result'])   
                            ? 'Complete' : 'Unsuccessful';  // status
                        $array['product_code'] = null;  // product_code   
                        $array['center_id'] = null;  // centerid
                        $array['operator_id'] = null;  // operator_id  ID of DXC rep that performed the last QC attempt
                        $array['duration'] =  number_format($result['interaction_time'], 2);  // duration  // number_format($interaction->interaction_time, 2);
                        $array['account_number'] = $result['account_number'];   // account_number
                        $array['service_address1'] = $customdata['service_address'];  // service_address1
                        $array['service_address2'] = null;  // service_address2
                        $array['service_city'] = $customdata['service_city'];  // service_city
                        $array['service_state'] = $customdata['service_state'];  // service_state
                        $array['service_zip'] = $customdata['service_zip'];  // service_zip
                        $array['center_id'] = null;  // centerid
                        $array['reason_code'] = null;  // reason_code
                        $array['reason_description'] = null;  // reason_description
                        $array['utility'] = $customdata['utilityname'];  // utility
                        $array['vendor_name'] = $customdata['marketer'];  // vendor_name
                        $array['dt_attempt1'] = $result['dt_attempt1']; // dt_attempt1
                        $array['attempt1'] = $result['attempt1']; // attempt1
                        $array['dt_attempt2'] = $result['dt_attempt2']; // dt_attempt2
                        $array['attempt2'] = $result['attempt2'];  // attempt2
                        $array['dt_attempt3'] = $result['dt_attempt3']; // dt_attempt3
                        $array['dt_attemp3'] = $result['attempt3'];  // attempt3
                        $array['agent_polite'] = $agent_polite;   // agent_polite  (Yes or No)
                        $array['understand_chose_supplier'] = $cb_understand_chose_supplier;  // understand_chose_supplier  (Yes or No)
                        $array['signed_form_call'] = $cb_signed__form_call;  // signed_form_call  (Yes or No)
                        $array['likely_to_recommend'] = 'N/A';  // likely_to_recommend 
                        $array['additional_comments'] = $customer_input;  // additional_comments
                        $array['agree_gm_to_supply'] = 'N/A';  // agree_gm_to_supply 
                        $array['agent_gave_toc'] = $agent_gave_toc;  // agent_gave_toc  (Yes or No)
                        $array['agent_rating'] = $customer_rating;  // agent_rating  (1-10)
                        $array['carrier'] = null;  // carrier
                        $array['phone_type'] = null;  // phone_type
                        $array['enrollment_id'] = $customdata['enrollment_id'];  // enrollment_id
                        $array['enrollment_type'] = $customdata['enrollmenttype'];  // enrollment_type
                        $array['retail_location_id'] = $customdata['location_id'];  // retail_location_id
                        $array['retail_location'] = $customdata['location'];  // retail_location
                        $array['email_validation'] = $customdata['email_validation'];  // email_validation
                        $array['used_ecl'] = $customdata['used_ecl'];  // used_ecl
                        $array['location_description'] = $customdata['location_description'];  // location_description
                        $array['rec_id'] = $result['confirmation_code'];

                        if ($debug) {
                            print_r($array);
                            exit();
                        }

                        fputcsv($file, $array);
                    }
                    fclose($file);
                    $subject = 'NRG - Res - DTD - Retail - Accumulation - Nightly State Report for ' .  $state_sav . ' ' . date('m-d-Y H:i:s');
                    $this->info('Email survey report for ' . $state_sav);
                    $this->send_email($subject, $path,$state_sav,$filename);
                    unlink($path);
                    $this->info('Job Finished!!');

                } catch (\Exception $e) {
                    echo 'Error: ' . $e . "\n";
                }
            }
        }
    }
    private function send_email(string $subject,string $path,string $state_sav,string $filename)
    {
        if ('production' == config('app.env')) {
            switch ($state_sav)  {
                case 'DE':
                    $email_address = [
                        'DESalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'IL':
                    $email_address = [
                        'ILSalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'MA':
                    $email_address = [
                        'MASalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'MD':
                    $email_address = [
                        'MDSalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'NJ':
                    $email_address = [
                        'NJSalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'NY':
                    $email_address = [
                        'NYSalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'OH':
                    $email_address = [
                        'OHSalesQuality@greenmountain.com'
                    ];
                    break;     
                case 'PA':
                    $email_address = [
                        'PASalesQuality@greenmountain.com'
                    ];
                    break;     
                default:
                    $email_address = [
                    'dxc_autoemails@tpv.com'
                    ];
            }
            array_push($email_address,'curt@tpv.com','curt.cadwell@answernet.com');
        } else {
            $email_address = ['curt@tpv.com','curt.cadwell@answernet.com'];
        } 
        if ($this->option('emailto') !== null) {
            $email_address = [$this->option('emailto')];
         }

        $message = 'File ' . $filename . ' was successfully created.';
        $data = [
            'subject' => '',
            'content' => $message
        ];
        Mail::send(
            'emails.generic',
            $data,
            function ($message) use ($subject, $email_address, $path) {
                $message->subject($subject);
                $message->from('no-reply@tpvhub.com');
                $message->to($email_address);
                $message->attach($path);
            }
        );
        return;
    }

}
