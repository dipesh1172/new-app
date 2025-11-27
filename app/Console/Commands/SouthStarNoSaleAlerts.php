<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Mail;

use Carbon\Carbon;
use App\Models\StatsProduct;
use App\Models\Event;
use App\Models\Interaction;

class SouthStarNoSaleAlerts extends Command
{
    /**
     * The name and signature of the console command.
     * --dry-run: Prevents emails from going out, and does not tag interaction records with 'alertSent'.
     * --show-sql: Show sql queries and results in terminal window.
     * --show-email: Dry-run mode will already do this. For regular runs, use this to show email contents in terminal window.
     * --mode [test, live]: Setting this to 'test' will send the email to IT instead of the vendor and/or client.
     *
     * @var string
     */
    protected $signature = 'SouthStar:NoSaleAlerts {--mode=} {--dry-run} {--env=} {--start-date=} {--end-date=} {--show-sql} {--show-email}';

    /**
     * The name of the automated job.
     *
     * @var string
     */
    protected $jobName = 'SouthStar - No Sale Alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends alert emails for each no saled TPV for a given date range.';

    /**
     * The brand IDs that will be searched
     *
     * @var array
     */
    protected $brandId = [
        'staging' => '9c1a1d3f-6edf-4d66-be92-e1905d557811',
        'prod' => '4436027c-39dc-48cb-8b7f-4d55b739c09e'
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
     * Report env: 'staging' or 'prod'.
     *
     * @var string
     */
    protected $env = 'prod'; // prod env by default.

    /**
     * Whether or not to show console messages.
     */
    protected $verbose = false;

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
        $this->verbose = $this->option('verbose');

        $this->startDate = Carbon::now('America/Chicago')->addMinutes(-30);
        $this->endDate = Carbon::now('America/Chicago');

        // Validate mode.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));

                // For test mode, set staging env. Can be overridden via arg.
                if ($this->mode == 'test') {
                    $this->env = 'staging';
                }
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
            // TODO: We're trusting the dates the user is passing. Add validation for:
            // 1) valid dates were provided
            // 2) start date <= end date
            $this->startDate = Carbon::parse($this->option('start-date'), 'America/Chicago');
            $this->endDate = Carbon::parse($this->option('end-date'), 'America/Chicago');
            $this->msg('Custom dates used.');
        }

        $this->msg('Start:   ' . $this->startDate);
        $this->msg('End:     ' . $this->endDate);
        $this->msg('BrandId: ' . $this->brandId[$this->env] . "\n");

        // Get list of confirmation codes to send no sale alert messages for.
        // We're using the 'enrolled' interaction field to tag which
        // records already had an alert email sent.
        $confCodes = Event::select(
            'events.id as event_id',
            'interactions.id as interaction_id',
            'events.confirmation_code',
            'dispositions.brand_label as no_sale_reason_id',
            'dispositions.reason as no_sale_reason'
        )->leftJoin(
            'interactions',
            'events.id',
            'interactions.event_id'
        )->leftJoin(
            'dispositions',
            'interactions.disposition_id',
            'dispositions.id'
        )->where(
            'events.brand_id',
            $this->brandId[$this->env]
        )->where(
            'interactions.created_at',
            '>=',
            $this->startDate
        )->where(
            'interactions.created_at',
            '<=',
            $this->endDate
        )->whereIn(
            'interactions.interaction_type_id',
            [1, 2] // inbound call, outbound call
        )->where(
            'interactions.event_result_id',
            2 // no sale
        )->whereIn(
            'dispositions.brand_label',
            ['000003', '000004', '000005', '000006', '000009', '000011', '000015']
        )->where(
            'interactions.enrolled',
            null
        )->distinct();

        // Show SQL query
        if ($this->option('show-sql')) {
            $this->msg("<fg=blue>SQL:");
            $this->msg($confCodes->toSql() . "\n");
            $this->msg("<fg=blue>BINDINGS:");
            $this->msg(print_r($confCodes->getBindings()));
        }

        $confCodes = $confCodes->get()->toArray();
        $this->msg(count($confCodes) . " record(s) found.\n");

        // Show results
        if ($this->option('show-sql')) {
            $this->msg("<fg=blue>SQL RESULT:");
            $this->msg(print_r($confCodes));
        }

