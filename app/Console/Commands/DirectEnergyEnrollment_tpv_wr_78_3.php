<?php

namespace App\Console\Commands;

use League\Flysystem\Sftp\SftpAdapter;
use League\Flysystem\Filesystem;
//use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\ProviderIntegration;
use App\Models\StatsProduct;

class DirectEnergyEnrollment_tpv_wr_78_3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'DirectEnergy:Enrollment_tpv_wr_78_3 {--mode=} {--noftp} {--noemail} {--start-date=} {--end-date=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'Direct Energy - Enrollment File - tpv_wr_78_3 ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates, FTPs, and Emails Direct Energy\'s enrollment file';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '94d29d20-0bcf-49a3-a261-7b0c883cbd1d'; //  prod ID
  

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'ftp_success' => [ // FTP success email notification distro
            'live' => ['dxc_autoemails@tpv.com','sharon.gallardo@answernet.com', '_tpvteam@directenergy.com','curt.cadwell@answernet.com','contract.alberta@directenergy.com'],
            'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']
        ],
        'ftp_error' => [ // FTP failure email notification distro
           'live' => ['dxcit@tpv.com', 'engineering@tpv.com'],
           'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com']
           //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

        ],
        'emailed_file' => [ // Emailed copy of the file distro
            'live' => ['curt.cadwell@answernet.com','sharon.gallardo@answernet.com','dxc_autoemails@tpv.com'],
            'test' => ['dxcit@tpv.com']
            //'test' => ['curt.cadwell@answernet.com','curt@tpv.com']

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
        'root' => '/',
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

        $this->startDate = Carbon::today('America/Chicago');
        $this->endDate = Carbon::tomorrow('America/Chicago')->add(-1, 'second');

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
            $this->brandId
        )->where(
            'provider_integration_type_id',
            1
        )->where(
            'service_type_id',
            32
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
        // Build file name
        $filename = ($this->mode == 'test' ? 'TEST_' : '')
            . 'TPV_WR_78_3_'
            . $this->startDate->year 
            . str_pad($this->startDate->month, 2, '0', STR_PAD_LEFT) 
            . str_pad($this->startDate->day, 2, '0', STR_PAD_LEFT) 
            . '013500.csv';
 
        // Data layout/File header for main TXT/CSV files.
        $csvHeader = $this->flipKeysAndValues([
            'Contract_ID_Number', 'TPV_Confirmation_Number', 'TPV_Date', 'TPV_Time', 'TPV_Status', 'Bonus_Status',
            'offer_code', 'promo_code'
        ]);

        $csv = array(); //  formatted data CSV file.

        $this->info("Retrieving TPV data...");
        $data = StatsProduct::select(
            'stats_product.id',
            'stats_product.event_id',
            'stats_product.confirmation_code',
            'stats_product.language',
            'stats_product.interaction_created_at',
            'stats_product.market',
            'stats_product.btn',
            'stats_product.email_address',
            'stats_product.vendor_grp_id',
            'stats_product.vendor_label',
            'stats_product.sales_agent_rep_id',
            'stats_product.sales_agent_name',
            'stats_product.auth_first_name',
            'stats_product.auth_last_name',
            'stats_product.bill_first_name',
            'stats_product.bill_last_name',
            'stats_product.service_address1',
            'stats_product.service_address2',
            'stats_product.service_city',
            'stats_product.service_state',
            'stats_product.service_zip',
            'stats_product.account_number1',
            'stats_product.commodity',
            'stats_product.rate_program_code',  
            'stats_product.utility_commodity_ldc_code',
            'stats_product.rate_external_id',
            'stats_product.product_rate_amount',
            'brand_promotions.promotion_code',
            'brand_promotions.promotion_key',
            'stats_product.result',
            'stats_product.event_created_at',  // can't use interaction_created_at because of psa surveys
            'stats_product.product_name'
        )->leftJoin(
            'event_product',
            'stats_product.event_product_id',
            'event_product.id'
        )->leftJoin(
            'brand_promotions',
            'event_product.brand_promotion_id',
            'brand_promotions.id'
        )->whereDate(
            'stats_product.interaction_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'stats_product.interaction_created_at',
            '<=',
            $this->endDate
        )->where(
            'stats_product.brand_id',
            $this->brandId
        // )->where(
        //     'stats_product.confirmation_code',
        //     '22632123760'
//        )->where(
//            'stats_product.result',
//            'sale'
        )->whereIn(
            'stats_product.result',
            ['sale', 'no sale']
        )->whereIn(
            'stats_product.service_state',
            ['AB','SK']
        )->orderBy(
            'stats_product.interaction_created_at'
        )->get();
        
        $this->info(count($data) . ' Record(s) found.');
        $save_conf_code = '';
        // Format and populate data CSV file
        $this->info('Formatting data...');
        foreach ($data as $r) {
            $this->info('   ' . $r->event_id . ':');

            // for AB and SK only one confirmation number this will eliminate duplicate records from dual fuel
            if (strpos($save_conf_code,$r->confirmation_code) > 0) { // search array (didn't want to use distinct on select statement)
                continue;  // skip if confirmation already found
            }
            $save_conf_code = $save_conf_code . $r->confirmation_code . ',';

            // Map data to enrollment CSV file fields.
            $row = [
//                $csvHeader['TransactionID'] => '',
                $csvHeader['Contract_ID_Number'] => strtoupper($r->account_number1),
                $csvHeader['TPV_Confirmation_Number']  => $r->confirmation_code,
                $csvHeader['TPV_Date'] => date_format(date_create_from_format('Y-m-d H:i:s',$r->event_created_at),'Y-m-d'),
                $csvHeader['TPV_Time'] => date_format(date_create_from_format('Y-m-d H:i:s',$r->event_created_at),'H:i:s'),
                $csvHeader['TPV_Status'] => ($r->result == 'Sale') ? 'VR' : 'CA',
                $csvHeader['Bonus_Status'] => 'C1',
//                $csvHeader['program_code'] => str_replace('e','',str_replace('g','',$r->rate_program_code)), // strip e or g
//                $csvHeader['program'] => $r->product_name,
                $csvHeader['offer_code'] => $r->rate_external_id, 
                $csvHeader['promo_code'] => $r->promotion_key,
//                $csvHeader['promo_key'] => str_replace('AB','',str_replace('SK','',$r->promotion_code)),    // strip either SK or AB
                // $csvHeader['UserID'] => $r->vendor_label,
                // $csvHeader['VendorTransactionID'] => '99' . $r->confirmation_code,
                // $csvHeader['Version'] => '1',
                // $csvHeader['PowerPricePlanID'] => (strtolower($r->commodity) == 'electric' ? $r->rate_program_code : ''),
                // $csvHeader['GasPricePlanID'] => (strtolower($r->commodity) == 'natural gas' ? $r->rate_program_code : ''),
                // $csvHeader['PowerTDSP'] => (strtolower($r->commodity) == 'electric' ? $r->utility_commodity_ldc_code : ''),
                // $csvHeader['GasTDSP'] => (strtolower($r->commodity) == 'natural gas' ? $r->utility_commodity_ldc_code : ''),
                // $csvHeader['PowerPriceZoneCode'] => '',
                // $csvHeader['GasPriceZoneCode'] => '',
                // $csvHeader['PowerProductID'] => (strtolower($r->commodity) == 'electric' ? $r->rate_external_id : ''),
                // $csvHeader['GasProductID'] => (strtolower($r->commodity) == 'natural gas' ? $r->rate_external_id : ''),
                // $csvHeader['PromoCode'] => '',
                // $csvHeader['FirstName'] => (strtolower($r->market) == 'residential' ? $r->bill_first_name : $r->auth_first_name),
                // $csvHeader['MiddleInitial'] => '',
                // $csvHeader['LastName'] => (strtolower($r->market) == 'residential' ? $r->bill_last_name : $r->auth_last_name),
                // $csvHeader['PhoneNum'] => ltrim($r->btn, '+1'),
                // $csvHeader['MobileNum'] => '1111111111',
                // $csvHeader['DateOfBirth'] => '7/4/1976',
                // $csvHeader['Email'] => (!empty($r->email_address) ? $r->email_address : 'no@noemail.com'),
                // $csvHeader['ContactType'] => 'PHONE',
                // $csvHeader['Language'] => $r->language,
                // $csvHeader['EBill'] => '',
                // $csvHeader['IsBillingSame'] => 'TRUE',
                // $csvHeader['BillingCity'] => '',
                // $csvHeader['BillingState'] => '',
                // $csvHeader['BillingStreetNum'] => '',
                // $csvHeader['BillingStreet'] => '',
                // $csvHeader['BillingAptNum'] => '',
                // $csvHeader['BillingZipCode'] => '',
                // $csvHeader['ServiceAddress'] => trim($r->service_address1 . ' ' . $r->service_address2),
                // $csvHeader['ServiceCity'] => $r->service_city,
                // $csvHeader['ServiceState'] => $r->service_state,
                // $csvHeader['ServiceZipCode'] => substr($r->service_zip, 0, 5),
                // $csvHeader['SwitchMoveDate'] => $r->interaction_created_at->format('m-d-Y'),
                // $csvHeader['PowerMeterNo'] => (strtolower($r->commodity) == 'electric' ? $r->account_number1 : ''),
                // $csvHeader['GasMeterNo'] => (strtolower($r->commodity) == 'natural gas' ? $r->account_number1 : ''),
                // $csvHeader['Ssn'] => '111111111',
                // $csvHeader['SwitchMoveType'] => 'SWITCH',
                // $csvHeader['TpvNumber'] => '99' . $r->confirmation_code,
                // $csvHeader['Commodity'] => (strtolower($r->commodity) == 'electric' ? 'POWER' : 'GAS'),
                // $csvHeader['VendorAgentName'] => implode('', explode(',', $r->sales_agent_name)), // remove commas from agnet name. copied logic from legacy.
                // $csvHeader['VendorAgentCode'] => $r->sales_agent_rep_id,
                // $csvHeader['TPVDate'] => $r->interaction_created_at->format('m-d-Y'),
                // $csvHeader['Promotion'] => $r->promotion_code,
            ];

            // Add this row of data to the correct vendor in the CSV array.
            $csv[] = $row;
        }

        // Write the CSV file.
        // Intentially writing without enclosures to mimic legacy enrollment file.
        $this->info('Writing CSV file...');
        $file = fopen(public_path('tmp/' . $filename), 'w');

        // Header Don't need header will skip for now
        // $fileHeader = [];
        // foreach ($csvHeader as $key => $value) {
        //     $fileHeader[] = $key;
        // }
        // fputs($file, implode(',', $fileHeader) . "\r\n");

        // Data
         foreach ($csv as $row) {
             fputs($file, implode(',', $row) . "\r\n");
         }
         fclose($file);

        // Start PGP Encrypt
        $gpg = gnupg_init();
        // this displays all keys in keyring pass a blank string to list keys in php  
        // use gpg --list-keys in terminal to mess with keyring lots of options
        // https://www.gnupg.org/documentation/manpage.html
        // https://stackoverflow.com/questions/15969740/encrypt-files-using-pgp-in-php
        $errKey = gnupg_addencryptkey($gpg,'1B98B1F9A3C66946AEB1A5F1031251540784824D');  // get this fingerprint from ringkey use gpg command
        if ($errKey) {
            $uploadFileContent = file_get_contents(public_path('tmp/'. $filename));
            $encryptFile = gnupg_encrypt($gpg,$uploadFileContent);
            $error1 = file_put_contents(public_path('tmp/'. $filename . '.pgp'), $encryptFile);
        } else {
            $this->info('Encryption failed.');
            $this->sendEmail('Error with Encryption ' . $filename,$this->distroList['ftp_error'][$this->mode]);
            return -1; // Quit early. 
        }

        // END PGP Encrypt

        // BEWARE!!!! FILENAME  when testing I found that DE automatically picks ALL .csv files up immediately
        // you will not find the file on their FTP server if you use a client like filezilla to verify the file was transferred.
        // if you change the file to end with .ttt if will show in folder

        // Upload the file to FTP server
        if (!$this->option('noftp')) {
            $this->info('Uploading file...');
            $this->info($filename);
            $ftpResult = 'SFTP at ' . Carbon::now() . '. Status: ';
            try {
                $stream = fopen(public_path('tmp/' . $filename . '.pgp'), 'r+');
                $filesystem->writeStream(
                    $filename . '.pgp',
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
            $attachments[] = public_path('tmp/' . $filename);
            $attachments[] = public_path('tmp/' . $filename . '.pgp');

            $this->info("Emailing file...");
            $this->sendEmail('Attached is the API file for ' . $this->startDate->format('m-d-Y') . '.', $this->distroList['emailed_file'][$this->mode], $attachments);
        }
        // Delete tmp file
        unlink(public_path('tmp/' . $filename));
        unlink(public_path('tmp/' . $filename . '.pgp'));
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


    /**
     * Keys become values and values become keys. It's assumed that the values are unique.
     *
     * @return mixed
     */
    private function flipKeysAndValues($inputArray)
    {
        $tempArray = [];

        foreach ($inputArray as $key => $value) {
            $tempArray[trim($value)] = $key;
        }

        return $tempArray;
    }
}
