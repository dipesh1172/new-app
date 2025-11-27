<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\CustomFieldStorage;
use App\Models\ProviderIntegration;
use App\Models\StatsProduct;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DirectEnergy_AB_StatisticsReport extends Command
{

    public $cloudfront;
    public $folderName = null;
 
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy_AB_StatisticsReport {--mode=} {--date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Direct Energy AB Statistics Report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates, FTPs, Recordings and Emails Direct Energy\'s enrollment file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '94d29d20-0bcf-49a3-a261-7b0c883cbd1d'; //  prod ID
  

    /**
     * Report select date
     *
     * @var mixed
     */
    protected $selDate = null;


    /**
     * Report mode: 'live' or 'test'.
     *
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        $this->cloudfront = config('services.aws.cloudfront.domain');
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Check mode.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Unrecognized --mode arg value: ' . $this->option('mode'));
            }
        }
    
        $this->selDate = Carbon::today('America/Chicago');

        // Check for and validate custom report date
        if ($this->option('date')) {
             $this->selDate = Carbon::parse($this->option('date'));
            $this->info('Using custom date..');
            $jobSchedule = 'Nightly';
        } else {
            $timeToCheck = Carbon::now('America/Chicago')->format('H');
            if ($timeToCheck <= '19') {
                $jobSchedule = 'Midday'; 
            } else {
                $jobSchedule = 'Nightly'; 
            }
        }

        $csvFile = "de_ab_stats_email_groups_do_not_delete_used_for_report.csv";
        $fc = str_getcsv(file_get_contents($csvFile));
        $file = fopen($csvFile,"r");
        $keys = fgetcsv($file);
        while (!feof($file)) {
            $emailDistribution[] = array_combine($keys, fgetcsv($file));
        }
        fclose($file);
         $this->info("Retrieving TPV data...");
         $podid = 'convert(replace(substring(custom_fields,POSITION(\'"pod_id","product":null,"value":\' IN custom_fields)+33,3),\'"\',\'\'),DECIMAL(3,0)) AS pod_id';
         foreach ($emailDistribution as $emailRec) {
            $officeName = [];
            if (strstr($emailRec['office'],'Calgary')) {
              array_push($officeName,'Calgary');
            }
            if (strstr($emailRec['office'],'Edmonton')) {
              array_push($officeName,'Edmonton');
            }
            if (empty($officeName)) {
                return 0;
            }
            $emailStatisticsTo = str_getcsv($emailRec['email']);
            $vendorId = $emailRec['center_id'];
            $data = StatsProduct::distinct()->select(
                'stats_product.result',
                'stats_product.custom_fields',
                'stats_product.event_id',
                'stats_product.vendor_id',
                'stats_product.vendor_name',
                'stats_product.vendor_code',
                'stats_product.vendor_grp_id',
                'stats_product.office_id',
                'stats_product.office_label',
                'stats_product.office_name',
                'stats_product.sales_agent_id',
                'stats_product.sales_agent_name',
                'stats_product.sales_agent_rep_id',
                'stats_product.confirmation_code',
                DB::raw($podid)
            )->whereDate(
                'stats_product.interaction_created_at',
                '=',
                $this->selDate->toDateString()
            )->where(
                'stats_product.brand_id',
                $this->brandId
            )->whereIn(
                'stats_product.office_name',
                $officeName);
            if ($vendorId <> 'ALL') {
                $data = $data->where('stats_product.office_label',$vendorId);
            }
            $data = $data->whereIn(
                'stats_product.service_state',
                ['AB','SK']
            )->whereIn(
                'stats_product.result',
                ['sale']
            )->orderBy(
                'stats_product.office_label'
            )->orderBy(
                'pod_id'
            )->orderBy(
                'stats_product.sales_agent_rep_id'
            )->get();
            // If no records, email client and exit early
            $this->info(count($data) . ' Record(s) found.');
            $data_array = $data->toArray();
            
            $subject = "Direct Energy Residential AB " . $jobSchedule . " Statistics for " . $this->selDate->format('m-d-Y');
            $emailBody = "AB " . $jobSchedule . " Statistics: " . $this->selDate->format('m-d-Y') . "<br/>";
            if(count($data) === 0) {
                $this->info('0 records. Sending results email to: ' . $emailStatisticsTo[0] . ' for Center '. $vendorId);
                $message = 'There were no sales to send for Center ' . $vendorId . ' ' . $this->selDate->format('m-d-Y') . '.';
                $this->sendEmail($subject,$message, $emailStatisticsTo);

                continue;
            }
            $AgentOfficeUnits = 0;
            $CenterOfficeTotal = 0;
            $GrandTotalCenter = 0;
            $firstTime = true;
            foreach ($data as $r) {
                if ($firstTime){
                    $firstTime = false;
                    $CenterID = $r->office_label;
                    $OfficeID = $r->pod_id;
                    $TSRID = $r->sales_agent_rep_id;
                    $SalesAgentName = $r->sales_agent_name;
                    $emailBody .= "Center " . $CenterID . ", Office " . $OfficeID . "<br/>";
                }
                if ($CenterID == $r->office_label and $OfficeID == $r->pod_id and $SalesAgentName == $r->sales_agent_name) {  // same agent
                    $AgentOfficeUnits++;
                    $CenterOfficeTotal++;
                } else {   // different agent
                    $emailBody .=  "TSR: " . $SalesAgentName . " Units: " . $AgentOfficeUnits . "<br/>";
                    $AgentOfficeUnits = 1;
                    $TSRID = $r->sales_agent_rep_id;
                    $SalesAgentName = $r->sales_agent_name;
                    if (!($CenterID == $r->office_label and $OfficeID == $r->pod_id)) {   // center and office has changed
                            $emailBody .=  "Totals for Center " . $CenterID . ", Office " . $OfficeID . " Units: " . $CenterOfficeTotal . "<br/><br/>";
                            $CenterID = $r->office_label;
                            $OfficeID = $r->pod_id;
                            $CenterOfficeTotal = 0;
                            $emailBody .= "Center " . $CenterID . ", Office " . $OfficeID . "<br/>";
                    }
                    $CenterOfficeTotal++;
                }
                $GrandTotalCenter++;

                $curts = '';
            }
            $emailBody .=  "TSR: " . $SalesAgentName . " Units: " . $AgentOfficeUnits . "<br/>";
            $emailBody .=  "Totals for Center " . $CenterID . ", Office " . $OfficeID . " Units: " . $CenterOfficeTotal . "<br/><br/>";
            $emailBody .= "Overall Totals Units: " . $GrandTotalCenter . "<br/>";
            $this->info('Sending results email to: ' . $emailStatisticsTo[0] . ' for Center '. $vendorId);
            $ttttt = $this->sendEmail($subject,$emailBody, $emailStatisticsTo);
           
        }
        return;
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
    protected function sendEmail(string $jobName,string $message, array $distro, array $files = array())
    {
        $uploadStatus = [];
        $email_address = $distro;

        // Build email subject
        if ('production' != env('APP_ENV')) {
            $subject = $jobName . ' (' . env('APP_ENV') . ') '
                . 'Date Run: ' . Carbon::now();
        } else {
            $subject = $jobName . ' ' . 'Date Run: ' .Carbon::now();
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
