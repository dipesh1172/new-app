<?php

namespace App\Console\Commands\GreenChoice;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Ftp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Aws\S3\S3Client;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client as GuzzleClient;

use App\Models\BrandCustomField;
use App\Models\CustomField;
use App\Models\CustomFieldStorage;
use App\Models\ProviderIntegration;
use App\Models\Interaction;
use App\Models\Event;
use App\Models\Brand;

class WelcomeLetterEmail extends Command
{
    private $brand_id = null;
    private $env = 'staging';
    private $errors = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gce:welcome:email 
        {--debug :  Extra output} 
        {--file= :  Override file name to look for} 
        {--single : Debug option. Only process the first record} 
        {--dryrun : Do everyting except email/text the letter link, FTP the letter files, and save record to DB}
        {--skipduplicates : If this flag is set, skip checking if there is duplicate interaction}
        {--distro=* : Notification email distro list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'GreenChoice Welcome Letter job (emails/SMS)';

    /**
     * FTP settings for uploading PDF copy of letters
     */
    protected $ftpSettings = null;

    /**
     * Http Client
     */
    protected $httpClient = null;

    /**
     * Notification email distro list
     */
    protected $distroList;

    /**
     * Path to blade file.
     */
    protected const LETTER_TEMPLATE_PATH = 'emails.gce.welcome_letters';

    /**
     * Path to PDF files
     */
    protected const PDF_PATH = '/tmp/gce/welcome_letters/';
    
    /**
     * PDF Converter URL
     */
    protected const PDF_CONVERTER_URL = 'https://apiv2.tpvhub.com/api/util/converthtmltopdf';

    /**
     * Job ID. Used to identify a specific run of this job
     */
    protected $jobId = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->env = (config('app.env') === 'production')
            ? 'production'
            : 'staging';

        $this->httpClient = new GuzzleClient(['verify' => false]);

        $this->jobId = Carbon::now()->timestamp; // Use unix timestamp as job ID
    }    

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->mattermostMessage('Job started - ' . Carbon::now('America/Chicago')->format('Y-m-d H:i:s'));

            if ($this->env == 'production')  {
                $this->brand_id = '7b08b19d-32a5-4906-a320-6a2c5d6d6372';                
            } else {
                $this->brand_id = 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e';
            }

            // Get notification distro list from args
            $this->distroList = $this->option('distro');

            // Brand check
            $brand = Brand::find($this->brand_id);

            if (!$brand) {
                $this->error('The Green Choice Energy brand record was not found.');

                $this->mattermostMessage('Unable to find a Brand record for id ' . $this->brand_id . '. Welcome letter file (if any) was not processed.');

                exit -1;
            }

            // Get FTP Credentials
            $pi = $this->providerIntegration($brand->id);
            if (empty($pi)) {
                $this->error("No Provider Integration found.");

                $this->mattermostMessage('No Provider Integration (FTP credentials) found. Welcome letter file (if any) was not processed.');

                exit - 1;
            }

            if ($this->option('debug')) {
                info(print_r($pi->toArray(), true));

                $this->info("Provider Integraion:");
                $this->info(print_r($pi->toArray(), true));
            }

            $config = [
                'hostname' => $pi->hostname,
                'username' => $pi->username,
                'password' => $pi->password,
                'root' => '/Welcome_Email',
                'port' => ($pi->provider_integration_type_id)
                    ? 21 : 22,
                'ssl' => true,
                'passive' => true,
            ];

            if ($this->option('debug')) {
                info(print_r($config, true));

                $this->info("FTP Config:");
                $this->info(print_r($config, true));
            }

            $this->info('Starting download...');
            $this->ftpDownload($config);            

