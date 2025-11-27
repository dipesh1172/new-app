<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Mail;
use Illuminate\Console\Command;

use Carbon\Carbon;

class GenieAutomatedJobsReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Genie:AutomatedJobsReport {--mode=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends an email listing IDT\'s automated jobs and their respective distro lists';

    /**
     * Report mode: 'live' or 'test'.
     * 
     * @var string
     */
    protected $mode = 'live'; // live mode by default.

    /**
     * Distribution list
     *
     * @var array
     */
    protected $distroList = [
        'live' => ['cguzman@genieretail.com', 'emortiz@genieretail.com'],
        'test' => ['dxcit@tpv.com', 'engineering@tpv.com']
    ];

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
        // Check mode. Leave in 'live' mode if not provided or an invalide value was provided.
        if ($this->option('mode')) {
            if (
                strtolower($this->option('mode')) == 'live' ||
                strtolower($this->option('mode')) == 'test'
            ) {
                $this->mode = strtolower($this->option('mode'));
            }
        }

        // List the jobs
        $jobs = [
            [
                'name' => 'Genie Retail Energy - 18KE Enrollments API',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => [],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - Auto Jobs Report',
                'disabled' => false,
                'frequency' => 'Day 1',
                'distro_1' => ['cguzman@genieretail.com', 'emortiz@genieretail.com'],
                'distro_2' => []
            ], [
                'name' => 'IDT Energy - Commercial Group - Email File Generation',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['tzupnik@genieretail.com', 'SalesSupport@genieretail.com', 'ebramwell@genieretail.com'],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - FTP File Generation',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['processing@genieretail.com', 'cguzman@genieretail.com', 'SalesSupport@genieretail.com'],
                'distro_2' => []
            ], [
                'name' => 'IDT Energy - MD - D2D - Email File Generation',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['processing@genieretail.com', 'cguzman@genieretail.com', 'emortiz@genieretail.com', 'curt@tpv.com'],
                'distro_2' => []
            ], [
                'name' => 'IDT Energy - OH - D2D - Email File Generation',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['processing@genieretail.com', 'cguzman@genieretail.com', 'emortiz@genieretail.com'],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - TLP Rates List Report',
                'disabled' => false,
                'frequency' => 'Hourly',
                'distro_1' => [],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - TM - DTD - Contracts - FTP',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['SalesSupport@GenieRetail.com'],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - TM - DTD - Waves - FTP',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['SalesSupport@GenieRetail.com'],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - Vendor 182 Enrollments API',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => [],
                'distro_2' => []
            ], [
                'name' => 'Genie Retail Energy - Vendor 189 Enrollments API',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => [],
                'distro_2' => []
            ], [
                'name' => 'Residents Energy - DE - D2D Email File Generation',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['SalesSupport@genieretail.com', 'processing@genieretail.com'],
                'distro_2' => []
            ], [
                'name' => 'Residents Energy - OH - D2D Email File Generation',
                'disabled' => false,
                'frequency' => 'Daily',
                'distro_1' => ['processing@genieretail.com'],
                'distro_2' => []
            ],
        ];

        // HTMl Styles
        $tableStyle   = 'border:solid;border-width:1;border-color:#775599';
        $headingStyle = 'font-family:arial;font-size:10pt;background-color:#ddddee;border-bottom:solid;border-width:2';
        $columnStyle  = 'font-family:arial;font-size:10pt;border-left:solid;border-width:1;border-color:#ddddee';
        $rowBgColor1  = '#eeeeff';
        $rowBgColor2  = '#ffffff';

        // HTML Table
        $html = "<table cols=5 bgColor='#ddddee' cellspacing=0 cellpadding=2 style='$tableStyle'>" .
            "  <tr>" .
            "    <th align='right' style='$headingStyle'>Count:&nbsp;&nbsp;&nbsp;</th>" .
            "    <th align='left' style='$headingStyle'>Name:</th>" .
            "    <th align='left' style='$headingStyle'>Disabled:</th>" .
            "    <th align='left' style='$headingStyle'>Frequency:</th>" .
            "    <th align='left' style='$headingStyle'>Distro 1:</th>" .
            "    <th align='left' style='$headingStyle'>Distro 2:</th>" .
            "  </tr>";

        $ctr = 1;
        foreach ($jobs as $job) {

            $distro1 = implode('<br/>', $job['distro_1']);
            $distro2 = implode('<br/>', $job['distro_2']);

            $html .=
                "  <tr bgColor='" . ($ctr % 2 == 0 ? $rowBgColor1 : $rowBgColor2) . "'>" .
                "    <td align='right' width=25 style='$columnStyle'>" . $ctr . "</td>" .
                "    <td width=850 style='$columnStyle'>" . $job['name'] .
                "    <td width=50 style='$columnStyle'>" . $job['disabled'] .
                "    <td width=50 style='$columnStyle'>" . $job['frequency'] .
                "    <td width=250 style='$columnStyle'>" . $distro1 .
                "    <td width=250 style='$columnStyle'>" . $distro2 .
                "  </tr>";

            $ctr++;
        }

        $html .= "</table>";

        // Send email
        $this->info('Emailing report...');
        $this->sendEmail(
            'The following jobs are currently in place for Genie Retail Energy.<br/>' . $html,
            $this->distroList[$this->mode]
        );
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
            $subject = 'Genie Retail Energy - Auto Jobs Report (' . config('app.env') . ') '
                . Carbon::now();
        } else {
            $subject = 'Genie Retail Energy - Auto Jobs Report '
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
}
