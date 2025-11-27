<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\ScriptAnswer;
use App\Models\Brand;
use App\Models\State;
use App\Models\Survey;
use App\Models\ProviderIntegration;

class DirectEnergyPsaSurveyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy:PSA:Survey:Report {--mode=} {--noftp} {--noemail} {--brand=} {--script=} {--debug} {--start-date=} {--end-date=}';
    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Direct Energy - PSA Call Backs  ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Direct Energy PSA Survey Report';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com', '_TPVDeployment@directenergy.com','_TPVTeam@directenergy.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
 //          'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
//          'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['dxc_autoemails@tpv.com'],
            'test' => ['dxcit@tpv.com']
 //          'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ]
    ];


    /**
     * FTP Settings
     *
     * @var array
     */

    protected $ftpSettings = [
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 22,
        'root' => '/outbound/',
//        'passive' => true,
//        'ssl' => true,
        'timeout' => 30,
        'directoryPerm' => 0755,
    ];
   
    /**
     * Report start date
     *
     * @var mixed
     */
    protected $startDate = null;

    /**
     * Report end date
     *
     * @var mixed
     */
    protected $endDate = null;

    /**
     * Report mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->startDate = Carbon::today('America/Chicago');
        $this->endDate = Carbon::tomorrow('America/Chicago')->add(-1, 'second');
 
        if ($this->option('brand') == null) {
                $this->warn('brand must be set');
               return;
         }
 
         if ($this->option('script') == null) {
            $this->warn('script must be set');
            return;
         }
         $brand_id = $this->option('brand');
         $script_id = $this->option('script');
         $debug = $this->option('debug');
     
        // Check mode. Leave in 'live' mode if not provided or an invalid value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            }
        }
        // Check for and validate custom report dates, but only if both start and end dates are provided
        if ($this->option('start-date') && $this->option('end-date')) {
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
            $this->info('Using custom dates...');
        }
        // Get FTP details
        $pi = ProviderIntegration::where(
            'brand_id',
            $brand_id
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            33
        )->first();

        if (empty($pi)) {
            $this->error("No credentials were found.");
            return -1;
        }

        $this->ftpSettings['host'] = $pi->hostname;
        $this->ftpSettings['username'] = $pi->username;
        $this->ftpSettings['password'] = $pi->password;
      
        $adapter = new SftpAdapter(
            [
                'host' =>  $this->ftpSettings['host'],
                'port' => $this->ftpSettings['port'],
                'username' => $this->ftpSettings['username'],
                'password' => $this->ftpSettings['password'],
                'root' => $this->ftpSettings['root'],
                'timeout' => $this->ftpSettings['timeout'],
                'directoryPerm' => $this->ftpSettings['directoryPerm'],
            ]
        );
        $filesystem = new Filesystem($adapter);


         $brand = Brand::where(
            'id',
            $brand_id
        )->first();
 //           )->where(
 //               'stats_product_type_id',
 //               3
 //        $test = survey::where('script_id',$script_id)->first();

        if ($brand) {
            $this->info('Query for surveys');
            DB::enableQueryLog();

            $results = StatsProduct::select(
                'surveys.id',
                'surveys.brand_id',
                'surveys.script_id',
                'surveys.event_id AS surveys_event_id',
                'stats_product.event_id',
                'stats_product.event_created_at',
                'stats_product.interaction_id',
                'stats_product.interaction_created_at',
                'stats_product.event_created_at',
                'stats_product.confirmation_code',
                'stats_product.btn',
                'stats_product.tpv_agent_name',
                'stats_product.tpv_agent_label',
                'surveys.refcode',
                'surveys.account_number',
                'surveys.customer_first_name',
                'surveys.customer_last_name',
                'stats_product.language',
                'stats_product.channel',
//                DB::raw('DATE(surveys.customer_enroll_date) AS customer_enroll_date'),
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
                'stats_product.service_address1',
                'stats_product.service_city',
                'stats_product.service_zip',
                'stats_product.service_state',
                'stats_product.custom_fields',
                'dispositions.brand_label',
                'dispositions.reason',
                'event_results.result'
            )->leftJoin(
                'surveys',
                'surveys.event_id',
                'stats_product.event_id'
            )->leftJoin(
                'interactions',
                'stats_product.interaction_id',
                'interactions.id'
            )->leftJoin(
                'dispositions',
                'interactions.disposition_id',
                'dispositions.id'
            )->leftJoin(
                'event_results',
                'interactions.event_result_id',
                'event_results.id'
            )->where(
                'stats_product.brand_id',
                $brand_id
            )->where(
                'stats_product.interaction_type',
                'survey'
            )->where(
                'surveys.brand_id',
                $brand_id
            )->where(
                'surveys.script_id',
                $script_id
            // )->where(
            //      'stats_product.confirmation_code',
            //      '20791087345'  //                '20810880504'
            )->whereDate(
                'stats_product.interaction_created_at',
                '>=',
                $this->startDate
            )->whereDate(
                'stats_product.interaction_created_at',
                '<=',
                $this->endDate
            )->orderBy(
                'interaction_created_at',
                'desc'
            )->get();
            $this->info('End Query for surveys');
            //         print_r($results->toArray());
            //         print_r(DB::getQueryLog());
            // direct_energy_psa_callbacks_salesforce_ftp_file_generation.prg   
            // DXC code below 
            //    cQuery = "SELECT CONVERT(VARCHAR,date_of_sale,101) as date_of_sale,CONVERT(VARCHAR,date_of_psa_call,101) as date_of_psa_call," + ;
            //     "recid_unique,cb_SpeakWithYesNo,cb_happiness_score,cb_happiness_score_comments,cb_happiness_score_type,cb_tsr_experience_text,cb_tsr_experience_text_type," + ;
            //     "cb_completed_tpv,cb_tpv_experience_score,cb_tpv_experience_type,cb_tpv_experience_text,cb_additional_comments_type," + ;
            //     "cb_additional_comments_text,cb_customer_already_cancelled_alert,cb_cancellation_reason,cb_other_cancellation_reason_text," + ;
            //     "cb_other_cancellation_reason_type,cb_psa_disposition," + ;
            //     "b.cb_time,psa_agent_id,psa_vendor,targeted_psa,SPACE(200) as cb_psa_long_disposition,a.rec_id," + ;
            //     "(convert(varchar,convert(int,b.cb_time)))+ '.' + IIF(LEN(convert(varchar,convert(int,60*(b.cb_time % 1)))) = 1," + ;
            //     "'0'+(convert(varchar,convert(int,60*(b.cb_time % 1))))," + ;
            //     "(convert(varchar,convert(int,60*(b.cb_time % 1))))) as ser_call_duration " + ;
            //     "FROM direct_energy_psa_call_back_answers a " + ;
            //     "INNER JOIN Direct_Energy_Res_DTD b on a.db_rec_id = b.rec_id " + ;
            //     "WHERE CONVERT(DATE,a.date_of_psa_call) = ?gd_RunDate" 
            // cHeader= "date_of_sale," + ;
            // "date_of_psa_call," + ;
            // "recid_unique," + ;
            // "cb_authorized_name_avaliable," + ;
            // "cb_happiness_score," + ;
            // "cb_happiness_score_comments," + ;
            // "cb_happiness_score_type," + ;
            // "cb_tsr_experience_text," + ;
            // "cb_tsr_experience_text_type," + ;
            // "cb_completed_tpv," + ;
            // "cb_tpv_experience_score," + ;
            // "cb_tpv_experience_text," + ;
            // "cb_tpv_experience_type," + ;
            // "cb_additional_comments_text," + ;
            // "cb_additional_comments_type," + ;
            // "cb_cancellation_reason," + ;
            // "cb_other_cancellation_reason_text," + ;
            // "cb_psa_long_disposition," + ;
            // "cb_time,record_type,psa_vendor,"+ ;
            // "SER_ID,ser_call_duration" + CHR(13)


            $headers = [
                // 'brand_name',
                // 'btn',
                // 'ani',
                // 'language',
                // 'channel',
                // 'agent_id',
                // 'agent_name',
                // 'customer_first_name',
                // 'customer_last_name',
                // 'tpv_agent_name',
                // 'tpv_agent_label',
                // 'email',
                // 'tranid',
                // 'date',
                // 'status',
                // 'product_code',
                // 'centerid',
                // 'operator_id',
                // 'duration',
                // 'account_number',
                // 'service_address1',
                // 'service_address2',
                // 'service_city',
                // 'service_state',
                // 'service_zip',
                // 'dt_attempt1',
                // 'attempt1',
                // 'dt_attempt2',
                // 'attempt2',
                // 'dt_attempt3',
                // 'attempt3',
                'date_of_sale',
                'date_of_psa_call',
                'recid_unique',
                'cb_authorized_name_avaliable',
                'cb_happiness_score',
                'cb_happiness_score_comments',
                'cb_happiness_score_type',
                'cb_tsr_experience_text',
                'cb_tsr_experience_text_type',
                'cb_completed_tpv',
                'cb_tpv_experience_score',
                'cb_tpv_experience_text',
                'cb_tpv_experience_type',
                'cb_additional_comments_text',
                'cb_additional_comments_type',
                'cb_cancellation_reason',
                'cb_other_cancellation_reason_text',
                'cb_psa_long_disposition',
                'cb_time',
                'record_type',
                'psa_vendor',
                'ser_id',
                'ser_call_duration',
                'confirmation_code'
            ];
 
            $data_array = $results->toArray();
            $data_written = (count($data_array) > 0)
                ? true
                : false;
            $subject = ($this->mode == 'test' ? 'TEST_' : '') .
                'Direct Energy PSA Call Backs ' .  date('m-d-Y H:i:s');
            if ($data_written) {
                 $this->info('Create survey report');
                
                // BEWARE!!!! FILENAME  when testing I found that DE automatically picks ALL .csv files up immediately
                // you will not find the file on their FTP server if you use a client like filezilla to verify the file was transferred.
                // if you change the file to end with .ttt if will show in outbound folder
                $filename =  ($this->mode == 'test' ? 'TEST_' : '') . 'direct_energy_psa_callbacks_salesforce_ftp_file_generation_new.csv';  //DXC name to send to DE FTP  

                // $filename = ($this->mode == 'test' ? 'TEST_' : '') .
                //     'direct_energy_psa_callbacks_salesforce_ftp_file_generation_new_' . date("Y_m_d_",time()) . time() . '.csv';  //email file
                //$filename = 'direct_energy_psa_report_' . date("Y_m_d_",time()) . time() . '.csv';
 //               $subject = ($this->mode == 'test' ? 'TEST_' : '') .
 //                   'Direct Energy PSA Call Backs ' .  date('m-d-Y H:i:s');
                $file = fopen(public_path('tmp/' . $filename), 'w');
                fputcsv($file, $headers);   //write header to file

                foreach ($data_array as $result) {
                    $qas = [];
                    $questions_answers = ScriptAnswer::select(
                        'script_questions.section_id',
                        'script_questions.subsection_id',
                        'script_questions.question_id',
                        'script_questions.question',
                        'script_answers.answer_type',
                        'script_answers.answer'
                    )->leftJoin(
                        'script_questions',
                        'script_answers.question_id',
                        'script_questions.id'
                    )->where(
                        'script_answers.interaction_id',
                        $result['interaction_id']
                    )->get();
                    foreach ($questions_answers as $qa) {
                        $qid = $qa->section_id . '.'
                            . $qa->subsection_id . '.'
                            . $qa->question_id;
                        $qas[$qid] = (isset($qa->answer) && 'null' !== $qa->answer && null !== $qa->answer)
                            ? $qa->answer : $qa->answer_type;
                    }
        
                    $customdata = json_decode($result['custom_data'], true);   // custom_data that are added into leads table json format 
                    $customfields = json_decode($result['custom_fields'], true);
                    $date_of_sale = '';   //DXC name
                    $date_of_psa_call = ''; //DXC name
                    $recid_unique = '';     //DXC name
                    $cb_authorized_name_avaliable = ''; //DXC name
                    $cb_happiness_score = '';  //DXC name
                    $cb_happiness_score_comments = '';  //DXC name
                    $cb_happiness_score_type = '';  //DXC name
                    $cb_tsr_experience_text = '';  //DXC name
                    $cb_tsr_experience_text_type = ''; //DXC name
                    $cb_completed_tpv = ''; //DXC name
                    $cb_tpv_experience_score = ''; //DXC name
                    $cb_tpv_experience_text = ''; //DXC name
                    $cb_tpv_experience_type = ''; //DXC name
                    $cb_additional_comments_text = ''; //DXC name
                    $cb_additional_comments_type = ''; //DXC name
                    $cb_cancellation_reason = ''; //DXC name
                    $cb_other_cancellation_reason_text = ''; //DXC name
                    $cb_psa_long_disposition = ''; //DXC name
                    $cb_time = ''; //DXC name
                    $record_type = ''; //DXC name
                    $psa_vendor = ''; //DXC name
                    $ser_id = ''; //DXC name
                    $ser_call_duration = ''; //DXC name

                    $custom_answers_found = false;
                    foreach ($customfields as $custom_answers) {
                        if ($custom_answers['output_name'] === 'happiness_score') {
                            $cb_happiness_score = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'unhappy_reason') {    
                            $cb_happiness_score_comments = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'sales_agent_feedback') {   
                            $cb_tsr_experience_text = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'over_phone') {
                            $cb_completed_tpv = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'overall_experience') {
                            $cb_tpv_experience_score = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'sales_agent_feedback2') {    
                            $cb_tpv_experience_text = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'additional_comments') {
                            $cb_additional_comments_text = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                        if ($custom_answers['output_name'] === 'cancel_reason') {
                            $cb_cancellation_reason = $custom_answers['value'];
                            $custom_answers_found = true;
                        }
                    }
                    // if ($custom_answers_found == false) {
                    //     continue;   // skip if no call backs yet
                    // }
                    // if ($result['confirmation_code'] == '20602170557') {
                    //     print_r($curt33);
                    //     print_r($customer_rating);
                    //     print_r($custom_answers['value']);
                    //     return;
                    // }

                    // $array['brand_name'] = $brand->name;      // brand_name
                    // $array['btn'] = (isset($result['btn']))
                    //     ? $result['btn'] : null;   // btn
                    // $array['ani'] = 'place holder';  // ani
                    // $array['language'] = $result['language'];   // language
                    // $array['channel'] = $result['channel']; //channel
                    // $array['agent_id'] = (isset($result['rep_id']))  // agent_id
                    //     ? $result['rep_id'] : null;
                    // $array['agent_name'] = (isset($result['survey_agent_name'])) // agent_name
                    //     ? $result['survey_agent_name'] : null;
                    // $array['customer_first_name'] = $result['customer_first_name'];  // customer_first_name
                    // $array['customer_last_name'] = $result['customer_last_name'];  // customer_last_name
                    // $array['tpv_agent_name'] = $result['tpv_agent_name'];
                    // $array['tpv_agent_label'] = $result['tpv_agent_label'];                           
                    // $array['customer_email'] = $customdata['customer_email'];    // email
                    // $array['tran_id'] = $result['confirmation_code']; // tranid
                    // $array['date'] = $customdata['date_and_time_of_sale']; // date
                    // $array['status'] = ('Sale' == $result['result'])   
                    //     ? 'Complete' : 'Unsuccessful';  // status
                    // $array['product_code'] = 'place holder';  // product_code   
                    // $array['center_id'] = 'place holder';  // centerid
                    // $array['operator_id'] = 'place holder';  // operator_id  ID of DXC rep that performed the last QC attempt
                    // $array['duration'] =  number_format($result['interaction_time'], 2);  // duration  // number_format($interaction->interaction_time, 2);
                    // $array['account_number'] = $result['account_number'];   // account_number
                    // $array['service_address1'] = $result['service_address1'];  // service_address1
                    // $array['service_address2'] = null;  // service_address2
                    // $array['service_city'] = $result['service_city'];  // service_city
                    // $array['service_state'] = $result['service_state'];  // service_state
                    // $array['service_zip'] = $result['service_zip'];  // service_zip
                    // $array['center_id'] = 'place holder';  // centerid
                    // $array['reason_code'] = null;  // reason_code
                    // $array['reason_description'] = null;  // reason_description
                    // $array['utility'] = $customdata['utilityname'];  // utility
                    // $array['vendor_name'] = $customdata['marketer'];  // vendor_name
                    // $array['dt_attempt1'] = $result['dt_attempt1']; // dt_attempt1
                    // $array['attempt1'] = $result['attempt1']; // attempt1
                    // $array['dt_attempt2'] = $result['dt_attempt2']; // dt_attempt2
                    // $array['attempt2'] = $result['attempt2'];  // attempt2
                    // $array['dt_attempt3'] = $result['dt_attempt3']; // dt_attempt3
                    // $array['dt_attemp3'] = $result['attempt3'];  // attempt3

                    // Direct energy fields for PSA
                    $array['date_of_sale'] = date_format(date_create_from_format('Y-m-d H:i:s',$result['event_created_at']),'m/d/Y');  // must use this instead of interaction_created_at  since this time stamp is updated for post tpv psa calls
                    $array['date_of_psa_call'] = date_format(date_create_from_format('Y-m-d H:i:s',$result['interaction_created_at']),'m/d/Y');
                    $array['recid_unique'] = $result['id'];  // survey GUID
                    $array['cb_authorized_name_avaliable'] =  ($result['result'] == 'Sale') ? 'Yes' : ''; // (empty($qas['1.1.1'])) ? '' : $qas['1.1.1'];
                    $array['cb_happiness_score'] = $cb_happiness_score;
                    $array['cb_happiness_score_comments'] = $cb_happiness_score_comments;
                    $array['cb_happiness_score_type'] = '';
                    $array['cb_tsr_experience_text'] = $cb_tsr_experience_text;
                    $array['cb_tsr_experience_text_type'] = '';
                    $array['cb_completed_tpv'] =  $cb_completed_tpv;
                    $array['cb_tpv_experience_score'] = $cb_tpv_experience_score;
                    $array['cb_tpv_experience_text'] = $cb_tpv_experience_text;
                    $array['cb_tpv_experience_type'] = '';
                    $array['cb_additional_comments_text'] = $cb_additional_comments_text;
                    $array['cb_additional_comments_type'] = '';
                    $array['cb_cancellation_reason'] =  $cb_cancellation_reason;
                    $array['cb_other_cancellation_reason_text'] = '';
                    $array['cb_psa_long_disposition'] =  ($result['result'] == 'Sale') ? 'Completed survey' : $result['reason'];
                    $array['cb_time'] = number_format($result['interaction_time'], 2);
                    $array['record_type'] = 'PSA DESAT';
                    $array['psa_vendor'] = 'TPVCOM';
                    $array['ser_id'] = 'TP' . $result['id'];  // survey GUID
                    $ser_call_duration = number_format($result['interaction_time'], 2);
                    //$ser_hrs = floor($ser_call_duration / 60);
                    $ser_mins = floor($ser_call_duration % 60);
                    $ser_secs = $ser_call_duration - (int)$ser_call_duration;
                    $ser_secs = round($ser_secs * 60);
                    //$array['ser_call_duration'] =  ($ser_hrs == 0 ? '' : (strval($ser_hrs) . '.')) . str_pad(strval($ser_mins),2,'0',STR_PAD_LEFT) . '.' . str_pad(strval($ser_secs),2,'0',STR_PAD_LEFT);
                    $array['ser_call_duration'] = str_pad(strval($ser_mins),1,'0',STR_PAD_LEFT) . '.' . str_pad(strval($ser_secs),2,'0',STR_PAD_LEFT);
                    $array['confirmation_code'] = $result['confirmation_code'];

                    if ($debug) {
                        print_r($array);
                        exit();
                    }

                    fputcsv($file, $array);
                }  // endfor
                fclose($file);

                // Upload the file to FTP server
                if (!$this->option('noftp')) {
                    $this->info('Uploading file...');
                    $this->info($filename);
                    $ftpResult = 'SFTP at ' . Carbon::now() . '. Status: ';
                    try {
                        $stream = fopen(public_path('tmp/' . $filename), 'r+');
                        $filesystem->writeStream(
                            $filename,
                            $stream
                        );

                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                        $ftpResult .= 'Success!';
                    } catch (\Exception $e) {
                        $ftpResult .= 'Error! The reason reported is: ' . $e;
                        $this->info($ftpResult);
                    }
                
                    $this->info($ftpResult);

                    if (isset($ftpResult)) {
                        if (strpos(strtolower($ftpResult),'success') > 0) {
                            $this->info('Upload succeeded.');

                            $this->sendEmail('File ' . $filename . ' has been successfully uploaded.', $this->distroList['ftp_success'][$this->mode]);
                        } else {
                            $this->info('Upload failed.');
                            $this->sendEmail(
                                'Error uploading file ' . $filename . ' to FTP server ' . $this->ftpSettings['host'] .
                                    "\n\n FTP Result: " . $ftpResult,
                                $this->distroList['ftp_error'][$this->mode]
                            );

                            return -1; // Quit early. We don't want totals email going out unless the upload succeeded.
                        }
                    }
                }

                // Regardless of FTP result, also email the file as an attachment
                if (!$this->option('noemail')) {
                    $this->info('Email survey report');
                    $attachments = [public_path('tmp/' . $filename)];
                    $this->sendEmail($subject,$this->distroList['emailed_file'][$this->mode],$attachments);
                    $this->info('Job Finished!!');

                }
                unlink(public_path('tmp/' . $filename));
            } else {
                if (!$this->option('noemail')) {
                     $this->info('Email survey report');
                    //$attachments = [public_path('tmp/' . $filename)];
                    $this->sendEmail('There were no surveys to send!',$this->distroList['emailed_file'][$this->mode]);
                    $this->info('There were no surveys to send! Job Finished!!');

                }
            }
        }
    }
    /**
     * Sends and email.
     *
     * @param string $message - Email body.
     * @param array  $distro  - Distribution list.
     * @param array  $files   - Optional. List of files to attach.
     *
     * @return string - Status message
     */
    public function sendEmail(string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != env('APP_ENV')) {
            $subject = $this->jobName . ' (' . env('APP_ENV') . ') '
                . Carbon::now();
        } else {
            $subject = $this->jobName . ' ' . Carbon::now();
        }

        if ($this->mode == 'test') {
            $subject = '(TEST) ' . $subject;
        }

        $data = [
            'subject' => '',
            'content' => $message
        ];

        for ($i = 0; $i < count($email_address); ++$i) {
            $status = 'Email to ' . $email_address[$i]
                . ' at ' . Carbon::now() . '. Status: ';

            try {
                Mail::send(
                    'emails.generic',
                    $data,
                    function ($message) use ($subject, $email_address, $i, $files) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');
                        $message->to(trim($email_address[$i]));

                        // add attachments
                        foreach ($files as $file) {
                            $message->attach($file);
                        }
                    }
                );
            } catch (\Exception $e) {
                $status .= 'Error! The reason reported is: ' . $e;
                $uploadStatus[] = $status;
            }

            $status .= 'Success!';
            $uploadStatus[] = $status;
        }

        return $uploadStatus;
    }
}
