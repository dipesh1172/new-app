<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\Vendor;

class ParkPowerEnrollmentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ParkPower:EnrollmentFile {--mode=} {--start-date=} {--end-date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emailed nightly enrollment files for Park Power. A separate file is created for each active vendor, whether they had activity or not.';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = '5e2e9249-cc27-4681-ab02-3b4b9e71f6cb';

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'live' => ['ldesanto@parkpower.com', 'aferraioli@parkpower.com', 'dxc_autoemails@dxc-inc.com', 'jcolia@parkpower.com'],
        'test' => ['dxcit@tpv.com', 'engineering@tpv.com']
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
        $this->startDate = Carbon::yesterday();
        $this->endDate = Carbon::today()->add(-1, 'second');

        $filesToEmail = array(); // Email file attachments

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

        // Data layout/File header for main TXT/CSV files.
        $csvHeader = $this->flipKeysAndValues([
            'Product ID', 'Utility DUNS ID', 'Utility Account Nbr', 'Customer Type', 'Name Prefix', 'First Name', 'Middle Initial', 'Last Name',
            'Organization Name', 'Title', 'Service Street Nbr', 'Service Address', 'Service City', 'Service State', 'Service Postal Code',
            'Service Postal Code +4', 'County', 'Phone', 'Email', 'HU Request', 'Bill Model', 'Sales Person Code', 'EES Agent',
            'Sales Date', 'Meter Number', 'Contract Effective Date', 'Promo Code (export AA)'
        ]);

        $csv = array(); // Houses formatted data CSV file.

        $this->info("Retrieving Active Vendors list");
        $vendorData = Vendor::select(
            'vendors.vendor_id',
            'vendors.vendor_label',
            'brands.name AS vendor_name'
        )->leftJoin(
            'brands',
            'vendors.vendor_id',
            'brands.id'
        )->where(
            'vendors.brand_id',
            $this->brandId
        )->where(
            'brands.active',
            1
        )->whereNull(
            'brands.deleted_at'
        )->orderBy(
            'brands.name'
        )->get();

        $this->info(count($vendorData) . ' Vendors(s) found.');
        if (count($vendorData) == 0) {
            return 0;
        }

        // CSV data will be stored in a multi-dimension array, grouped by vendor.
        // Create that array and set up the vendor groups.
        foreach ($vendorData as $r) {
            $arr = [
                'name' => $r->vendor_name,
                'label' => $r->vendor_label,
                'data' => array()
            ];

            $csv[$r->vendor_id] = $arr;
        }

        $this->info('Retrieving TPV data...');
        $data = StatsProduct::select(
            'event_id',
            'vendor_id',
            'rate_program_code',
            'utility_commodity_external_id',
            'account_number1',
            'market',
            DB::raw("'' AS name_prefix"),
            'bill_first_name',
            'bill_middle_name',
            'bill_last_name',
            DB::raw("'' AS organizaltion_name"),
            DB::raw("'' AS title"),
            'service_address1',
            'service_city',
            'service_state',
            'service_zip',
            'service_county',
            'btn',
            'email_address',
            DB::raw("'Y' AS hu_request"),
            'rate_source_code',
            'vendor_label',
            'sales_agent_rep_id',
            'event_created_at',
            'account_number2',
            DB::raw("'' AS contract_effective_date"),
            DB::raw("'' AS promo_code"),
            'custom_fields',
            'result',
            'event_product_id'
        )->whereDate(
            'event_created_at',
            '>=',
            $this->startDate
        )->whereDate(
            'event_created_at',
            '<=',
            $this->endDate
        )->where(
            'result',
            'sale'
        )->where(
            'brand_id',
            $this->brandId
        )->orderBy(
            'event_created_at'
        )->get();

        $this->info(count($data) . ' Record(s) found.');