            $this->mattermostMessage('Job Ended - ' . Carbon::now('America/Chicago')->format('Y-m-d H:i:s'));

        } catch (\Exception $e) {
            $this->mattermostMessage(
                "An unhandled exception has occurred.\n\n"
                . "\tLine: " . $e->getLine() . "\n"
                . "\tError: " . $e->getMessage());

            $this->mattermostMessage('Job Ended - ' . Carbon::now('America/Chicago')->format('Y-m-d H:i:s'));
        }
    }

    /**
     * File FTP Download
     *
     * @param array  $config - configuration needed to perform FTP upload
     * @param string $type   - file type
     *
     * @return array
     */
    public function ftpDownload($config)
    {
        $root = (isset($config['root'])) ? trim($config['root']) : '/';
        try {

            // Create FTP adapter
            $adapter = new Ftp(
                [
                    'host' => trim($config['hostname']),
                    'username' => trim($config['username']),
                    'password' => trim($config['password']),
                    'port' => (isset($config['port'])) ? $config['port'] : 21,
                    'root' => $root,
                    'passive' => (isset($config['passive'])) ? $config['passive'] : false,
                    'ssl' => (isset($config['ssl'])) ? trim($config['root']) : false,
                    'timeout' => 20,
                ]
            );

            $filesystem = new Filesystem($adapter);

            // Build file name to look for
            $date = Carbon::today('America/Chicago')->startOfDay()->format('mdY');

            $filename = ($this->option('file'))
                ? $this->option('file')
                : 'file_' . $date . '.csv';

            // Check for file on FTP server.
            $this->info("Checking if file " . $filename . " exists");
            $exists = $filesystem->has($filename);

            if (!$exists) {
                $this->mattermostMessage('No files to process. Expected file, ' . $filename . ', not located on FTP server.');

                return;
            }

            // Read file contents
            $this->info("Reading file contents");
            $contents = $filesystem->read($filename);

            if(!$contents) {
                $this->mattermostMessage('Filename ' . $filename . ' is empty.');

                return;
            }

            // Convert file contents to an array
            $this->info("Converting to a CSV data array");
            $lines = explode(PHP_EOL, utf8_encode($contents));
            $header = null;
            $csv = [];

            foreach ($lines as $line) {
                if (strlen(trim($line)) > 0) {
                    $csv[] = str_getcsv($line);
                }
            }

            if ($this->option('single')) {
                $this->mattermostMessage('--single option set. Only one record will be processed.');
            }
            $this->mattermostMessage('Start processing of file "' . $filename . '" with ' . (count($csv) - 1). ' record(s).');

            // Process data
            $count = 0; // Counts recrds
            $rowCount = 0; // Counts rows, for console output

            $this->info("Begin processing loop");
            foreach ($csv as $row) {
                $rowCount++;

                $this->info("----------------------------------------------------------------------");
                $this->info("[ " . $rowCount . " / " . count($csv) . "]");

                // Skip header row
                if ($header === null) {
                    $this->info("Header row. Skipping");
                    $header = $row;
                    continue;
                }

                $count++;

                $result = $this->processData($rowCount, array_combine($header, $row), $filename);

                if ($result) {
                    $this->info('Success: ' . $row[0]);

                    if ($this->option('single')) {
                        // For Debugging;  Only run 1 row and quit.
                        $this->info(' --single flag was specified.');
                        exit();
                    }
                }

                if ($count > 500) {
                    $this->mattermostMessage('File "' . $filename . '" had more than 500 rows: ' . (count($csv) - 1) . '. Processing was cut off after 500 records.');
                    break;
                }
            }

            $this->mattermostMessage('File "' . $filename . '" processing finished.');

            // Send notification email
            // Email content is variable, depending on whether errors were encountered.
            $this->sendNotificationEmail($filename, $csv);

        } catch (\Exception $e) {

            $this->mattermostMessage(
                "Unhandled exception in function ftpDownload().\n\n"
                . "\tLine: " . $e->getLine() . "\n"
                . "\tError: " . $e->getMessage());
        }

        return;
    }

    /**
     * Process a record from the file
     */
    public function processData($rowNum, $data, $filename): bool
    {
        $event = null;

        $validationResult = $this->validateRecord($data);

        if($validationResult['result'] === 'error') {
            $msg = $validationResult['message'];
            $this->error($msg);

            $this->errors[] = [
                'row' => $rowNum,
                'message' => $validationResult['message'],
                'data' => json_encode($data)
            ];

            return false;
        }

        if ($this->option('debug')) {
            print_r($data);
        }

        $this->info("Confirmation Code: " . $data['confirmation_code']);
        $this->info("Delivery Method: " . $data['delivery_method']);
        info('Starting new data...'); // for Laravel log

        $this->info('Begin DB transaction');
        DB::beginTransaction();

        try {

            // Skip record if an Event with matching confirmation number is not found
            $this->info('  Checking for existing event');

            $event = Event::where(
                'brand_id',
                $this->brand_id
            )->where(
                'confirmation_code',
                trim($data['confirmation_code'])
            )->first();

            if (!$event) {
                $msg = 'Unable to locate Event for confirmation code ' . $data['confirmation_code'];
                $this->info('  -- ' . $msg);
                $this->errors[] = [
                    'row' => $rowNum,
                    'message' => $msg,
                    'data' => json_encode($data)
                ];

                DB::rollBack();

                return false;
            }

            // Skip record if a welcome letter was already sent
            if(!$this->option('skipduplicates')) {
                $interaction = Interaction::where(
                    'event_id',
                    $event->id
                )->where(
                    'interaction_type_id',
                    30 // welcome_letter_no_sp
                )->first();
    
                if($interaction) {
                    $msg = 'A welcome letter for confirmation code ' . $data['confirmation_code'] . ' has been sent previously.';
                    $this->info('  -- ' . $msg);
                    $this->errors[] = [
                        'row' => $rowNum,
                        'message' => $msg,
                        'data' => json_encode($data)
                    ];
    
                    DB::rollBack();
    
                    return false;
                }
            }

            
            $data['language_id'] = $event->language_id;
            $data['brand_id'] = $event->brand_id;

            $letterTemplate = self::LETTER_TEMPLATE_PATH . ".welcome_letter";

            $letterDelivered = false; // To track if email/sms operation succeeded. Used to decide whether some custom fields are populated or not.


            // If dry run, skip welcome letter delivery.
            // Also skips record updates and DB commit.
            if($this->option('dryrun')) {
                $this->info('Dry run. Welcome letter not delivered');

                return false;
            }

            $this->info('  Delivering welcome letter...');
            switch (strtolower($data['delivery_method'])) {
                case 'email':

                    try {
                        $this->info('  -- Email to: ' . $data['email_address']);
                        
                        $subject = "Welcome to Green Choice Energy";

                        Mail::send(
                            'emails.gce.welcome_letters.welcome_letter',
                            [
                                'firstName' => $data['customer_first_name'],
                                'lastName' => $data['customer_last_name'],
                                'languageId' => $data['language_id']
                            ],
                            function ($message) use ($subject, $data) {
                                $message->subject($subject);
                                $message->from('no-reply@tpvhub.com');
                                $message->to(trim($data['email_address']));
                            }
                        );

                        $letterDelivered = true;
                        
                    } catch (\Exception $e) {

                        $this->info('  -- Error delivering welcome letter via email: ' . $e->getMessage());

                        $this->errors[] = [
                            'row' => $rowNum,
                            'message' => 'Error delivering welcome letter via email: ' . $e->getMessage(),
                            'data' => json_encode($data)
                        ];

                        $this->mattermostMessage(
                            "confirmation_code: " . $data['confirmation_code']. ". Error delivering welcome letter via email.\n\n"
                            . "\tLine: " . $e->getLine() . "\n"
                            . "\tError: " . $e->getMessage()
                        );

                        DB::rollBack();

                        return false;
                    }

                    break;
                case 'text':
                        
                    try {

                        $letterDelivered = $this->sendTextMessage($data, $letterTemplate);

                        if(!$letterDelivered) {
                            DB::rollBack();
                        }

                    } catch (\Exception $e) {

                        $this->info('  -- Error delivering welcome letter via text: ' . $e->getMessage());

                        $this->errors[] = [
                            'row' => $rowNum,
                            'message' => 'Error delivering welcome letter via text: ' . $e->getMessage(),
                            'data' => json_encode($data)
                        ];

                        $this->mattermostMessage(
                            "confirmation_code: " . $data['confirmation_code']. ". Error delivering welcome letter via text.\n\n"
                            . "\tLine: " . $e->getLine() . "\n"
                            . "\tError: " . $e->getMessage()
                        );

                        DB::rollBack();

                        return false;                        
                    }

                    break;
            }

            $this->info('Committing DB transaction.');
            DB::commit();

            if($letterDelivered) {
                $this->createInteraction($event->id, $data, $letterTemplate, $filename);
            }

            return true;

        } catch (Exception $e) {
            $this->info('Exception: ' . $e);
            $this->info('Rolling back DB transaction');

            $this->errors[] = [
                'row' => $rowNum, 
                'message' => "Error processing confirmation code '" . $data['confirmation_code'] . "': " . $e->getMessage(),
                'data' => json_encode($data)
            ];

            DB::rollBack();
        }

        $this->info("processData() end reached. Returning false.");
        return false;
    }

    /**
     * Build and send notification email
     */
    private function sendNotificationEmail($filename, $data)
    {
        try {
            
            if(!$this->distroList) {
                $this->mattermostMessage("Empty notification distro list. No-one is receiving the results of importing the file.");
            }

            // Dump error data, if any, to file
            $errorFile = null;

            if(count($this->errors) > 0) {
                $fileParts = pathinfo($filename);
                $errorFile = $fileParts['filename'] . '-errors.csv';

                $fh = fopen(public_path('tmp/') . $errorFile, 'w');

                fputcsv($fh, ['welcome_letter_file_row', 'error', 'welcome_letter_file_data']);
                foreach($this->errors as $error) {
                    fputcsv($fh, $error);
                }
                fclose($fh);
            }

            // Build message
            $message = "<p>Welcome letters file: <strong>" . $filename . "</strong></p>"
                . "<p>Records in file: " . (count($data) -1) . "</p>"
                . "<p>Errors: " . count($this->errors) . "</p>"
                . (count($this->errors) > 0 ? "<p>See attached errors file for details.</p>" : "");

            $data = [
                'subject' => '',
                'content' => $message
            ];

            foreach($this->distroList as $distro) {
                $subject = "Green Choice Energy - Welcome Letters Delivery";

                Mail::send(
                    'emails.generic',
                    $data,
                    function ($message) use ($subject, $distro, $errorFile) {
                        $message->subject($subject);
                        $message->from('no-reply@tpvhub.com');
                        $message->to($distro);

                        if($errorFile) {
                            $message->attach( (public_path('tmp/') . $errorFile) );
                        }
                    }
                );
            }

        } catch (\Exception $e) {
            $this->mattermostMessage(
                "Error sending notification email\n\n"
                . "\tLine: " . $e->getLine()
                . "\tError: " . $e->getMessage()
            );
        }
    }

    /**
     * Validate record from welcome letters file.
     */
    private function validateRecord($data)
    {
        // Check for missing fields
        if (!isset($data['confirmation_code'])) {
            return [
                'result' => 'error',
                'message' => "Missing column: confirmation_code"
            ];
        }

        if (!isset($data['customer_first_name'])) {
            return [
                'result' => 'error',
                'message' => "Missing column: customer_first_name"
            ];
        }

        if (!isset($data['customer_last_name'])) {
            return [
                'result' => 'error',
                'message' => "Missing column: customer_last_name"
            ];
        }

        if (!isset($data['btn'])) {
            return [
                'result' => 'error',
                'message' => "Missing column: btn"
            ];
        }

        if (!isset($data['email_address'])) {
            return [
                'result' => 'error',
                'message' => "Missing column: email_address"
            ];
        }

        if (!isset($data['delivery_method'])) {
            return [
                'result' => 'error',
                'message' => "Missing column: delivery_method"
            ];
        }

        // Check for empty/null fields
        if (!$data['confirmation_code']) {
            return [
                'result' => 'error',
                'message' => "Column cannot be empty: confirmation_code."
            ];
        }

        if (!$data['customer_first_name']) {
            return [
                'result' => 'error',
                'message' => "Column cannot be empty: customer_first_name."
            ];
        }

        if (!$data['customer_last_name']) {
            return [
                'result' => 'error',
                'message' => "Column cannot be empty: customer_last_name."
            ];
        }

        if (!$data['delivery_method']) {
            return [
                'result' => 'error',
                'message' => "Column cannot be empty: delivery_method."
            ];
        }

        if (!in_array(strtolower($data['delivery_method']), ['email', 'text'])) {
            return [
                'result' => 'error',
                'message' => "Invalid value in column delivery_method. Must be either 'email' or 'text'."
            ];
        }

        if(strtolower($data['delivery_method']) == 'email' && !$data['email_address']) {
            return [
                'result' => 'error',
                'message' => "Missing email_address. email_address column cannot be empty when delivery_method is 'email'."
            ];
        }

        if(strtolower($data['delivery_method']) == 'text' && !$data['btn']) {
            return [
                'result' => 'error',
                'message' => "Missing btn. btn column cannot be empty when delivery_method is 'text'."
            ];
        }

        return ['result' => 'success', 'message' => null];
    }

    /**
     * Mattermost message blast to tech-automated-jobs channel
     */
    private function mattermostMessage($message)
    {
        $prepend = '[Green Choice Energy Welcome Letter][' . $this->jobId . ']';

        SendTeamMessage('tech-automated-jobs', $prepend . ' ' . $message);
    }

    /**
     * Lookup ProviderIntegration by brand identifier.
     */
    public function providerIntegration($brand_id)
    {
        $pi = ProviderIntegration::where(
            'brand_id',
            $brand_id
        )->where(
            'provider_integration_type_id',
            7
        )->first();

        if ($pi) {
            if (is_string($pi->notes)) {
                $pi->notes = json_decode($pi->notes, true);
            }

            return $pi;
        }

        return null;
    }

    /**
     * Create Interaction
     */
    public function createInteraction($event_id, $data, $letterTemplate, $filename)
    {
        $interaction = new Interaction();
        
        $interaction->event_id = $event_id;
        $interaction->interaction_type_id = 30; // welcome_email_no_sp
        $interaction->event_source_id = 20; // welcome_email
        $interaction->notes = [
            'letters_file' => $filename,
            'template' => $letterTemplate,
            'delivery_method' => $data['delivery_method']
        ];

        $interaction->save();
    }

    /**
     * sendTextMessage()
     */
    public function sendTextMessage($data, $letterTemplate) 
    {
        // Create PDF and upload it to S3
        $fileName = $this->createPDF($data, $letterTemplate);
        $fileParts = pathinfo($fileName);
                    
        $remoteFileName = $data['brand_id'] . '/' . $fileParts['basename'];
        $mediaUrl = 'https://tpv-twilio-mms.s3.amazonaws.com/' . $remoteFileName;

        $this->uploadS3($fileName, $remoteFileName);

        // Create MMS message, and sent to customer, with PDF attached
        if ($data['language_id'] == 2) {
            $message = '¡Bienvenido a la familia Green Choice Energy! Responde STOP para cancelar tu suscripción.';
        } else {
            $message = 'Welcome to the Green Choice Energy family! Reply STOP to unsubscribe.';
        }
        
        $this->info('  -- SMS to: ' . $data['btn']);

        $ret = SendMMS(
            $data['btn'],
            config('services.twilio.default_number'),
            $message,
            $mediaUrl,
            null,
            $data['brand_id'],
            1
        );

        if (strstr($ret, 'ERROR') !== false) {
            $this->info('  -- Could not send SMS notification. ' . $ret);

            return false;
        } else {
            return true;
        }
    }

    /**
     * uploadS3()
     */
    public function uploadS3($fileName, $remoteFileName)
    {
        $s3Client = new S3Client([
            'version' => '2006-03-01',
            'region'  => config('services.aws.region'),
            'credentials' => [
                'key'    => config('services.aws.key'),
                'secret' => config('services.aws.secret')
            ]
        ]);

        $stream = fopen($fileName, 'r+');

        $s3Client->putObject([
            'Bucket' => 'tpv-twilio-mms',
            'Key' => $remoteFileName,
            'Body' => $stream
        ]);

        fclose($stream);
    }    

    /**
     * Add custom field associated to the event or product
     */
    public function addCustomField($brand_id, $event_id, $name, $value, $product_id = null)
    {
        // Locate custom field
        $cf1 = CustomField::select(
            'custom_fields.id',
            'brand_custom_fields.id as bcf_id'
        )->leftJoin(
            'brand_custom_fields',
            'custom_fields.id',
            'brand_custom_fields.custom_field_id'
        )->where(
            'brand_custom_fields.brand_id',
            $brand_id
        )->where(
            'custom_fields.output_name',
            $name
        )->first();

        if (empty($cf1)) {
            $cf1 = new CustomField();

            $cf1->name = $name;
            $cf1->output_name = $name;
            $cf1->description = $name;
            $cf1->question = ["english" => $name, "spanish" => $name, "choices" => null, "anyDate" => false, "optional" => true];
            $cf1->custom_field_type_id = 5;

            $cf1->save();
        }

        // Locate brand custom field
        if (empty($cf1->bcf_id)) {
            $bcf = CustomField::select(
                'custom_fields.id'
            )->leftJoin(
                'brand_custom_fields',
                'custom_fields.id',
                'brand_custom_fields.custom_field_id'
            )->where(
                'brand_custom_fields.brand_id',
                $brand_id
            );

            if (!empty($product_id)) {
                $bcf = $bcf->where(
                    'brand_custom_fields.associated_with_type',
                    'Product'
                )->where(
                    'brand_custom_fields.associated_with_id',
                    $product_id
                );
            } else {
                $bcf = $bcf->where('brand_custom_fields.associated_with_type', 'Event')
                    ->whereNull('brand_custom_fields.associated_with_id');
            }

            $bcf = $bcf->where(
                'custom_fields.output_name',
                $name
            )->first();
        } else {
            $bcf = BrandCustomField::find($cf1->bcf_id);
        }

        if (empty($bcf)) {
            $bcf = new BrandCustomField();

            if (!empty($product_id)) {
                $bcf->associated_with_type = 'Product';
                $bcf->associated_with_id = $product_id;
            } else {
                $bcf->associated_with_type = 'Event';
            }

            $bcf->custom_field_id = $cf1->id;
            $bcf->brand_id = $brand_id;
            $bcf->save();
        }

        $cfs = new CustomFieldStorage();
        $cfs->custom_field_id = $cf1->id;
        $cfs->value = $value;
        $cfs->event_id = $event_id;

        if (!empty($product_id)) {
            $cfs->product_id = $product_id;
        }

        $cfs->save();
    }

    /**
     * Creates a PDF copy of the renewal letter
     */
    private function createPDF($data, $letterTemplate)
    {
        // File name will use blade template name as base, with the path portion removed
        $filename = 
            public_path(self::PDF_PATH)
            . 'welcome_letter_'
            . $data['confirmation_code']
            . '.pdf';

        $payload = $this->createPdfConverterPayload($data);
        
        $res = $this->httpClient->post(
            self::PDF_CONVERTER_URL,
            [
                'verify' => false,
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($payload),
            ]
        );

        file_put_contents($filename, $res->getBody()->getContents());

        return $filename;
    }

    /**
     * Creates the required payload object to use with the PDF converter API endpoint, including the appropriate letter template.
     */
    private function createPdfConverterPayload($data)
    {
        return [
            "document" => [
                "context" => [
                    "firstName" => $data['customer_first_name'],
                    "lastName" => $data['customer_last_name']
                ],
                "template" => ($data['language_id'] == 2 ? $this->getSpanishTemplate() : $this->getEnglishTemplate())
            ]
        ];
    }

    /**
     * English template for MMS PDF. HTML sized to fit on one PDF page.
     */
    private function getEnglishTemplate()
    {
        return "<div style='font: 10pt Arial, sans-serif;'>"
            . "<p><center><img alt='Green Choice Energy Logo' src='https://tpv-assets.s3.amazonaws.com/gce_logo_alt.png'/></center></p>"
            . "<p><br/><br/>Dear {{ firstName }} {{ lastName }},</p>"
            . "<p>Welcome to the Green Choice Energy family! We are thrilled to have you as a customer and want to express our sincere gratitude for choosing us.</p>"
            . "<p>Here at Green Choice Energy, we are committed to providing you with exceptional services and a seamless experience. We're here to ensure that your experience with us exceeds your expectations.</p>"
            . "<p>To help you get started, here are a few key points to kick off your journey with Green Choice Energy:</p>"
            . "<p><ol><li><strong>Explore Our Products/Services:</strong> Take a moment to browse through our website and discover the range of products/services we offer. "
            . "From planting a tree in your honor or the robust Green Choice energy Rewards, we have something for everyone.</li>"
            . "<li><strong>Stay Connected:</strong> Follow us on social media to stay updated on the latest news. We love connecting with our customers!</li>"
            . "<li><strong>Need Assistance?</strong> Our customer care team is here to assist you. If you have any questions, concerns, or just want to say hello, "
            . "feel free to reach out to us at <a href='tel:800-685-0960'>800-685-0960</a>. We're always happy to help!</li></ol></p>"
            . "<p>We're honored to have you as part of the Green Choice Energy family, we are confident that you'll enjoy your experience with us. "
            . "Thank you for entrusting us with your commitment to sustainable energy. Together, we're making a positive difference for our planet and future generations. "
            . "Your choice to embrace renewable energy is a powerful step toward a cleaner, greener world. We're honored to have you as part of the Green Choice Energy family, "
            . "and we look forward to collectively driving positive change.</p><p>Best regards,</p><p>Green Choice Energy</p></div>";
    }

    /**
     * English template for MMS PDF. HTML sized to fit on one PDF page.
     */
    private function getSpanishTemplate()
    {
        return "<div style='font: 10pt Arial, sans-serif; line-height: 16px'><p><center><img alt='Green Choice Energy Logo' src='https://tpv-assets.s3.amazonaws.com/gce_logo_alt.png'/></center></p>"
            . "<p><br/>Querido/a {{ firstName }} {{ lastName }},</p>"
            . "<p>¡Bienvenido a la familia Green Choice Energy! Estamos encantados de tenerte como cliente y queremos expresarte nuestra más sincera gratitud por elegirnos.</p>"
            . "<p>Aquí en Green Choice Energy, estamos comprometidos a proporcionarte servicios excepcionales y una experiencia sin contratiempos. Estamos aquí para asegurarnos "
            . "de que tu experiencia con nosotros supere tus expectativas.</p>"
            . "<p>Para ayudarte a empezar, aquí tienes algunos puntos clave para iniciar tu viaje con Green Choice Energy:</p>"
            . "<p><ol><li><strong>Explora nuestros productos y servicios:</strong> Tómate un momento para navegar por nuestro sitio web y descubrir la gama de "
            . "productos/servicios que ofrecemos. Desde plantar un árbol en tu honor hasta las robustas Recompensas de Energía Green Choice, tenemos algo para todos.</li>"
            . "<li><strong>Mantente conectado:</strong> Síguenos en las redes sociales para estar al día de las últimas noticias. ¡Nos encanta conectar con nuestros clientes!</li>"
            . "<li><strong>Necesita ayuda?</strong> Nuestro equipo de atención al cliente está aquí para ayudarle. Si tiene alguna pregunta, duda o simplemente quiere saludarnos, no dude en "
            . "ponerse en contacto con nosotros llamando al <a href='tel:800-685-0960'>800-685-0960</a>. Estaremos encantados de ayudarle.</li></ol></p>"
            . "<p>Nos sentimos honrados de tenerte como parte de la familia Green Choice Energy, estamos seguros de que disfrutarás tu experiencia con nosotros. "
            . "Gracias por confiarnos tu compromiso con la energía sostenible. Juntos, estamos marcando una diferencia positiva para nuestro planeta y las generaciones futuras. "
            . "Su decisión de apostar por las energías renovables es un gran paso hacia un mundo más limpio y ecológico. Nos sentimos honrados de que formes parte de la familia "
            . "Green Choice Energy y esperamos impulsar colectivamente un cambio positivo.</p><p>Saludos cordiales,</p><p>Green Choice Energy</p></div>";
    }
}
