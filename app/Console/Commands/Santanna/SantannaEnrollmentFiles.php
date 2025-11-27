<?php

namespace App\Console\Commands\Santanna;

use App\Helpers\FtpHelper;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;

/**
 * Creates and uploads Santanna's DTD and TM enrollment files. The files are created on the TPV FTP server. 
 */
class SantannaEnrollmentFiles extends Command
{
    use ExportableTrait;
    use DeliverableTrait;

    /**
     * The name and signature of the console command.
     * 
     * --mode[test,live]: Setting this to test will upload the file to the dxctest FTP account instead of Santanna's FTP account.
     * --env[staging,prod]: Determines which environment's brand ID is used.
     * --no-delivery: Creates the files on the job server instead of the FTP server.
     * --no-email: Suppress email
     * --channel[dtd,tm]: Normally, the job creates both the DTD and TM files. Use this arg to only create one or the other file.
     *
     * @var string
     */
    protected $signature = 'Santanna:EnrollmentFiles {--mode=} {--env=} {--no-delivery} {--no-email} {--channel=} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Santanna - Enrollment File';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Santanna enrollment files process. Creates one file per channel for the request date range. Leaves the files on TPV.com\'s FTP server for the client to pick up.';

    /**
     * The brand identifier
     *
     * @var array
     */
    protected $brandId = [
        'staging' => '7c88b08c-5576-41f0-898a-1b1c8c8983c4',
        'prod' => 'a6271008-2dc4-4bac-b6df-aa55d8b79ec7'
    ];

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
            'live' => ['dxc_autoemails@tpv.com'],
            'test' => ['dxc_autoemails@tpv.com']
        ],
        'error' => [ // For general errors that require the program to quit early
            'dxc_autoemails@tpv.com', 'engineering@tpv.com'
        ]   
    ];

    /**
     * FTP Settings
     *
     * @var array
     */
    protected $ftpSettings = null;

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
     * Environment: 'prod' or 'staging'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

    /**
     * Files are written to here before upload
     * 
     * @var string
     */
    protected $filePath = '';

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
        $this->startDate = Carbon::yesterday();
        $this->endDate = Carbon::yesterday()->endOfDay();

        $this->verbose = $this->option('verbose');

        $this->filePath = public_path('tmp/');

        // Validate mode.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            } else {
                $this->error('Invalid --mode value: ' . $this->option('mode'));
                return -1;
            }
        }

        // Validate env.
        if ($this->option('env')) {
            if (
                strtolower($this->option('env')) == 'prod' ||
                strtolower($this->option('env')) == 'staging'
            ) {
                $this->env = strtolower($this->option('env'));
            } else {
                $this->error('Invalid --env value: ' . $this->option('env'));
                return -1;
            }
        }

        // Check for and validate custom report dates, but only if both start and end dates are provided
        if ($this->option('start-date') && $this->option('end-date')) {
            $this->startDate = Carbon::parse($this->option('start-date'));
            $this->endDate = Carbon::parse($this->option('end-date'));
            $this->info('Custom dates used.');
        }

        // Check for an validate channel
        $channelsToProcess = [];
        if ($this->option('channel')) {
            if (
                strtoupper($this->option('channel')) == 'DTD' ||
                strtoupper($this->option('channel')) == 'TM'
            ) {
                $channelsToProcess[] = strtoupper($this->option('channel'));
            } else {
                $this->error('Invalid --channel value: ' . $this->option('channel'));
            }
        } else { // Not specified. Process both DTD and TM.
            $channelsToProcess[] = 'DTD';
            $channelsToProcess[] = 'TM';
        }

        $this->info('Start:   ' . $this->startDate);
        $this->info('End:     ' . $this->endDate);
        $this->info('BrandId: ' . $this->brandId[$this->env]);
        $this->info('Processing for ' . implode(' and ', $channelsToProcess) . "\n");

        // Get FTP settings
        $this->info('Getting FTP settings...');
        $this->ftpSettings = $this->getFtpSettings();

        if(!$this->ftpSettings) {
            $this->error('Unable to retrieve FTP settings. Exiting...');
            exit -1;
        }

        // Run entire process for current channel before moving on to next channel.
        foreach ($channelsToProcess as $channel) {

            $filename = 'santanna_energy_sc_res_' . strtolower($channel) . '_' . $this->startDate->format('Ymd') . '.txt';
            $this->info('Creating file ' . $filename . '...');

            $data = StatsProduct::select(
                'id',
                'interaction_created_at',
                'vendor_grp_id',
                'bill_first_name',
                'bill_middle_name',
                'bill_last_name',
                'auth_first_name',
                'auth_middle_name',
                'auth_last_name',
                'service_address1',
                'service_address2',
                'service_city',
                'service_state',
                'service_zip',
                'btn',
                'utility_commodity_ldc_code',
                'account_number1',
                'account_number2',
                'confirmation_code',
                'result',
                'sales_agent_rep_id',
                'email_address'
            )->whereDate(
                'interaction_created_at',
                '>=',
                $this->startDate
            )->whereDate(
                'interaction_created_at',
                '<=',
                $this->endDate
            )->where(
                'brand_id',
                $this->brandId[$this->env]
            )->where(
                'channel',
                $channel
            )->where(
                'result',
                'sale'
            );
            
            $this->info("SQL:");
            $this->info($data->toSql());
            $this->info("Bindings");
            print_r($data->getBindings());
            $data = $data->get();

            // Create a file, even if it ends up being empty            
            $records = count($data);
            $recNo = 1;

            $contents = [];

            if ($records == 0) {
                $this->info('No records');
            }

            foreach ($data as $row) {

                $this->info('Record ' . $recNo . ' of ' . $records . ':');
                $this->info('Formatting...');

                $contents[] = implode("\t", [
                    '0' => $row->vendor_grp_id,
                    '1' => '09011',
                    '2' => $row->interaction_created_at->format('Y/m/d H:i:s'),
                    '3' => $row->bill_first_name . ' ' . ltrim($row->bill_middle_name . ' ' . $row->bill_last_name),
                    '4' => $row->auth_first_name . ' ' . ltrim($row->auth_middle_name . ' ' . $row->auth_last_name),
                    '5' => $row->service_address1,
                    '6' => $row->service_address2,
                    '7' => $row->service_city,
                    '8' => $row->service_state,
                    '9' => $row->service_zip,
                    '10' => preg_replace("/^(\d{3})(\d{3})(\d{4})$/", "$1-$2-$3", trim($row->btn, '+1')), // format 999-999-9999
                    '11' => $row->external_commodity_ldc_code,
                    '12' => $row->account_number1,
                    '13' => $row->account_number2, // Removed logic that would set SSN4 if meter number is blank. No longer needed since account validation not being implemented in Focus
                    '14' => '', // Last billed usage. No longer needed since account validation not being implemented in Focus
                    '15' => (!empty($row->company_name) ? '1' : '0'), // 1 - commercial, 0 - residential
                    '16' => $row->confirmation_code,
                    '17' => (strtolower($row->result) == 'sale' ? 'S' : 'F'),
                    '18' => '',
                    '19' => '', // Account code from account validation check API. No longer needed since  account validation not being implemented in Focus
                    '20' => '', // Used to be mapped to CV_ID, but no longer applicable since they started providing all rate info in HTTP post
                    '21' => $row->sales_agent_rep_id,
                    '22' => $row->email_address
                ]);

                $recNo++;
            }

            // Write the file
            $this->info('Writing the file...');

            try {
                $this->writeFile(public_path('tmp') . '/' . $filename, $contents);
            } catch(\Exception $e) {
                $this->error('Write File Exception: ' . $e->getMessage());
                exit -1;
            }

            // We're done here if the no-delivery option is set
            if($this->option('no-delivery')) {
                $this->info('no-delivery option set. File was created but not uploaded.');
                break;;
            }
                
            // Deliver the file
            try {
                $this->info('Uploading file...');
                $ftpResult = $this->sftpUpload(public_path('tmp') . '/' . $filename, $this->ftpSettings);

                if (strpos(json_encode($ftpResult), 'Status: Success!') != true) {
                    $this->error('FTP upload failed!');
                    $this->error($ftpResult);
                    break;
                }
            } catch (\Exception $e) {

                $this->sendGenericEmail([
                    'to' => $this->distroList['ftp_error'][$this->mode],
                    'subject' => $this->getEmailSubject(),
                    'body' => 'Error uploading file ' . $filename . "\n\n" . "Error Message: \n" . $e->getMessage()
                ]);                                    
            }

            if ($this->option('no-email')) {
                $this->info('Skipping email notification...');
                break;
            }

            try {
                $this->info('Sending e-mail notification...');

                $this->sendGenericEmail([
                    'to' => $this->distroList['ftp_success'][$this->mode],
                    'subject' => $this->getEmailSubject(),
                    'body' => 'Successfully created file ' . $filename . ".\n\n" . $records . ' record(s) processed.'
                ]);
            } catch (\Exception $e) {
                $this->error('Email exception:');
                $this->error($e->getMessage());
            }

            unlink(public_path('tmp') . '/' . $filename);
        }
    }   

    /**
     * Creates and returns an email subject line string
     */
    private function getEmailSubject() {
        return $this->jobName . (env('APP_ENV') != 'production' ? ' (' . env('APP_ENV') . ') ' : ' ' . Carbon::now("America/Chicago")->format('Y-m-d'));
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings(): ?array {

        return FtpHelper::getSettings(
                    $this->brandId[$this->env],
                    38,
                    1,
                    (config('app.env') === 'production' ? 1 : 2)
                );
    }
}