        // Format and populate data CSV file
        foreach ($data as $r) {

            $this->info($r->event_id . ':');

            // Parse custom fields
            $customFields = json_decode($r->custom_fields);
            $serviceZip4 = "";

            foreach ($customFields as $field) {
                if ($field->product == $r->event_product_id && $field->name = 'zip ext') {
                    $serviceZip4 = $field->value;
                    break;
                }
            }

            // Split out house number and street name
            $streetElems = explode(' ', $r->service_address1);
            $houseNumber = "";
            $streetName = "";

            for ($i = 0; $i < count($streetElems); $i++) {
                if ($i == 0) {
                    $houseNumber .= $streetElems[$i];
                } else if ($i == 1 && $streetElems[$i] == '1/2') {
                    $houseNumber .= " " . $streetElems[$i];
                } else {
                    $streetName = ltrim($streetName) . ' ' . $streetElems[$i];
                }
            }

            // Map data to enrollment CSV file fields.
            $this->info('  Mapping data to CSV file layout...');
            $row = [
                $csvHeader['Product ID'] => strtoupper($r->rate_program_code),
                $csvHeader['Utility DUNS ID'] => strtoupper($r->utility_commodity_external_id),
                $csvHeader['Utility Account Nbr'] => strtoupper($r->account_number1),
                $csvHeader['Customer Type'] => strtoupper($r->market),
                $csvHeader['Name Prefix'] => strtoupper($r->name_prefix),
                $csvHeader['First Name'] => strtoupper($r->bill_first_name),
                $csvHeader['Middle Initial'] => strtoupper($r->bill_middle_name),
                $csvHeader['Last Name'] => strtoupper($r->bill_last_name),
                $csvHeader['Organization Name'] => strtoupper($r->organizaltion_name),
                $csvHeader['Title'] => strtoupper($r->title),
                $csvHeader['Service Street Nbr'] => strtoupper($houseNumber),
                $csvHeader['Service Address'] => strtoupper($streetName),
                $csvHeader['Service City'] => strtoupper($r->service_city),
                $csvHeader['Service State'] => strtoupper($r->service_state),
                $csvHeader['Service Postal Code'] => $r->service_zip,
                $csvHeader['Service Postal Code +4'] => $serviceZip4,
                $csvHeader['County'] => strtoupper($r->service_county),
                $csvHeader['Phone'] => ltrim($r->btn, '+1'),
                $csvHeader['Email'] => strtoupper($r->email_address),
                $csvHeader['HU Request'] => strtoupper($r->hu_request),
                $csvHeader['Bill Model'] => strtoupper($r->rate_source_code),
                $csvHeader['Sales Person Code'] => strtoupper($r->vendor_label),
                $csvHeader['EES Agent'] => strtoupper($r->sales_agent_rep_id),
                $csvHeader['Sales Date'] => $r->event_created_at->format("m/d/y"),
                $csvHeader['Meter Number'] => strtoupper($r->account_number2),
                $csvHeader['Contract Effective Date'] => $r->contract_effective_date,
                $csvHeader['Promo Code (export AA)'] => strtoupper($r->promo_code)
            ];


            // Add this row of data to the correct vendor in the CSV array.
            $csv[$r->vendor_id]['data'][] = $row;
        }

        // Write the CSV files
        foreach ($csv as $key => $vendorData) {
            $this->info('Writing CSV file (' . $csv[$key]['name'] . ')...');

            $filename = 'Park Energy - Enrollments - ' . $csv[$key]['label'] . ' - ' . $this->startDate->format('m-d-Y') . '.csv';
            if ($this->mode == 'test') {
                $filename = 'test_' . $filename;
            }

            $file = fopen(public_path('tmp/' . $filename), 'w');

            $fileHeader = [];
            foreach ($csvHeader as $key => $value) {
                $fileHeader[] = $key;
            }
            fputcsv($file, $fileHeader);

            foreach ($vendorData['data'] as $row) {
                fputcsv($file, $row);
            }
            fclose($file);

            $filesToEmail[] = public_path('tmp/' . $filename);
        }


        // Email the files
        $this->info('Emailing files...');
        $this->sendEmail(
            'Attached ' . (count($filesToEmail) == 1 ? 'is the enrollment file ' : 'are the enrollment files ') . 'for ' . $this->startDate->format('m-d-Y') . '.',
            $this->distroList[$this->mode],
            $filesToEmail
        );

        // Delete temp files
        foreach ($filesToEmail as $file) {
            unlink($file);
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
        if ('production' != config('app.env')) {
            $subject = 'Park Power - Email File Generation (' . config('app.env') . ') '
                . Carbon::now();
        } else {
            $subject = 'Park Power - Email File Generation '
                . Carbon::now();
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
