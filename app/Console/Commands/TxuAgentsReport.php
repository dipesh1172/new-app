<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\BrandUser;
use App\Models\BrandUserNote;
use App\Models\PhoneNumberLookup;

define("TZ_CST", "America/Chicago");
define("STR_FILEPATH", public_path('tmp/'));

class TxuAgentsReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TXU:AgentsReport {--mode=} {--env=} {--no-email} {--show-sql} {--limit=}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'TXU - Agents Report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'TXU sales agents report';

    /**
     * The brand identifier
     *
     * @var array
     */
    protected $brandId = [
        'staging' => 'cba28472-e721-4c21-9aca-dd7b2dfc06c9',
        'prod' => '200979d8-e0f5-41fb-8aed-e58a91292ca0'
    ];

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'live' => ['dxc_autoemails@tpv.com', 'kelly.welander@txu.com', 'kelsey.bowman@vistraenergy.com', 'Josue.Flores@vistraenergy.com', 'Olga.Muller@vistraenergy.com', 'Alma.Aguilar@txu.com', 'Kim.Stein@vistraenergy.com;'],
        'test' => ['dxc_autoemails@tpv.com', 'engineering@tpv.com'],
        'error' => ['dxc_autoemails@tpv.com', 'alex@tpv.com']
    ];

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
        $dtNow = Carbon::now(TZ_CST);
        $this->info($dtNow);

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

        // Build file name
        $activeAgentsFilename = ($this->mode == "test" ? "TEST -- " : "")
            . "TXU - Agents Report - Active Agents - "
            . $dtNow->format("Ymd-His") . ".csv";

        $inactiveAgentsFilename = ($this->mode == "test" ? "TEST -- " : "")
            . "TXU - Agents Report - Inactive Agents - "
            . $dtNow->format("Ymd-His") . ".csv";


        // We're creating to files, so build two arrays
        $activeAgents = [];
        $inactiveAgents = [];

        // Get agent list
        $agents = $this->getAgents($this->brandId[$this->env]);

        // Format data and preform other lookups
        $count = 0; // For console output
        $totalCount = count($agents);

        foreach ($agents as $agent) {
            $count++;

            $this->info("Formatting data for " . $agent['vendor_id'] . " - " . $agent['first_name'] . " " . $agent['last_name'] . " (" . $agent['brand_users_id'] . ")");

            $this->info("\n" . $count . " / " . $totalCount);


            // Get sales agent notes            
            $notes = $this->getNotes($agent['brand_users_id']);

            // Get sales agent phone numbers
            $phoneNumbers = $this->getPhoneNumbers($agent['users_id']);

            $row = [
                "grp_id" => $agent['vendor_id'],
                "agent_id" => $agent['tsr_id'],
                "tsr_name" => $agent['first_name'] . " " . $agent['last_name'],
                "phone" => $phoneNumbers,
                "updated_by" => "",
                "dt_date" => $agent['updated_at'],
                "dt_added" => $agent['created_at'],
                "active" => ($agent['status'] == 1 ? "Yes" : "No"),
                "tsr_language" => $agent['language'],
                "ssn4" => $agent['ssn'],
                "shirt_size" => $agent['shirt_size'],
                "passed_background_check" => ($agent['pass_bg_chk'] == 1 ? "Yes" : "No"),
                "passed_drug_test" => ($agent['pass_drug_test'] == 1 ? "Yes" : "No"),
                "passed_exam" => ($agent['pass_exam'] == 1 ? "Yes" : "No"),
                "profile_pic_added" => ($agent['avatar'] ? "Yes" : "No"),
                "tsr_notes" => $notes,
                "last_sale_date" => $agent['last_sale_date']
            ];

            // Added to active or inactive agents list (for export) based on status
            if ($agent['status'] == 1) {
                $activeAgents[] = $row;
            } else {
                $inactiveAgents[] = $row;
            }
        }

        // Write acitve agents CSV file.
        $this->info('Writing Active Agents CSV file...');
        $file1Result = $this->writeCsvFile($activeAgents, $activeAgentsFilename);

        $this->info('Writing Inactive Agents CSV file...');
        $file2Result = $this->writeCsvFile($inactiveAgents, $inactiveAgentsFilename);

        // Check results and quit early with error email
        if ($file1Result['result'] != 'success' || $file2Result['result'] != 'success') {
            $message = "Error exporting active and/or inactive agents to CSV!\n\nFiles:";

            if ($file1Result['result'] != 'success') {
                $message .= "Active Agents file:\n"
                    . "Filename: " . $activeAgentsFilename . "\n"
                    . "Error: " . $file1Result['message'];
            }
            if ($file2Result['result'] != 'success') {
                $message .= "Inactive Agents file:\n"
                    . "Filename: " . $inactiveAgentsFilename . "\n"
                    . "Error: " . $file2Result['message'];
            }

            $this->sendEmail($message, $this->distroList['error']);
            return -1;
        }

        if (!$this->option('no-email')) {
            // No errors? Email files to client.
            $this->info("Emailing report...");

            $this->sendEmail(
                "Active/Inactive agents lists attached.",
                $this->distroList[$this->mode],
                [STR_FILEPATH . $activeAgentsFilename, STR_FILEPATH . $inactiveAgentsFilename]
            );

            // Remove files. Only if --no-email is not set.
            if (file_exists(STR_FILEPATH . $activeAgentsFilename)) {
                unlink(STR_FILEPATH . $activeAgentsFilename);
            }
            if (file_exists(STR_FILEPATH . $inactiveAgentsFilename)) {
                unlink(STR_FILEPATH . $inactiveAgentsFilename);
            }
        }
    }

    /**
     * Retrieve list of sales reps.
     */
    private function writeCsvFile($data, $filename)
    {
        try {

            // Open file
            $file = fopen(STR_FILEPATH . $filename, 'w');

            // Data
            $ctr = 0;
            foreach ($data as $row) {
                $ctr++;

                // Write header row
                if ($ctr == 1) {
                    $header = array_keys($row);
                    fputcsv($file, $header);
                }

                fputcsv($file, $row);
            }
            fclose($file);
        } catch (\Exception $e) {
            $this->error("this::writeCsvFile(): " . $e->message);
            return ['result' => 'error', 'message' => $e->message];
        }

        return ['result' => 'success', 'message' => ''];
    }

    /**
     * Retrieve list of sales reps.
     */
    private function getAgents($brandId)
    {
        $this->info("Getting TXU agents list...");

        $agents = BrandUser::select(
            'brand_users.id AS brand_users_id',
            'brand_users.tsr_id',
            'brand_users.created_at',
            'brand_users.updated_at',
            'brand_users.status',
            'brand_users.ssn',
            'brand_users.shirt_size',
            'brand_users.pass_bg_chk',
            'brand_users.pass_drug_test',
            'brand_users.pass_exam',
            'brand_users.last_sale_date',
            'users.id AS users_id',
            'users.avatar',
            'users.first_name',
            'users.last_name',
            'languages.language',
            'vendors.vendor_label AS vendor_id',
            'genders.id',
            'brands.name AS vendor_name'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->leftJoin(
            'vendors',
            function ($join) {
                $join->on('brand_users.employee_of_id', '=', 'vendors.vendor_id');
                $join->on('brand_users.works_for_id', '=', 'vendors.brand_id');
            }
        )->leftJoin(
            'brands',
            'vendors.vendor_id',
            'brands.id'
        )->leftJoin(
            'languages',
            'brand_users.language_id',
            'languages.id'
        )->leftJoin(
            'genders',
            'brand_users.gender_id',
            'genders.id'
        )->where(
            'brand_users.works_for_id',
            $brandId
        )->where(
            'brands.name',
            '!=',
            'TPV.com Test Vendor' // Should have a hard-coded value here, but I don't know any other way to block internal test vendors.
        )->where(
            'role_id',
            3 // Sales agent
        )->withTrashed();

        // Set limit?
        if ($this->option('limit')) {
            $agents->limit($this->option('limit'));
        }
        // Dispaly SQL in console
        if ($this->option('show-sql')) {
            $sql = Str::replaceArray('?', $agents->getBindings(), $agents->toSql());

            $this->info("Get Agents SQL:");
            $this->info($sql);
        }

        $agents = $agents->get()->toArray();

        return $agents;
    }

    /**
     * Retrieve Brand User phone numbers and returns them as a comma separated string
     */
    private function getPhoneNumbers($userId)
    {
        $this->info("Getting phone numbers... ");

        $phones = PhoneNumberLookup::select(
            'phone_numbers.phone_number'
        )->leftJoin(
            'phone_numbers',
            'phone_number_lookup.phone_number_id',
            'phone_numbers.id'
        )->where(
            'phone_number_lookup.phone_number_type_id',
            1 // Specifies that the guid in type_id is User id. Not sure why, since guids are suposed to be globally unique...
        )->where(
            'phone_number_lookup.type_id',
            $userId
        )->get()->toArray();

        $phoneStr = "";
        foreach ($phones as $phone) {
            $phoneStr .= (!empty($phoneStr) ? ", " : "") . ltrim($phone['phone_number'], "+1");
        }

        return $phoneStr;
    }

    /**
     * Retrieve Brand User notes and returns them as a JSON string.
     */
    private function getNotes($brandUserId)
    {
        $this->info("Getting notes...");

        $notes = BrandUserNote::select(
            DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS user"),
            'brand_user_notes.created_at',
            'brand_user_notes.note'
        )->leftJoin(
            'brand_users',
            'brand_user_notes.added_by_brand_user_id',
            'brand_users.id'
        )->leftJoin(
            'users',
            'brand_users.user_id',
            'users.id'
        )->where(
            'brand_user_notes.brand_user_id',
            $brandUserId
        )->get()->toArray();

        $noteStr = json_encode($notes);

        return $noteStr;
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