        // Loop through each confirmation code and send alert emails
        foreach ($confCodes as $confCode) {

            $this->msg('-------------------------------------------------------');
            $this->msg("Confirmation code " . $confCode['confirmation_code']);
            $this->msg("Interaction ID " . $confCode['interaction_id'] . "\n");

            $tpv = StatsProduct::select(
                'id',
                'interaction_created_at',
                'channel',
                'confirmation_code',
                'language',
                'vendor_grp_id',
                'auth_first_name',
                'auth_last_name',
                'btn',
                'sales_agent_rep_id',
                'sales_agent_name',
                'service_state',
                'custom_fields'
            )->where(
                'brand_id',
                $this->brandId[$this->env]
            )->where(
                'confirmation_code',
                $confCode['confirmation_code']
            );

            // Show SQL query
            if ($this->option('show-sql')) {
                $this->msg("<fg=blue>SQL:");
                $this->msg($tpv->toSql() . "\n");
                $this->msg("<fg=blue>BINDINGS:");
                $this->msg(print_r($tpv->getBindings()));
            }

            $tpv = $tpv->get()->first();

            // Currently we're only processing these for vendor 15
            if ($tpv->vendor_grp_id != "15") {
                $this->msg("Not vendor 15. Skipping...\n", 1);
                continue;
            }

            // Set distro lists
            // Test mode override for distro is in sendEmail function.
            // This is so that we can show the intenteded distro list in the email message.
            $clientDistro = ['Stacy.Worthy@southstarenergy.com'];
            $vendorDistro = ['SouthStarAcctTeam@dialamerica.com', 'nhill@southstarenergy.com', 'debra.freeman@southstarenergy.com', 'nicole.richardson@southstarenergy.com'];

            if ($this->option('show-sql')) {
                $this->msg("<fg=blue>SQL RESULT:");
                $this->msg(print_r($tpv->toArray()));
            }

            // parse custom fields
            $customFields = json_decode($tpv->custom_fields);

            $callConsentFlag = $this->getCustomValue("call_consent_flag", $customFields);

            // Build vars for email content
            $action = "";
            $priorityNum = "";
            $priorityTxt = "";

            switch ($confCode['no_sale_reason_id']) {
                case '000003': {
                        $action = "Call Right Away";
                        $priorityNum = "1";
                        $priorityTxt = "Top Priority";
                        break;
                    }
                case '000004': {
                        $action = "Listen to Call First; Then Call Back the Applicant";
                        $priorityNum = "2";
                        $priorityTxt = "High Priority";
                        break;
                    }
                case '000005': {
                        $action = "Listen to Call First; Then Call Back the Applicant";
                        $priorityNum = "2";
                        $priorityTxt = "High Priority";
                        break;
                    }
                case '000006': {
                        $action = "Listen to Call First; Then Call Back the Applicant";
                        $priorityNum = "2";
                        $priorityTxt = "High Priority";
                        break;
                    }
                case '000009': {
                        $action = "Call Right Away";
                        $priorityNum = "1";
                        $priorityTxt = "Top Priority";
                        break;
                    }
                case '000011': {
                        $action = "Listen to Call First; Then Call Back the Applicant";
                        $priorityNum = "2";
                        $priorityTxt = "High Priority";
                        break;
                    }
                case '000015': {
                        $action = "Call Right Away";
                        $priorityNum = "1";
                        $priorityTxt = "Top Priority";
                        break;
                    }
            }

            // Send vendor email
            $subject = 'SouthStar TPV No Sale - ' . $confCode['no_sale_reason_id'] . ' - ' . $priorityTxt . ' - ' . $action;

            $message = "A customer was not able to successfully go through TPV.\n"
                . "ACTION: " . $action . "\n\n"
                . "Customer: " . $tpv->auth_first_name . " " . $tpv->auth_last_name . "\n"
                . "Phone: " . trim($tpv->btn, "+1") . "\n"
                . "Permission to Call Back: " . $callConsentFlag . "\n"
                . "Agent ID: " . $tpv->sales_agent_rep_id . " – " . $tpv->sales_agent_name . "\n"
                . "Reason: " . $confCode['no_sale_reason_id'] . " " . $confCode['no_sale_reason'] . "\n\n"
                . "Priority: " . $priorityNum . " – " . $priorityTxt . "\n"
                . "Date/Time: " . $tpv->interaction_created_at->format("m-d-Y H:i:s") . "\n"
                . "Verification #: " . $tpv->confirmation_code . "\n"
                . "Confirmation #: " . $tpv->confirmation_code;

            // For dry-run mode, output to terminal
            if ($this->option('dry-run') || $this->option('show-email')) {
                $this->msg("<fg=blue>VENDOR EMAIL:", 1);
                $this->msg("<fg=blue>SUBJECT:", 2);
                $this->msg($subject . "\n", 2);
                $this->msg("<fg=blue>MESSAGE:", 2);
                $this->msg(str_replace("\n", "\n    ", $message) . "\n", 2);
            }

            if (!$this->option('dry-run')) {
                $this->msg("<fg=magenta>Sending Vendor email...", 1);

                $this->sendEmail($message, $vendorDistro, $subject);
            }

            // Send client alert email
            if ($confCode['no_sale_reason_id'] == '000015') {

                $subject = 'SouthStar – SC – Res – ' . $tpv->service_state . '– ' . $tpv->channel . ' – ' . $tpv->language . ' :: Price Mismatch';

                $message = "A customer was not able to successfully go through TPV because of a misunderstanding related to the price:\n\n"
                    . "Agent ID: " . $tpv->sales_agent_rep_id . " – " . $tpv->sales_agent_name . "\n\n"
                    . "Customer: " . $tpv->auth_first_name . " " . $tpv->auth_last_name . "\n\n"
                    . "Date/Time: " . $tpv->interaction_created_at->format("m-d-Y H:i:s") . "\n"
                    . "Verification #: " . $tpv->confirmation_code;

                if ($this->option('dry-run') || $this->option('show-email')) {
                    $this->msg("<fg=blue>CLIENT EMAIL:", 1);
                    $this->msg("<fg=blue>SUBJECT:", 2);
                    $this->msg($subject . "\n", 2);
                    $this->msg("<fg=blue>MESSAGE:", 2);
                    $this->msg(str_replace("\n", "\n    ", $message) . "\n", 2);
                }

                if (!$this->option('dry-run')) {
                    $this->msg("<fg=magenta>Sending Client email...", 1);

                    $this->sendEmail($message, $clientDistro, $subject);
                }
            }

            // Tag interaction 'enrolled' field with 'alertSent' to prevent the record from
            // being picked up on this job's next run

            // Find the record
            if (!$this->option('dry-run')) {
                $this->msg("<fg=magenta>Updating interaction record:", 1);
                $this->msg("<fg=magenta>Searching for interaction...", 2);

                $interaction = Interaction::where(
                    'id',
                    $confCode['interaction_id']
                )->first();

                // Update it
                if ($interaction) {
                    $this->msg("<fg=magenta>Found! Updating...\n", 2);
                    $interaction->enrolled = 'alertSent';

                    $interaction->save();
                } else {
                    $this->msg("<fg=magenta>Not Found!\n", 2);
                }
            } else {
                $this->msg("<fg=magenta>Dry run. Skipping interaction record udpate.\n", 1);
            }
        }
    }

    /**
     * Display's a console message, but only if in verbose mode.
     */
    public function msg($str, $indent = 0)
    {
        if ($this->verbose) {
            $this->info(str_pad("", $indent * 2, " ", STR_PAD_LEFT) . $str);
        }
    }

    /**
     * Display's a console error message, but only if in verbose mode.
     */
    public function err($str)
    {
        if ($this->verbose) {
            $this->error($str);
        }
    }

    /**
     * Finds a custom field value, by product ID. If there are duplicate fields, the latest version is used.
     */
    private function getCustomValue(string $fieldName, array $fieldList, $productId = null)
    {
        $customkFieldValue = "";
        $lastDateTime = null;

        foreach ($fieldList as $field) {

            if (!$field->output_name) {
                continue;
            }

            if ($field->output_name == $fieldName && $field->product == $productId) {
                if ($lastDateTime == null || $lastDateTime < $field->date) {
                    $customkFieldValue = $field->value;
                    $lastDateTime = $field->date;
                }
            }
        }

        return $customkFieldValue;
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
    public function sendEmail(string $message, array $distro, string $subject)
    {
        $uploadStatus = [];
        $email_address = $distro;
        $files = [];

        // Append timestamp
        $subject .= $subject . ' ' . Carbon::now('America/Chicago');

        // Prepend TEST tag
        if ($this->mode == 'test') {
            $subject = '(TEST) ' . $subject;
        }

        // Override distro list and show original in message if test mode.
        if ($this->mode == 'test') {

            $message = "(TEST Email. Original Distro: " . implode(", ", $email_address) . ")\n\n" . $message;

            $email_address = ['engineering@tpv.com'];
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
