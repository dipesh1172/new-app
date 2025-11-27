<?php

namespace App\Console\Commands;

use App\Models\EmailAddress;
use App\Models\EmailAddressLookup;
use App\Models\JsonDocument;
use App\Models\PhoneNumber;
use App\Models\PhoneNumberLookup;
use App\Models\Script;
use App\Models\State;
use App\Models\Survey;
use App\Models\Upload;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SurveyImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey:import {--debug} {--state=} {--upload=} {--brand=} {--file=} {--script=} {--dupebtn}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk import survey files';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function finishUp($exitCode, &$errors, $uploadId = null)
    {
        if ($uploadId !== null) {
            $j = new JsonDocument();
            $j->document_type = 'upload-errors';
            $j->ref_id = $uploadId;
            $j->document = ['errors' => $errors];
            $j->save();
        }

        return $exitCode;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('Starting import...');
        Log::info($this->options());

        ini_set('auto_detect_line_endings', true);

        try {
            $uploadId = $this->option('upload');
            $allow_dupe_btn = (config('app.env') !== 'production')
                ? true
                : $this->option('dupebtn');

            $errorList = [];
            $brand_id = $this->option('brand');
            $script = $this->option('script');
            $state = $this->option('state');

            if (!$uploadId) {
                if (!$this->option('brand') || !$this->option('state') || !$this->option('file') || !$this->option('script')) {
                    $this->error('Syntax: php artisan survey:import --file=<path to file> --brand=<brand id> --script=<script_id> --state=<2 initial state>');
                    Log::error(
                        'Syntax: php artisan survey:import --file=<path to file> --brand=<brand id> --script=<script_id> --state=<2 initial state>'
                    );

                    return 30;
                }

                $file = $this->option('file');
            } else {
                Log::info("Using uploaded file ID " . $uploadId);

                $state = $this->option('state');
                $upload = Upload::find($uploadId);
                if (!$upload) {
                    $this->error('Could not find an upload file with this ID.');
                    Log::error('Could not find an upload file with this ID.');
                    return 29;
                }

                $contents = Storage::disk('s3')->get($upload->filename);
                if (strlen(trim($contents)) == 0) {
                    $this->error('Uploaded File Contents were empty.');
                    Log::error('Uploaded File Contents were empty.');
                    return 30;
                }

                $filename = 'survey-import-' . time() . '.csv';
                $file = public_path('tmp/' . $filename);
                $open = fopen($file, 'w');
                fwrite($open, $contents);
                fclose($open);

                $brand_id = $upload->brand_id;
            }

            $state_lookup = State::where(
                'state_abbrev',
                strtoupper($state)
            )->first();
            if (!$state_lookup) {
                $this->error('State: ' . $state . '  was not found.');
                Log::error('State: ' . $state . ' was not found.');
                return 31;
            }

            $script_lookup = Script::where(
                'brand_id',
                $brand_id
            )->where(
                'id',
                $script
            )->first();
            if (!$script_lookup) {
                $this->error('Script: ' . $script . ' was not found for this brand.');
                Log::error('Script: ' . $script . ' was not found.');
                return 32;
            }

            if (file_exists($file)) {
                $handle = fopen($file, 'r');
                $lineNumber = 0;

                if ($this->option('debug')) {
                    while (($data = fgetcsv($handle)) !== false) {
                        print_r($data);
                    }

                    exit();
                }

                //Getting all the headers on one var to avoid futures changes on the doc and more

                $headers = fgetcsv($handle);
                $headers_ = [];
                //Check for weird format on csv
                $weird_format = false;
                if (count($headers) === 1) {
                    //then use delimiter |
                    $weird_format = true;
                    $headers = explode('|', $headers[0]);
                    if (count($headers) === 1) {
                        Log::error('There has been an error reading the headers for the uploaded survey. UploadId: ' . $uploadId);
                        exit;
                    }
                }

                foreach ($headers as $key => $value) {
                    $headers_[trim($value)] = $key;
                }
                $headers = $headers_;

                while (($data = fgetcsv($handle)) !== false) {
                    $lineNumber = ++$lineNumber;
                    if ($weird_format) {
                        $data = explode('|', $data[0]);
                    }

                    if (isset($data[$headers['CREATE_DT']]) && trim($data[$headers['CREATE_DT']]) === 'CREATE_DT') {
                        continue;
                    }

                    $refcode = $data[$headers['CONTR_ID']];
                    $account_number = trim($data[$headers['ESI_ID']]);
                    $email = strtolower(trim($data[$headers['CA_EMAIL_ADDR']]));
                    $first_name = trim($data[$headers['CA_FIRST_NAME']]);
                    $last_name = trim($data[$headers['CA_LAST_NAME']]);
                    $phone = null;
                    if (isset($data[$headers['CA_PHONE_NO']]) && strlen(trim($data[$headers['CA_PHONE_NO']])) > 0) {
                        $phone = trim($data[$headers['CA_PHONE_NO']]);
                        $phone = ($phone[0] == '1') ? '+' . $phone : '+1' . $phone;
                    }
                    $language = (isset($data[$headers['LANGUAGE']]) && trim($data[$headers['LANGUAGE']]) == 'S')
                        ? 2 : 1;

                    $GMECHOICE_REFERRAL_ID = (isset($headers['GMECHOICE_REFERRAL_ID'])) ? $data[$headers['GMECHOICE_REFERRAL_ID']] : null;
                    $SRVC_ADDRESS = (isset($headers['SRVC_ADDRESS'])) ? $data[$headers['SRVC_ADDRESS']] : null;

                    $D2D_AGENCY = (isset($headers['D2D_AGENCY'])) ? $data[$headers['D2D_AGENCY']] : null;
                    $agent_name = null;
                    if (isset($headers['D2D AGENT LAST NAME, FIRST NAME'])) {
                        $agent_name =  $data[$headers['D2D AGENT LAST NAME, FIRST NAME']];
                    } elseif (isset($headers['AGENT_LAST_NAME_FIRST_NAME'])) {
                        $agent_name = $data[$headers['AGENT_LAST_NAME_FIRST_NAME']];
                    }

                    $GMECHOICE_ENROLL_SOURCE = (isset($headers['GMECHOICE_ENROLL_SOURCE'])) ? $data[$headers['GMECHOICE_ENROLL_SOURCE']] : null;
                    $AGENT_VENDOR = (isset($headers['AGENT_VENDOR'])) ? $data[$headers['AGENT_VENDOR']] : null;
                    $CONTR_ACCT_ID = (isset($headers['CONTR_ACCT_ID'])) ? $data[$headers['CONTR_ACCT_ID']] : null;

                    try {
                        $enroll_date = (isset($data[$headers['CREATE_DT']]) && strlen(trim($data[$headers['CREATE_DT']])) > 0)
                            ? Carbon::parse(
                                trim($data[$headers['CREATE_DT']]),
                                'America/Chicago'
                            ) : null;
                    } catch (\InvalidArgumentException $e) {
                        if (isset($data[11])) {
                            Log::error('There was an issue parsing the date: ' . $data[$headers['CREATE_DT']], [['lineNumber' => $lineNumber], $e]);
                            $errorList[] = ['lineNumber' => $lineNumber, 'message' => 'There was an issue parsing the date: ' . $data[$headers['CREATE_DT']]];
                        } else {
                            Log::error('Attempted to parse undefined as a date?');
                        }
                    }

                    if (
                        strlen($first_name) > 0
                        && strlen($last_name) > 0
                        && strlen($phone) == 12
                        && strlen($refcode) > 0
                    ) {
                        $exists = PhoneNumber::where(
                            'phone_numbers.phone_number',
                            $phone
                        )->leftJoin(
                            'phone_number_lookup',
                            'phone_numbers.id',
                            'phone_number_lookup.phone_number_id'
                        )->leftJoin(
                            'surveys',
                            'phone_number_lookup.type_id',
                            'surveys.id'
                        )->where(
                            'phone_number_lookup.phone_number_type_id',
                            6
                        )->where(
                            'surveys.brand_id',
                            $brand_id
                        )->withTrashed()->first();
                        if ($exists && !$allow_dupe_btn) {
                            $this->info($lineNumber . ': BTN (' . $phone . ') exists.');
                            $errorList[] = ['lineNumber' => $lineNumber, 'message' => 'BTN (' . $phone . ') exists'];
                            Log::info($lineNumber . ': BTN (' . $phone . ') exists.');
                        } else {
                            if ($enroll_date->diffInDays(Carbon::now('America/Chicago')) < 30) {
                                $survey = Survey::where(
                                    'refcode',
                                    $refcode
                                )->where(
                                    'brand_id',
                                    $brand_id
                                )->first();
                                // If survey doesn't exist, add it.
                                if (!$survey) {
                                    $survey = new Survey();
                                    $survey->brand_id = $brand_id;
                                    $survey->refcode = $refcode;
                                    $survey->account_number = $account_number;
                                    $survey->customer_first_name = $first_name;
                                    $survey->customer_last_name = $last_name;
                                    $survey->customer_enroll_date = $enroll_date;
                                    $survey->script_id = $script_lookup->id;
                                    $survey->language_id = $language;
                                    $survey->state_id = $state_lookup->id;
                                    $survey->referral_id = $GMECHOICE_REFERRAL_ID;
                                    $survey->srvc_address = $SRVC_ADDRESS;
                                    $survey->agency = $D2D_AGENCY;
                                    $survey->agent_name = $agent_name;
                                    $survey->contr_acct_id = $CONTR_ACCT_ID;
                                    $survey->enroll_source = $GMECHOICE_ENROLL_SOURCE;
                                    $survey->agent_vendor = $AGENT_VENDOR;
                                    $survey->save();

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
                                        }

                                        $pnl = new PhoneNumberLookup();
                                        $pnl->phone_number_type_id = 6;
                                        $pnl->type_id = $survey->id;
                                        $pnl->phone_number_id = $pnid;
                                        $pnl->save();
                                    }

                                    if (isset($email)) {
                                        $exists = EmailAddress::where(
                                            'email_address',
                                            $email
                                        )->withTrashed()->first();
                                        if (!$exists) {
                                            $ea = new EmailAddress();
                                            $ea->email_address = $email;
                                            $ea->save();

                                            $eaid = $ea->id;
                                        } else {
                                            $eaid = $exists->id;
                                        }

                                        $eal = new EmailAddressLookup();
                                        $eal->email_address_type_id = 3;
                                        $eal->type_id = $survey->id;
                                        $eal->email_address_id = $eaid;
                                        $eal->save();
                                    }
                                }
                            } else {
                                $this->info($lineNumber . ': Enroll date (' . $enroll_date . ') was older than 30 days.  Skipping import.');
                                Log::info('Enroll date (' . $enroll_date . ') was older than 30 days.  Skipping import.', ['lineNumber' => $lineNumber]);
                                $errorList[] = ['lineNumber' => $lineNumber, 'message' => 'Enroll date (' . $enroll_date . ') was older than 30 days.  Skipping import.'];
                            }
                        }
                    } else {
                        $this->info($lineNumber . ': First/Last Name, Phone (12 characters), and refcode are required.');
                        Log::info('First/Last Name, Phone (12 characters), and refcode are required.', ['lineNumber' => $lineNumber]);
                        $errorList[] = ['lineNumber' => $lineNumber, 'message' => 'First/Last Name, Phone (12 characters), and refcode are required.'];
                    }
                }
            }

            return $this->finishUp(0, $errorList, $uploadId);
        } catch (\Exception $e) {
            Log::info('Exception running survey import: ', [$e]);
            $errorList[] = ['lineNumber' => null, 'Error during survey import: ' . $e->getMessage()];

            return $this->finishUp(42, $errorList, $uploadId);
        }
    }
}
