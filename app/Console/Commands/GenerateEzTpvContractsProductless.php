<?php

namespace App\Console\Commands;

// Models
use function GuzzleHttp\json_encode;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;
use Twilio\Exceptions\RestException;
use Swift_CharacterReaderFactory_SimpleCharacterReaderFactory;
use PhpOffice\PhpWord\PhpWord;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Traits\S3OperationsTrait;
use App\Traits\FindDualFuelMatchesTrait;
use App\Models\ZipCode;
use App\Models\UtilitySupportedFuel;
use App\Models\UtilityAccountType;
use App\Models\Utility;
use App\Models\Upload;
use App\Models\BrandService;
// Traits
use App\Models\TermsAndCondition;
use App\Models\State;
use App\Models\Script;
use App\Models\Rate;
use App\Models\Product;
use App\Models\PhoneNumber;
use App\Models\JsonDocument;
use App\Models\EztpvJobBatch;
use App\Models\EztpvJob;
use App\Models\EztpvDocument;
use App\Models\EztpvContactError;
use App\Models\Eztpv;
use App\Traits\ExportableTrait;
use App\Traits\DeliverableTrait;
// Illuminate
use App\Models\EventType;
use App\Models\EventProductIdentifier;
use App\Models\EventProduct;
use App\Models\Event;
use App\Models\EmailAddress;
use App\Models\CustomFieldStorage;
use App\Models\Channel;
use App\Models\BrandUser;
use App\Models\BrandEztpvContract;
use App\Models\BrandAuthRelationship;
use App\Models\Brand;
use App\Http\Controllers\SupportController;
use App\Models\Interaction;
use App\Models\StatsProduct;

class GenerateEzTpvContractsProductless extends Command
{
    use FindDualFuelMatchesTrait;
    use S3OperationsTrait;
    use ExportableTrait;
    use DeliverableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eztpv:generateContractsProductless
        {--eztpv_id=} 
        {--confirmation_code=} 
        {--hoursAgo=}
        {--debug}
        {--noEmail} 
        {--noSlack} 
        {--override-lang=}
        {--contract=}
        {--noDelivery} 
        {--override-local}
        {--preview}
        {--unfinished}
        {--windows}
        {--override-brand-copy-email=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate contracts for EzTPVs dispositioned as Good Sale';

    private $deliver_contracts = true;
    private $pdfGen;
    private $pdfMerge;
    private $eztpv;
    private $dbQuery;
    private $formData;
    private $brand_service_types = null;
    private $contract_data = null;

    /**
     * Constant List of IDs for companies with different IDs in Production and Staging Environments
     * - uses Associative (Named) Array, so we know which company the IDs belong to in code
     * - access with self::BRAND_IDS['company_name']
     * 
     * - NOTE: Production and Staging IDs may have same value
     * 
     */
    private const BRAND_IDS = [ 
        'idt_energy'            => ['production' => '77c6df91-8384-45a5-8a17-3d6c67ed78bf', 'staging' => '77c6df91-8384-45a5-8a17-3d6c67ed78bf'],
        'residents_energy'      => ['production' => '0e80edba-dd3f-4761-9b67-3d4a15914adb', 'staging' => '0e80edba-dd3f-4761-9b67-3d4a15914adb'],
        'townsquare_energy'     => ['production' => '872c2c64-9d19-4087-a35a-fb75a48a1d0f', 'staging' => 'dda4ac42-c7b8-4796-8230-9668ad64f261'],
        'browns_energy'         => ['production' => '70a0fd82-3be5-4233-8dd2-1f22b6e7ccb2', 'staging' => '0fd601cc-065a-4198-b283-3233f97fbf0c'],
        'txu_energy'            => ['production' => '200979d8-e0f5-41fb-8aed-e58a91292ca0', 'staging' => 'cba28472-e721-4c21-9aca-dd7b2dfc06c9'],
        'nordic_energy'         => ['production' => '250035df-4e77-465a-8d9a-293bf78b6283', 'staging' => '1e0b3d38-325b-4535-ab63-f428edeb1145'],
        'greenwave_energy'      => ['production' => 'f02ff7b7-cfb0-411f-8ac2-827d5f582ad6', 'staging' => '872c2e82-46b2-4502-aa51-6aabdd1ed495'],
        'sunsea_energy'         => ['production' => 'd43dd474-ff9c-41b7-96bf-fb21d634492a', 'staging' => 'd43dd474-ff9c-41b7-96bf-fb21d634492a'],
        'usge'                  => ['production' => '568bc259-b147-4369-97e5-9a99825d0d7a', 'staging' => 'f9c984dc-a17b-41ed-ad5b-f69f3fef59af'],
        'cleansky_energy_ne'    => ['production' => '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0', 'staging' => '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0'],   // CleanSky North East Brand ID
        'cleansky_energy_tx'    => ['production' => '6d525d22-13f6-4381-88b9-d872e8cdf492', 'staging' => '6d525d22-13f6-4381-88b9-d872e8cdf492'],   // CleanSky Texas Brand ID        
        'ak_test_brand'         => ['production' => 'ef8f307a-8cf9-4b85-bf02-295a8e44ca4a', 'staging' => 'faeb80e2-16ce-431c-bb54-1ade365eec16'],    // Alex K Test Brand
        'median'                => ['production' => '0d7bf748-4d03-4416-8447-6ee10bc6649f', 'staging' => 'f3a7015a-aa8a-430c-88c1-fbf22f91580f'],
        'energy_bpo'            => ['production' => '09dcd3eb-ecc2-4075-9bf1-a8d3bee2a83f', 'staging' => 'c4e9c8d1-a1e7-45a3-ab11-e48452fdcb26'],
        'spark_energy'          => ['production' => '7845a318-09ff-42fa-8072-9b0146b174a5', 'staging' => '6ed156aa-a95d-4df6-905d-df6c56956463', 'staging2' => 'c72feb62-44e7-4c46-9cda-a04bd0c58275']
    ];

    // 200979d8-e0f5-41fb-8aed-e58a91292ca0 belongs to TXU Energy, it was listed as idt_energy specific, IDT has same Uuid for prod and stage
    // EXACTLY what I was afraid of when hard coding uuids like this
    
    // Custom email addresses to be used as 'from' addresses in email messages.
    private const BRAND_EMAILS = [
        'IDT Energy' => 'IDTEnrollment@tpvhub.com',
        'Residents Energy' => 'ResidentsEnrollment@tpvhub.com'
    ];

    /* BRAND_COPY_EMAILS
    - Below, the KEYS such as 'idt_energy' should exactly match BRAND_IDS above
    - When Brand_Service is Enabled, we send a COPY of the Email that has been mailed to the customer to one of these Email Addresses
      so our AM (Account Managers) have a COPY of the exact email that was sent to the customer as an Attachment.  This allows our AM's
      to find the original contract as it was sent to a customer from within the AM's Gmail Box
    - These emails are effectively Distribution Lists maintained in mx.answernet.com as "Smarter Email" and forward the attachments to our AM's
    - Billable Service
    - These Brands will not be billed as this was a feature from VoiceLog: Nordic, CleanSky, and Genie (Genie is IDT, Residents, )
    */

    private const BRAND_COPY_EMAILS = [
        'idt_energy'            => 'GenieContracts@answernet.com',          // IDT Energy is a Genie Brand
        'residents_energy'      => 'GenieContracts@answernet.com',          // Residents Energy is a Genie Brand
        'townsquare_energy'     => 'GenieContracts@answernet.com',          // Townsquare Energy is a Genie Brand
        'cleansky_energy_ne'    => 'CleanSkyContracts@answernet.com',       // Same Mailbox for both CleanSky Northeast and Texas
        'cleansky_energy_tx'    => 'CleanSkyContracts@answernet.com',
        'nordic_energy'         => 'NordicContracts@answernet.com',
        'ak_test_brand'         => 'Damian.McQueen@answernet.com'
    ];

    /**
     * Create a new command instance.
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
        $this->logInfo('Args: ', __METHOD__, __LINE__, $this->options());

        if ($this->option('debug')) {
            DB::enableQueryLog();
        }

        $this->logInfo('Selecting contract(s) to process...', __METHOD__, __LINE__);
        $sales = Eztpv::select('eztpvs.id')
            ->join('events', 'eztpvs.id', 'events.eztpv_id')
            ->where('eztpvs.contract_type', '!=', 3)
            ->where('eztpvs.contract_type', '!=', 0);

        $this->deliver_contracts = !$this->option('noDelivery');

        if (!$this->option('unfinished')) {
            $sales->where('eztpvs.finished', 1);
        }

        // TODO: Alex K - 5/1/22 - Remove this IF when contract genreator updates are opened up to all clients
        if (!$this->option('preview') && !$this->option('confirmation_code')) {
            $sales = $sales->whereNotIn(
                'eztpvs.brand_id',
                ['eb35e952-04fc-42a9-a47d-715a328125c0', '4e65aab8-4dae-48ef-98ee-dd97e16cbce6'] // Indra prod/stage
            );
        }

        // Until this version of the contract generator is opened up for all productless configs, limit to Energy BPO brand IDs only
        $sales = $sales->whereIn(
            'eztpvs.brand_id',
            [self::BRAND_IDS['energy_bpo']['production'], self::BRAND_IDS['energy_bpo']['staging']]
        );

        if (!$this->option('preview')) {
            $this->info('Contract: final');
            $sales->join('interactions', 'events.id', 'interactions.event_id')
                ->where('interactions.event_result_id', 1);
        } else {
            $this->info('Contract: preview');
        }

        // Only process "test" records locally
        switch (config('app.env')) {
            case 'local':
                switch ($this->option('override-local')) {
                    case true:
                        $testing = 0;
                        break;

                    default:
                        $testing = 1;
                        break;
                }
                $sales->where('eztpvs.testing', $testing);
                break;
            default:
                $sales->where('eztpvs.testing', 0);
                break;
        }

        if ($this->option('eztpv_id')) {
            $this->info('Given EZTPV ID');
            $sales = $sales->where(
                'eztpvs.id',
                $this->option('eztpv_id')
            );
        } elseif ($this->option('confirmation_code')) {
            $this->info('Given Confirmation Code');
            $sales = $sales->where(
                'events.confirmation_code',
                $this->option('confirmation_code')
            );
        } else {
            if ($this->option('hoursAgo')) {
                $this->info('Processing up to ' . $this->option('hoursAgo') . ' hours ago');
                $sales = $sales->where(
                    'eztpvs.processed',
                    0
                )->where(
                    'eztpvs.created_at',
                    '>=',
                    Carbon::now()->subHours($this->option('hoursAgo'))
                );
            } else {
                $this->info('Processing up to 36 hours ago (set)');
                $sales = $sales->where(
                    'eztpvs.processed',
                    0
                )->where(
                    'eztpvs.created_at',
                    '>=',
                    Carbon::now()->subHours(36)
                );
            }
        }

        $sales = $sales->whereNull(
            'events.deleted_at'
        )->orderBy('eztpvs.created_at');
        
        $this->logSqlStr('Contract selection query:', $sales, __METHOD__, __LINE__);
        
        $sales = $sales->get();

        if (
            count($sales) > 0
            && !$this->option('preview')
        ) {
            $job = new EztpvJobBatch();
            $job->central_start_time = Carbon::now('America/Chicago');
            $job->batch_data = $sales->toJson();
            $job->save();
            $this->info('Started Batch ' . $job->id);

            $this->logInfo("Contract selection result:", __METHOD__, __LINE__, $sales->toArray());
        }

        if (count($sales) === 0) {
            $this->logInfo("Contract selection result: No records found.", __METHOD__, __LINE__);
            $this->info('No Sales on record');
        }

        foreach ($sales as $sale) {
            $this->logInfo('Processing Eztpv: ' . $sale->id, __METHOD__, __LINE__);
            $this->info('Processing Sale ' . $sale->id);

            // unset stored dbQuery if set
            if (isset($this->dbQuery)) {
                unset($this->dbQuery);
            }

            if (
                count($sales) > 0
                && !$this->option('preview')
            ) {
                $job_form_data = Eztpv::find($sale->id);
                $job_eztpv = new EztpvJob();
                $job_eztpv->eztpv_job_batch_id = $job->id;
                $job_eztpv->eztpv_id = $sale->id;
                $job_eztpv->central_start_time = Carbon::now('America/Chicago');
                $job_eztpv->form_data = $job_form_data->form_data;
                $job_eztpv->save();
                $this->info('Started EzTPV Job ' . $job_eztpv->id);                
            }

            // set eztpv globally to current sale data
            $this->setGlobal($sale->id);

            $this->updateProducts($sale->id);

            if ($this->option('confirmation_code')) {
                $this->info(' (confirmation code: ' . $this->option('confirmation_code') . ')');
            }

            if ($this->option('debug')) {
                $this->info("\n");
            }

            $eztpv = Eztpv::with(
                'signature_customer',
                'signature_agent',
                'event.sales_agent'
            )
                ->where(
                    'eztpvs.id',
                    $sale->id
                )
                ->first();
            if (!isset($eztpv)) {
                $this->info("-- Could not find eztpv record.\n");
                $this->logInfo("Could not find eztpv record.", __METHOD__, __LINE__);
                continue;
            }

            $this->logInfo("Current EZTPV record:", __METHOD__, __LINE__, $eztpv->toArray());

            $event = Event::where('eztpv_id', '=', $eztpv->id)
                ->with([
                    'interactions',
                    // Eager Load the Sales Agent and Brand User (sales_agent.user) to include Soft Deleted Sales Agents (via withTrashed())
                    'sales_agent' => function($q){ $q->withTrashed(); },
                    'sales_agent.user' => function($q){ $q->withTrashed(); }
                ])
                ->first();
            if (!isset($event)) {
                $this->info("-- Could not find event record.\n");
                continue;
            }

            $brandState = State::find($event->brand->state);
            $event->brand->brandState = $brandState;

            $data = json_decode($this->formData, true);
            $data['contracts'] = [];
            $data['finalized_products'] = [];
            $data['sorted_products'] = [];

            if ($this->option('debug')) {
                $this->info("json_decoded data is:\n");
                $this->line(print_r($data, true));
            }

            // pdf contract check
            $check = false;

            // possibly temporary default code
            if (!isset($this->eztpv->contract_type)) {
                $this->eztpv->contract_type = 2;
            }

            if (isset($this->eztpv->contract_type)) {
                switch ($this->eztpv->contract_type) {
                    case 0:
                        if ($this->option('debug')) {
                            $this->info("Contract Type = No Contract\n");
                        }

                        // no contract
                        if (!$this->option('preview')) {
                            $eztpv->processed = 1;
                            $eztpv->save();
                        }

                        if (
                            count($sales) > 0
                            && !$this->option('preview')
                        ) {
                            $job_eztpv_end = EztpvJob::find($job_eztpv->id);
                            $job_eztpv_end->central_end_time = Carbon::now('America/Chicago');
                            $job_eztpv_end->save();
                        }
                        continue 2;
                        break;

                    case 1:
                        if ($this->option('debug')) {
                            $this->info("Contract Type = Summary Contract\n");
                        }

                        // summary contract
                        // email contracts?
                        if (
                            isset($this->eztpv->eztpv_contract_delivery)
                            && 'email' === $this->eztpv->eztpv_contract_delivery
                            && !$this->option('preview')
                        ) {
                            $email = $this->sendCustomerEmail($event->id, $data, $eztpv);
                            if (
                                is_array($email)
                                && 'error_contacting_customer' === $email[0]
                            ) {
                                $this->info("Error contacting customer:\n");
                                $this->info('Eztpv: ' . $eztpv->id . "\n");
                                $this->info('Error: ' . $email[1] . "\n");

                                $job_eztpv_end = EztpvJob::find($job_eztpv->id);
                                $job_eztpv_end->central_end_time = Carbon::now('America/Chicago');
                                $job_eztpv_end->error_code = $email[1];
                                $job_eztpv_end->save();
                                $eztpv->processed = 3;
                                $eztpv->save();
                                continue 2;
                            } else {
                                $eztpv->processed = 1;
                                $eztpv->save();
                            }

                            // If Brand Service is Enabled, then an Email Copy is sent to the Sales Agent
                            $this->sendEmailCopyToAccountManagers($event, $eztpv, $data);
                        }

                        // text contracts?
                        if (
                            isset($this->eztpv->eztpv_contract_delivery)
                            && 'text' === $this->eztpv->eztpv_contract_delivery
                            && !$this->option('preview')
                        ) {
                            $this->sendCustomerText($data, $eztpv);

                            $eztpv->processed = 1;
                            $eztpv->save();
                        }
                        break;

                    case 2:
                        if ($this->option('debug')) {
                            $this->info("Contract Type = Custom Contract\n");
                        }

                        // custom contract
                        if (isset($this->eztpv->eztpv_contract_delivery)) {
                            $check = true;
                        }
                        break;

                    case 3:
                        // placeholder for php@7.3 compatibility
                        // continue 2;
                        continue 2;

                    case 4:
                        if ($this->option('debug')) {
                            $this->info("Contract Type = Signature Page\n");
                        }

                        // signature page contract
                        if (isset($this->eztpv->eztpv_contract_delivery)) {
                            $check = true;
                        }
                        break;
                }
            } else {
                continue;
            }

            if ($check) {
                if ($this->option('debug')) {
                    $this->info("In Stage 1\n");
                }

                if ($this->option('debug')) {
                    $this->info("In Stage 2\n");
                }

                // Check data and parse into individual contracts:

                // create array of products (copy)
                $products = [];

                if (isset($data['updated_products']) && $this->option('debug')) {
                    $this->info('updated_products count is '
                        . count($data['updated_products']) . "\n");
                }

                if (
                    isset($data['updated_products'])
                    && count($data['updated_products']) > 0
                ) {
                    $products = $data['updated_products'];
                } else {
                    $this->info("Preparing email...\n");
                    $this->line(print_r($data, true));
                    $this->logError(
                        'No products found when generating contracts for EzTPV: '
                            . $eztpv->id . ' Data:' . print_r($data, true),
                            __METHOD__, __LINE__
                    );

                    continue;
                }

                if ($this->option('debug')) {
                    $this->info("Products:\n");
                    $this->line(print_r($products, true));
                }

                $eds = EztpvDocument::where(
                    'eztpv_id',
                    $eztpv->id
                )->join(
                    'uploads',
                    'eztpv_documents.uploads_id',
                    'uploads.id'
                )->where(
                    'uploads.upload_type_id',
                    3
                )->get();

                if (isset($eds) && count($eds) > 0) {
                    $this->info('Found ' . count($eds) . ' EzTpvDocuments');
                    foreach ($eds as $doc) {
                        $upload = Upload::find($doc->uploads_id);
                        if ($upload) {
                            // delete from s3
                            $this->s3Delete($upload->filename);

                            $upload->delete();
                        }

                        if ($doc) {
                            $doc->delete();
                        }
                    }
                } else {
                    $this->info('No EztpvDocuments found');
                }

                switch ($eztpv->version) {
                    case 1:
                        $this->info('Processing for EZTPV v1');
                        $dualchk = $this->find_dual_fuel_matches($products);
                        $this->logDebug('Dual Check is ' . json_encode($dualchk), __METHOD__, __LINE__);

                        if ($this->option('debug')) {
                            $this->info('Dual Check is ' . json_encode($dualchk) . "\n");
                        }

                        if ($dualchk['result']) {
                            foreach ($dualchk['results'] as $result) {
                                $data = $this->doDual($event, $result, $data);
                            }
                        }

                        $this->logDebug('Products is now ' . json_encode($data['products']), __METHOD__, __LINE__);

                        if ($this->option('debug')) {
                            $this->info('Products is now ' . json_encode($data['products']) . "\n");
                        }

                        foreach ($data['updated_products'] as $product) {
                            $this->logDebug('Adding ' . json_encode($product), __METHOD__, __LINE__);
                            $data['finalized_products'][][] = $product;
                        }
                        break;

                    case 2:
                        $this->info('Processing for EZTPV v2');
                        $finalized_products = [];
                        if (
                            '4e65aab8-4dae-48ef-98ee-dd97e16cbce6' == $event->brand_id
                            || 'eb35e952-04fc-42a9-a47d-715a328125c0' == $event->brand_id
                        ) {
                            // Indra Energy
                            // Process dual-fuel as individual contracts
                            $this->info('Special Handling for Indra Energy (1)');
                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                                if (count($data['finalized_products']) > 1) {
                                    $sigPageCommodity = 'dualsingles';
                                }
                            }
                        } elseif (
                            '6629c585-6cf0-4cdb-8bf9-e76b8a4a2bf5' == $event->brand_id // prod
                            || '0eacb864-933f-4dfe-b2bb-8de4c5936f18' == $event->brand_id // staging
                        ) {
                            // Columbia utilities
                            // Process dual-fuel as individual contracts
                            $this->info('Special handling for Columbia Utilities (1)');
                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                                if (count($data['finalized_products']) > 1) {
                                    $sigPageCommodity = 'dualsingles';
                                }
                            }
                        } elseif (
                            'c03d58ed-1bb0-4e35-9e11-94c1e3bd59cc' == $event->brand_id
                        ) {
                            // Clearview Energy
                            // Process dual-fuel as individual contracts
                            $this->info('Special handling for Clearview Energy (1)');
                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                                if (count($data['finalized_products']) > 1) {
                                    $sigPageCommodity = 'dualsingles';
                                }
                            }
                        } elseif (
                            'd758c445-6144-4b9c-b683-717aadec83aa' == $event->brand_id
                            || '1f402ff3-dace-4aea-a6b2-a96bbdf82fee' == $event->brand_id
                        ) {
                            // Spring Energy
                            // Process dual-fuel as individual contracts
                            $this->info('Special handling for Spring Energy (1)');
                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                                if (count($data['finalized_products']) > 1) {
                                    $sigPageCommodity = 'dualsingles';
                                }
                            }
                        } elseif (
                            '363ef739-3f2c-4a18-9221-46d46c869eb9' == $event->brand_id
                            || '293c51ca-87de-41c6-bb98-948c7537bc11' == $event->brand_id
                        ) {
                            // Atlantic Energy
                            // as normal, except duals can be different rate types, as Atlantic doesn't select contracts by rate_type_id
                            $this->info('Special Handling for Atlantic Energy (1)');
                            $remove_keys = [];
                            for ($i = 0; $i < count($data['updated_products']); ++$i) {
                                $finalized_products = [];
                                if (!empty($data['updated_products'][$i]['linked_to'])) {
                                    $sigPageCommodity = 'dual';
                                    $column = array_column($data['updated_products'], 'id');
                                    $key2 = array_search($data['updated_products'][$i]['linked_to'], $column);
                                    if (isset($key2) && strlen(trim($key2)) > 0) {
                                        $finalized_products[] = $data['updated_products'][$key2];
                                        $finalized_products[] = $data['updated_products'][$i];
                                        $data['finalized_products'][] = $finalized_products;

                                        $remove_keys[] = $i;
                                        $remove_keys[] = $key2;
                                    }
                                }
                            }

                            if ($this->option('debug')) {
                                $this->info('after first loop $data is now: ' . print_r($data, true));
                            }

                            foreach ($remove_keys as $rk) {
                                unset($data['updated_products'][$rk]);
                            }

                            if ($this->option('debug')) {
                                $this->info('after remove loop $data is now: ' . print_r($data, true));
                            }

                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                            }

                            if ($this->option('debug')) {
                                $this->info('after last loop $data is now: ' . print_r($data, true));
                            }
                        } elseif (
                            $this->eztpv->contract_type == 4
                        ) {
                            // Process dual-fuel as individual contracts
                            $this->info('Special Handling for contract type 4 (dual as individual)');
                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                            }

                            if (count($data['finalized_products']) > 1) {
                                if (
                                    $data['finalized_products'][0][0]['event_type_id'] !== $data['finalized_products'][1][0]['event_type_id']
                                ) {
                                    if (
                                        (
                                            self::BRAND_IDS['nordic_energy']['production'] == $event->brand_id
                                            || self::BRAND_IDS['nordic_energy']['staging'] == $event->brand_id
                                        )
                                        &&
                                        (
                                            empty($data['finalized_products'][0][0]['linked_to'])  &&
                                            empty($data['finalized_products'][1][0]['linked_to'])
                                        )

                                    ) {
                                        $sigPageCommodity = 'dualsingles';
                                    } else{
                                        $sigPageCommodity = 'dual';
                                    }

                                } else {
                                    $sigPageCommodity = 'dualsingles';
                                }
                            } else {
                                $sigPageCommodity = null;
                            }

                            $this->logInfo('sigPageCommodity = ' . $sigPageCommodity, __METHOD__, __LINE__);
                        } else {
                            // All other brands
                            // foreach ($products as $key => $product) {
                            //     foreach ($products as $subkey => $prod) {
                            //         if ($product['id'] === $prod['linked_to']) {
                            //             $finalized_products[] = $product;
                            //             $finalized_products[] = $prod;
                            //             unset($data['updated_products'][$key]);
                            //             unset($data['updated_products'][$subkey]);
                            //             $data['finalized_products'][] = $finalized_products;
                            //         }
                            //     }
                            // }
                            // foreach ($data['updated_products'] as $key => $product)
                            // {
                            //     $data['finalized_products'][][] = $product;
                            //     unset($data['updated_products'][$key]);
                            // }
                            $this->info('Processing for All Brands');
                            $remove_keys = [];
                            for ($i = 0; $i < count($data['updated_products']); ++$i) {
                                $finalized_products = [];
                                if (!empty($data['updated_products'][$i]['linked_to'])) {
                                    $sigPageCommodity = 'dual';
                                    $column = array_column($data['updated_products'], 'id');
                                    $key2 = array_search($data['updated_products'][$i]['linked_to'], $column);
                                    if (isset($key2) && strlen(trim($key2)) > 0) {
                                        if (
                                            isset($data['updated_products'][$key2]['rate_type_id'])
                                            && isset($data['updated_products'][$i]['rate_type_id'])
                                            && $data['updated_products'][$key2]['rate_type_id']
                                            == $data['updated_products'][$i]['rate_type_id']
                                        ) {
                                            $finalized_products[] = $data['updated_products'][$key2];
                                            $finalized_products[] = $data['updated_products'][$i];
                                            $data['finalized_products'][] = $finalized_products;
                                        } else {
                                            // rate_type_ids are different, process products as individual contracts
                                            $data['finalized_products'][][] = $data['updated_products'][$key2];
                                            $data['finalized_products'][][] = $data['updated_products'][$i];
                                        }

                                        $remove_keys[] = $i;
                                        $remove_keys[] = $key2;
                                    }
                                }
                            }

                            foreach ($remove_keys as $rk) {
                                unset($data['updated_products'][$rk]);
                            }

                            foreach ($data['updated_products'] as $key => $product) {
                                $data['finalized_products'][][] = $product;
                                unset($data['updated_products'][$key]);
                            }
                        }
                        break;
                }

                // returns array of (filepath+filename of temporary pdf)
                if (
                    4 == $this->eztpv->contract_type
                ) {
                    // signature page contracts
                    if (!isset($sigPageCommodity)) {
                        switch ($data['finalized_products'][0][0]['event_type_id']) {
                            case '1':
                            case 1:
                                $sigPageCommodity = 'electric';
                                break;

                            case '2':
                            case 2:
                                $sigPageCommodity = 'gas';
                                break;
                        }
                    }
                    $filenames = $this->generateSignaturePageContract($eztpv, $event, $data, $sigPageCommodity);
                } else {
                    // all other brands
                    if ($this->option('debug')) {
                        $this->info(
                            '(1) calling generate_contract_document with '
                                . print_r(
                                    [
                                        'eztpv' => $eztpv->toArray(),
                                        'data' => $data,
                                        'contract_id' => null
                                    ],
                                    true
                                )
                        );
                    }

                    $filenames = $this->generate_contract_document($eztpv, $data);
                }

                if ((is_array($filenames) && 'error' === $filenames[0])
                    || !$filenames
                ) {
                    if (
                        is_array($filenames)
                        && 'error' === $filenames[0]
                    ) {
                        if (!$this->option('preview')) {
                            $job_eztpv_end = EztpvJob::find($job_eztpv->id);
                            $job_eztpv_end->central_end_time = Carbon::now('America/Chicago');
                            $job_eztpv_end->error_code = 'filenames returned error: ' . $filenames[1];
                            $job_eztpv_end->save();
                            $eztpv->processed = 3;
                            $eztpv->save();
                        }
                        $step = $filenames[1];
                        $additional = isset($filenames[2]) ? $filenames[2] : null;
                        $this->info('Error: ' . $step . (!empty($additional) ? ' Additionally: ' . json_encode($additional) : ''));
                    }

                    if (!$filenames) {
                        if (!$this->option('preview')) {
                            $job_eztpv_end = EztpvJob::find($job_eztpv->id);
                            $job_eztpv_end->central_end_time = Carbon::now('America/Chicago');
                            $job_eztpv_end->error_code = 'filenames returned false';
                            $job_eztpv_end->save();
                            $eztpv->processed = 3;

                            $this->formData = json_encode($data);
                        }
                        $step = 'filenames returned false';
                        $this->info('Error: filenames returned false');
                    }
                    $eztpv->save();

                    if (!$this->option('noEmail')) {
                        $step = isset($step) ? $step : 'After Contract Generation - filenames error or false';
                        $this->sendErrorEmail($step, $eztpv->id, $event->confirmation_code, $data, $additional);
                    }

                    continue;
                } else {
                    unset($data['contracts']);
                    foreach ($filenames as $filename) {
                        $this->logInfo('Temporary PDF filename check:: ' . print_r($filename, true), __METHOD__, __LINE__);
                        if ('skipped' == $filename['file']) {
                            continue;
                        } else {
                            $data['contracts'][] = [
                                'file' => $filename['file'],
                                'pdf_info_id' => $filename['pdf_info_id'],
                            ];
                        }
                    }

                    if (isset($data['contracts']) && count($data['contracts']) > 0) {
                        $this->formData = json_encode($data);

                        if (isset($eztpv->signature_customer)) {
                            $customer_signature = $eztpv->signature_customer->signature;
                        } elseif (isset($eztpv->signature)) {
                            $customer_signature = $eztpv->signature;
                        } else {
                            $customer_signature = null;
                        }
                        if (isset($eztpv->signature_agent)) {
                            $agent_signature = $eztpv->signature_agent->signature;
                        } elseif (isset($eztpv->signature2)) {
                            $agent_signature = $eztpv->signature2;
                        } else {
                            $agent_signature = null;
                        }

                        $contract_complete = $this->complete_contract_document(
                            $eztpv,
                            $data,
                            $event->id,
                            $customer_signature,
                            $agent_signature
                        );

                        if (false == $contract_complete) {
                            $step = 'After Contract Completion - contract_complete returned false';
                            $this->info('Error: ' . $step);

                            if (!$this->option('noEmail')) {
                                $this->sendErrorEmail($step, $eztpv->id, $event->confirmation_code, $data);
                            }
                            $complete = false;
                        } elseif (
                            is_array($contract_complete)
                            && 'error_contacting_customer' === $contract_complete[0]
                        ) {
                            $this->info("Error contacting customer:\n");
                            $this->info('Eztpv: ' . $eztpv->id . "\n");
                            $this->info('Error: ' . $contract_complete[1] . "\n");

                            $complete = false;
                        } elseif (
                            is_array($contract_complete)
                            && 'signature_error' === $contract_complete[0]
                        ) {
                            $step = $contract_complete[1];
                            $this->info('Error (signature): ' . $step);

                            if (!$this->option('noEmail')) {
                                $this->sendErrorEmail($step, $eztpv->id, $event->confirmation_code, $data);
                            }

                            $complete = false;
                        } elseif (
                            is_array($contract_complete)
                            && 'sigfile_customer_error' === $contract_complete[0]
                        ) {
                            $step = $contract_complete[1];
                            $this->info('Error (sigfile_customer): ' . $step);
                            if (!$this->option('noEmail')) {

                                $this->sendErrorEmail($step, $eztpv->id, $event->confirmation_code, $data);
                            }

                            $complete = false;
                        } elseif (
                            is_array($contract_complete)
                            && 's3_error' === $contract_complete[0]
                        ) {
                            $step = $contract_complete[1] . '<br>S3 Exception: ' . $contract_complete[2];
                            $this->info('Error (signature): ' . str_replace('<br>', ' ', $step));

                            if (!$this->option('noEmail')) {
                                $this->sendErrorEmail($step, $eztpv->id, $event->confirmation_code, $data);
                            }

                            $complete = false;
                        } elseif (
                            is_array($contract_complete)
                            && $contract_complete[0] === 'preview'
                            && $this->option('preview')
                        ) {
                            $this->logInfo('Preview contract info sent to webhook:', __METHOD__, __LINE__, $contract_complete['files']);

                            // delete old json_documents by eztpv_id
                            $toDeleteJson = JsonDocument::where(
                                'ref_id',
                                $eztpv->id
                            )
                                ->forceDelete();

                            $json = new JsonDocument();
                            $json->document_type = 'eztpv-preview';
                            $json->document = $contract_complete['files'];
                            $json->ref_id = $eztpv->id;
                            $json->save();

                            return;
                        } else {
                            $complete = true;
                        }

                        switch ($complete) {
                            case true:
                                $this->logInfo("Contract successfully processed. Setting 'processed' to true (if not preview mode)", __METHOD__, __LINE__);
                                
                                if (!$this->option('preview')) {
                                    $eztpv->processed = 1;
                                    $eztpv->save();
                                }

                                if ($this->option('preview')) {
                                    // return contract url via api response
                                }
                                break;

                            case false:
                                $this->logError("Contract was NOT successfully processed. Error: " . $contract_complete[1], __METHOD__, __LINE__);

                                $this->info('Error: ' . $contract_complete[1]);
                                if (!$this->option('preview')) {
                                    $job_eztpv_end = EztpvJob::find($job_eztpv->id);
                                    $job_eztpv_end->central_end_time = Carbon::now('America/Chicago');
                                    $job_eztpv_end->error_code = $contract_complete[1];
                                    $job_eztpv_end->save();
                                    $eztpv->processed = 3;
                                    $eztpv->save();
                                }
                                continue 2;
                                break;
                        }
                    }
                }

                if ($this->option('debug')) {
                    $this->info("Filenames:\n");
                    $this->line(print_r($filenames, true));

                    $this->info('Success!');
                    $this->info("Contracts:\n");
                    foreach ($filenames as $key => $value) {
                        $this->info(config('services.aws.cloudfront.domain') . '/' . $value['file']);
                    }
                }
            } else {
                $this->info('PDF Contracts not enabled');
            }
            if (
                count($sales) > 0
                && !$this->option('preview')
            ) {
                $job_eztpv_end = EztpvJob::find($job_eztpv->id);
                $job_eztpv_end->central_end_time = Carbon::now('America/Chicago');
                $job_eztpv_end->save();
            }

            // unset stored dbQuery if set
            if (isset($this->dbQuery)) {
                unset($this->dbQuery);
            }
        }

        if (
            count($sales) > 0
            && !$this->option('preview')
        ) {
            $job_end = EztpvJobBatch::find($job->id);
            $job_end->central_end_time = Carbon::now('America/Chicago');
            $job_end->save();
        }
    }

    private function updateProducts($eztpv_id)
    {
        if ($this->option('debug')) {
            $this->logInfo('Updating products for eztpv: ' . $eztpv_id, __METHOD__, __LINE__);
        }

        $eztpv = $this->eztpv;

        // REVISED CODE - Straight forward simple join to replace "$eps = EventProduct::whereHas(" due to subquery that is EXTREMELY slow
        $eps = EventProduct::join('events','event_product.event_id', 'events.id')
            ->select('events.brand_id', 'event_product.*')
            ->where('events.eztpv_id', $eztpv_id)
            ->with(
                'event.channel',
                'addresses',
                'identifiers',
                'identifiers.utility_account_type',
                'home_type',
                'market',
                'customFields.customField',
                'promotion'
            )->orderBy('event_product.created_at');

        // echo "EventProduct Query:\n" . $eps->toSql() . "\nBindings:\n" . print_r($eps->getBindings(),true);

        $eps = $eps->get()->toArray();

        if ($eps) {
            $data['updated_products'] = [];

            foreach ($eps as $ep) {
                $product = [];

                $account_number = null;
                $utility_account_type_id = null;
                $account_number2 = null;
                $utility_account_type_id2 = null;
                $name_key = null;


                $identifiers = $ep['identifiers'];
                for ($x = 0; $x < count($identifiers); ++$x) {
                    $uan_type_id = 0;

                    if (!empty($identifiers[$x]['utility_account_number_type_id'])) {
                        $uan_type_id = $identifiers[$x]['utility_account_number_type_id'];
                    } else if (!empty($identifiers[$x]['utility_account_type']['utility_account_number_type_id'])) {
                        $uan_type_id = $identifiers[$x]['utility_account_type']['utility_account_number_type_id'];
                    }

                    if (empty($uan_type_id)) {
                        switch ($identifiers[$x]['utility_account_type']['id']) {
                            case 7:
                            case 8:
                                if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                    $account_number2 = $identifiers[$x]['identifier'];
                                    $utility_account_type_id2 = $identifiers[$x]['utility_account_type']['account_type'];
                                }
                                break;
                            case 9:
                                if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                    $name_key = $identifiers[$x]['identifier'];
                                }
                                break;
                            default:
                                if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                    $account_number = $identifiers[$x]['identifier'];
                                    $utility_account_type_id = $identifiers[$x]['utility_account_type']['account_type'];
                                }
                                break;
                        }
                    } else {
                        switch ($uan_type_id) {
                            case 2:
                                if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                    $account_number2 = $identifiers[$x]['identifier'];
                                    $utility_account_type_id2 = $identifiers[$x]['utility_account_type']['account_type'];
                                }
                                break;
                            case 3:
                                if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                    $name_key = $identifiers[$x]['identifier'];
                                }
                                break;
                            case 1:
                            default:
                                if (strlen(trim($identifiers[$x]['identifier'])) > 0) {
                                    $account_number = $identifiers[$x]['identifier'];
                                    $utility_account_type_id = $identifiers[$x]['utility_account_type']['account_type'];
                                }
                                break;
                        }
                    }
                }

                $product['id'] = $ep['id'];
                $product['account_number'] = $account_number;
                $product['account_number2'] = $account_number2;
                $product['name_key'] = $name_key;
                $product['utility_account_type_id'] = $utility_account_type_id;
                $product['utility_account_type_id2'] = $utility_account_type_id2;
                $product['home_type_id'] = $ep['home_type']['home_type'];
                $product['market_id'] = $ep['market_id'];

                $address = $this->getAddresses($ep);
                if (isset($address)) {
                    if (isset($address['billing'])) {
                        if (isset($address['billing']['address'])) {
                            $product['billing_address1'] = $address['billing']['address'];
                        }

                        if (isset($address['billing']['address'])) {
                            $product['billing_address2'] = $address['billing']['address2'];
                        }

                        if (isset($address['billing']['city'])) {
                            $product['billing_city'] = $address['billing']['city'];
                        }

                        if (isset($address['billing']['state'])) {
                            $product['billing_state'] = $address['billing']['state'];
                        }

                        if (isset($address['billing']['zip'])) {
                            $product['billing_zip'] = $address['billing']['zip'];
                        }

                        if (isset($address['billing']['county'])) {
                            $product['billing_county'] = $address['billing']['county'];
                        }

                        if (isset($address['billing']['country'])) {
                            $product['billing_country'] = $address['billing']['country'];
                        }
                    }

                    if (isset($address['service'])) {
                        if (isset($address['service']['address'])) {
                            $product['service_address1'] = $address['service']['address'];
                        }

                        if (isset($address['service']['address'])) {
                            $product['service_address2'] = $address['service']['address2'];
                        }

                        if (isset($address['service']['city'])) {
                            $product['service_city'] = $address['service']['city'];
                        }

                        if (isset($address['service']['state'])) {
                            $product['service_state'] = $address['service']['state'];
                        }

                        if (isset($address['service']['zip'])) {
                            $product['service_zip'] = $address['service']['zip'];
                        }

                        if (isset($address['service']['county'])) {
                            $product['service_county'] = $address['service']['county'];
                        }

                        if (isset($address['service']['country'])) {
                            $product['service_country'] = $address['service']['country'];
                        }
                    }
                }

                // Special logic for IDT IL DTD and Residents IL DTD.
                // Remove any gas accounts collected.
                // In IL IDTE/Residents agents use paper contracts for natural gas, so we don't need to generate a PDF contract for those accounts.
                // We're using this hard-coded logic as Genie still wants the IL DTD contract setting in EZTPV config to be honored for electric accounts.
                // In the case of dual fuel, we'll end up creating a contract only for the electric account.
                if(
                    (
                        $ep['brand_id'] == self::BRAND_IDS['idt_energy']['production']
                        || $ep['brand_id'] == self::BRAND_IDS['idt_energy']['staging']
                        || $ep['brand_id'] == self::BRAND_IDS['residents_energy']['production']
                        || $ep['brand_id'] == self::BRAND_IDS['residents_energy']['staging']
                    )
                    && isset($product['service_state'])
                    && strtolower($product['service_state']) == 'il'
                    && strtolower($ep['event']['channel']['channel']) == 'dtd'
                    && $ep['event_type_id'] == 2
                ) {
                    continue;
                }

                $product['rate_id'] = $ep['rate_id'];

                $product['event_type_id'] = $ep['event_type_id'];
                $product['business_name'] = $ep['company_name'];
                $product['auth_first_name'] = $ep['auth_first_name'];
                $product['auth_middle_name'] = isset($ep['auth_middle_name'])
                    ? $ep['auth_middle_name'] : null;
                $product['auth_last_name'] = $ep['auth_last_name'];
                $product['bill_first_name'] = $ep['bill_first_name'];
                $product['bill_middle_name'] = isset($ep['bill_middle_name'])
                    ? $ep['bill_middle_name'] : null;
                $product['bill_last_name'] = $ep['bill_last_name'];
                $product['auth_relationship'] = $ep['auth_relationship'];
                $product['linked_to'] = isset($ep['linked_to'])
                    ? $ep['linked_to'] : null;

                switch ($ep['event_type_id']) {
                    case 1:
                        $commodity = 'electric';
                        break;
                    case 2:
                        $commodity = 'gas';
                        break;
                    case 4:
                        $commodity = 'dual';
                        break;
                }

                // start toc
                $toc = TermsAndCondition::select(
                    'terms_and_conditions.toc'
                )->where(
                    'brand_id',
                    $this->eztpv->brand_id
                )->where(
                    'language_id',
                    $ep['event']['language_id']
                )->where(
                    'commodity',
                    $commodity
                );

                if ($ep['event']['channel']['channel']) {
                    $toc = $toc->where(
                        'channels',
                        'LIKE',
                        '%' . mb_strtoupper($ep['event']['channel']['channel']) . '%'
                    );
                }

                if ($ep['market']['market']) {
                    $toc = $toc->where(
                        'markets',
                        'LIKE',
                        '%' . mb_strtoupper($ep['market']['market']) . '%'
                    );
                }

                if ($product['service_state']) {
                    $toc = $toc->where(
                        'states',
                        $product['service_state']
                    );
                }

                // $this->logInfo($product);
                if (
                    isset($rateInfo)
                ) {
                    if (!empty($rateInfo->green_percentage)) {
                        if ($this->option('debug')) {
                            $this->info('Limiting TOC results to green: ' . $rateInfo->green_percentage);
                        }
                        $toc = $toc->where(
                            'green_product',
                            1
                        );
                    } else {
                        if ($this->option('debug')) {
                            $this->info('Limiting TOC results to non-green.');
                        }
                        $toc = $toc->where(
                            'green_product',
                            0
                        );
                    }

                    if ($this->option('debug')) {
                        $this->info('Using rate type to limit TOC results: ' . $rateInfo->rate_type_id);
                    }
                    $toc = $toc->where(
                        function ($query) use ($rateInfo) {
                            $query->where(
                                'rate_type_id',
                                $rateInfo->rate_type_id
                            )->orWhereNull(
                                'rate_type_id'
                            );
                        }
                    );
                }


                $toc = $toc->orderBy('rate_type_id', 'desc')->first();

                if ($toc) {
                    $ep['toc'] = $toc->toc;

                    $eventId = $ep['event']['id'];
                    $selectedProduct = $ep['id'];
                    $text = $toc->toc;
                    $lang = $ep['event']['language_id'];
                    $eventData = Cache::remember('event-' . $eventId, 30, function () use ($eventId) {
                        return SupportController::gatherEventDetails($eventId);
                    });
                    if (!is_array($selectedProduct)) {
                        if (isset($eventData['products']) && null !== $selectedProduct) {
                            foreach ($eventData['products'] as $p) {
                                if ($p['id'] === $selectedProduct) {
                                    $eventData['selectedProduct'] = $p;
                                    break;
                                }
                            }
                        }
                    } else {
                        if (2 != count($selectedProduct)) {
                            $this->logError('Invalid number of selected products', __METHOD__, __LINE__);
                        } else {
                            $dualProduct = ['dualFuel' => true, 'electric' => null, 'gas' => null];
                            if (isset($eventData['products'])) {
                                foreach ($eventData['products'] as $p) {
                                    foreach ($selectedProduct as $sp) {
                                        if ($p['id'] === $sp) {
                                            if (1 == $p['event_type_id']) {
                                                $dualProduct['electric'] = $p;
                                                continue 2;
                                            }
                                            if (2 == $p['event_type_id']) {
                                                $dualProduct['gas'] = $p;
                                                continue 2;
                                            }
                                        }
                                    }
                                }
                                $eventData['selectedProduct'] = $dualProduct;
                            }
                        }
                    }
                    $varMap = SupportController::getVariableMap();

                    $product['toc'] = SupportController::hydrateVariables($text, $eventData, $lang, $varMap);
                    $product['toc'] = htmlspecialchars(strip_tags($product['toc']));
                }
                // end toc

                // product custom fields
                if (
                    isset($ep['custom_fields'])
                ) {
                    // parse custom fields into $product['custom']
                    foreach ($ep['custom_fields'] as $cfs) {
                        $product['custom']['custom_' . $commodity . '_' . $cfs['custom_field']['output_name']] = $cfs['value'];
                    }
                }
                // end product custom fields

                $data['updated_products'][] = $product;
            }

            $updatedEmail = EmailAddress::select(
                'email_address'
            )->join(
                'email_address_lookup AS eal',
                'eal.email_address_id',
                'email_addresses.id'
            )->join(
                'events AS e',
                'e.id',
                'eal.type_id'
            )->where(
                'e.eztpv_id',
                $eztpv->id
            )->where(
                function ($query) {
                    $query->where(
                        'eal.email_address_type_id',
                        3
                    )->orWhere(
                        'eal.email_address_type_id',
                        '3'
                    );
                }
            )->first();
            if ($updatedEmail) {
                $data['email_address'] = $updatedEmail->email_address;
            } else {
                $data['email_address'] = null;
            }

            $updatedPhoneNumber = PhoneNumber::select(
                'phone_number'
            )->join(
                'phone_number_lookup AS pnl',
                'pnl.phone_number_id',
                'phone_numbers.id'
            )->join(
                'events AS e',
                'e.id',
                'pnl.type_id'
            )->where(
                'e.eztpv_id',
                $eztpv->id
            )->where(
                'pnl.phone_number_type_id',
                3
            )->whereNull(
                'pnl.deleted_at'
            )->first();
            if ($updatedPhoneNumber) {
                $data['phone_number'] = str_replace('+1', '', $updatedPhoneNumber->phone_number);
            }
            $data['market_id'] = $data['updated_products'][0]['market_id'];
            $data['ah_date_of_birth'] = Carbon::parse(
                $eps[0]['event']['ah_date_of_birth'],
                'America/Chicago'
            )->format('m/d/y');

            $this->formData = json_encode($data);
        }

        return;
    }

    /**
     * Hash Service Address.
     *
     * @param array $product - product array
     *
     * @return string
     */
    private function hashServiceAddress($product)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        $address_hash = md5(
            $this->cleanUp($product['service_address1']) .
                $this->cleanUp($product['service_address2']) .
                $this->cleanUp($product['service_city']) .
                $this->cleanUp($product['service_state']) .
                $this->cleanUp($product['service_zip'])
        );

        return $address_hash;
    }

    public function generate_contract_document($eztpv, $data, $contract_id = null)
    {
        $this->logDebug(
            'CONTRACT GENERATION --> finalized_products = '
                . print_r($data['finalized_products'], true), __METHOD__, __LINE__
        );

        $event = Event::where(
            'eztpv_id',
            $eztpv->id
        )->with(
            [
                'interactions',
                'eztpvConfig',
                'customFieldStorage.customField'
            ]
        )->first();

        // event custom fields
        if (
            isset($event->customFieldStorage)
        ) {
            $eventCustomFields = [];
            // parse custom fields into $product['custom']
            foreach ($event->customFieldStorage as $cfs) {
                if (
                    isset($cfs->customField)
                    && isset($cfs->customField->output_name)
                    && isset($cfs->value)
                ) {
                    $eventCustomFields['custom_' . $cfs->customField->output_name] = $cfs->value;
                    ${'custom_' . $cfs->customField->output_name} = $cfs->value;
                }
            }
        }
        // end event custom fields

        if ($this->option('debug')) {
            $this->info('finalized_products is ' . print_r($data['finalized_products'], true));
        }

        for ($x = 0; $x <= count($data['finalized_products']) - 1; ++$x) {
            //preprocess data
            unset(
                $account_number_electric,
                $account_number_gas,
                $address,
                $address_service,
                $address_billing,
                $address_electric,
                $address_gas,
                $agent_fullname,
                $agent_id,
                $agent_license,
                $ah_date_of_birth,
                $auth_fullname,
                $auth_relationship,
                $business_name,
                $auth_relationship_authorized_agent,
                $auth_relationship_account_holder,
                $auth_relationship_self,
                $auth_relationship_spouse,
                $bill_fullname,
                $bill_fullname_same_as_auth_fullname,
                $city,
                $city_service,
                $city_billing,
                $city_electric,
                $city_gas,
                $city_state_zip_billing,
                $city_state_zip_service,
                $client_name,
                $client_address,
                $client_city,
                $client_state,
                $client_zip,
                $client_phone_number,
                $client_email_address,
                $client_logo_path,
                $commodity_type,
                $commodity_type_all,
                $company_name,
                $company_name_electric,
                $company_name_gas,
                $computed_electric_green_product,
                $computed_electric_rate_type_plus_green,
                $computed_gas_green_product,
                $computed_gas_rate_type_plus_green,
                $computed_electric_other_fees,
                $computed_multiline_auth_fullname_fl_plus_service_address,
                $confirmation_code,
                $date,
                $date_plus_3_days,
                $date_plus_10_days,
                $delivery_method,
                $eft_radio,
                $electric_fixed_rate_checkbox,
                $electric_tiered_rate_checkbox,
                $utility_electric_primary_identifier,
                $electric_utility_account_type_account_number,
                $electric_utility_account_type_customer_number,
                $electric_utility_account_type_supplier_agreement_id,
                $electric_variable_rate_checkbox,
                $email_address,
                $end_date_electric,
                $end_date_electric_month_year,
                $end_date_gas,
                $renewal_date_electric,
                $renewal_date_gas,
                $event_type_electric,
                $event_type_electric_and_gas,
                $event_type_gas,
                $full_address,
                $full_address_service,
                $full_address_billing,
                $fullname,
                $fullname_electric,
                $fullname_gas,
                $gas_fixed_rate_checkbox,
                $gas_info_same_as_electric,
                $gas_tiered_rate_checkbox,
                $utility_gas_primary_identifier,
                $gas_utility_account_type_account_number,
                $gas_utility_account_type_customer_number,
                $gas_variable_rate_checkbox,
                $gps_lat,
                $gps_lon,
                $green_product,
                $green_percentage,
                $green_percentage_formatted,
                $initials,
                $ip_address,
                $modified_rate_amount,
                $modified_daily_fee,
                $no_email,
                $office_name,
                $phone_number,
                $rate_info_electric_admin_fee,
                $rate_info_electric_currency,
                $rate_info_electric_calculated_intro_rate_amount,
                $rate_info_electric_calculated_intro_rate_amount_in_cents,
                $rate_info_electric_calculated_intro_rate_amount_in_dollars,
                $rate_info_electric_calculated_rate_amount,
                $rate_info_electric_calculated_rate_amount_in_cents,
                $rate_info_electric_calculated_rate_amount_in_dollars,
                $rate_info_electric_custom_data_1,
                $rate_info_electric_custom_data_2,
                $rate_info_electric_custom_data_3,
                $rate_info_electric_custom_data_4,
                $rate_info_electric_custom_data_5,
                $rate_info_electric_daily_fee,
                $rate_info_electric_daily_fee_in_cents,
                $rate_info_electric_daily_fee_in_dollars,
                $rate_info_electric_date_to,
                $rate_info_electric_estimated_daily_fee_500_in_cents,
                $rate_info_electric_estimated_daily_fee_500_in_dollars,
                $rate_info_electric_estimated_daily_fee_1000_in_cents,
                $rate_info_electric_estimated_daily_fee_1000_in_dollars,
                $rate_info_electric_estimated_daily_fee_1500_in_cents,
                $rate_info_electric_estimated_daily_fee_1500_in_dollars,
                $rate_info_electric_estimated_daily_fee_2000_in_cents,
                $rate_info_electric_estimated_daily_fee_2000_in_dollars,
                $rate_info_electric_intro_estimated_daily_fee_500_in_cents,
                $rate_info_electric_intro_estimated_daily_fee_500_in_dollars,
                $rate_info_electric_intro_estimated_daily_fee_1000_in_cents,
                $rate_info_electric_intro_estimated_daily_fee_1000_in_dollars,
                $rate_info_electric_intro_estimated_daily_fee_1500_in_cents,
                $rate_info_electric_intro_estimated_daily_fee_1500_in_dollars,
                $rate_info_electric_intro_estimated_daily_fee_2000_in_cents,
                $rate_info_electric_intro_estimated_daily_fee_2000_in_dollars,
                $rate_info_electric_estimated_total_bill_500kWh,
                $rate_info_electric_estimated_total_bill_1000kWh,
                $rate_info_electric_estimated_total_bill_1500kWh,
                $rate_info_electric_estimated_total_bill_2000kWh,
                $rate_info_electric_final_rate_500kWh,
                $rate_info_electric_final_rate_1000kWh,
                $rate_info_electric_final_rate_1500kWh,
                $rate_info_electric_final_rate_2000kWh,
                $rate_info_electric_final_rate_500kWh_in_cents,
                $rate_info_electric_final_rate_500kWh_in_dollars,
                $rate_info_electric_final_rate_1000kWh_in_cents,
                $rate_info_electric_final_rate_1000kWh_in_dollars,
                $rate_info_electric_final_rate_1500kWh_in_cents,
                $rate_info_electric_final_rate_1500kWh_in_dollars,
                $rate_info_electric_final_rate_2000kWh_in_cents,
                $rate_info_electric_final_rate_2000kWh_in_dollars,
                $rate_info_electric_green_percentage,
                $rate_info_electric_intro_term,
                $rate_info_electric_monthly_fee,
                $rate_info_electric_name,
                $rate_info_electric_program_code,
                $rate_info_electric_rate_amount,
                $rate_info_electric_rate_amount_in_cents,
                $rate_info_electric_rate_amount_in_dollars,
                $rate_info_electric_rate_amount_500kWh_in_cents,
                $rate_info_electric_rate_amount_1000kWh_in_cents,
                $rate_info_electric_rate_amount_2000kWh_in_cents,
                $rate_info_electric_rate_monthly_fee,
                $rate_info_electric_rate_source_code,
                $rate_info_electric_rate_uom,
                $rate_info_electric_service_fee,
                $rate_info_electric_term,
                $rate_info_electric_term_remaining,
                $rate_info_electric_term_total,
                $rate_info_electric_term_uom,
                $rate_info_electric_transaction_fee,
                $rate_info_electric_tiered_rate_amount,
                $rate_info_electric_yearly_price,
                $rate_info_electric_promo_code,
                $rate_info_electric_promo_name,
                $rate_info_electric_promo_type,
                $rate_info_electric_promo_key,
                $rate_info_electric_promo_text,
                $rate_info_electric_promo_reward,
                $rate_info_gas_currency,
                $rate_info_gas_calculated_intro_rate_amount,
                $rate_info_gas_calculated_intro_rate_amount_in_cents,
                $rate_info_gas_calculated_intro_rate_amount_in_dollars,
                $rate_info_gas_calculated_rate_amount,
                $rate_info_gas_calculated_rate_amount_in_cents,
                $rate_info_gas_calculated_rate_amount_in_dollars,
                $rate_info_gas_custom_data_1,
                $rate_info_gas_custom_data_2,
                $rate_info_gas_custom_data_3,
                $rate_info_gas_custom_data_4,
                $rate_info_gas_custom_data_5,
                $rate_info_gas_date_to,
                $rate_info_gas_estimated_daily_fee_500_in_cents,
                $rate_info_gas_estimated_daily_fee_500_in_dollars,
                $rate_info_gas_estimated_daily_fee_1000_in_cents,
                $rate_info_gas_estimated_daily_fee_1000_in_dollars,
                $rate_info_gas_estimated_daily_fee_1500_in_cents,
                $rate_info_gas_estimated_daily_fee_1500_in_dollars,
                $rate_info_gas_estimated_daily_fee_2000_in_cents,
                $rate_info_gas_estimated_daily_fee_2000_in_dollars,
                $rate_info_gas_intro_estimated_daily_fee_500_in_cents,
                $rate_info_gas_intro_estimated_daily_fee_500_in_dollars,
                $rate_info_gas_intro_estimated_daily_fee_1000_in_cents,
                $rate_info_gas_intro_estimated_daily_fee_1000_in_dollars,
                $rate_info_gas_intro_estimated_daily_fee_1500_in_cents,
                $rate_info_gas_intro_estimated_daily_fee_1500_in_dollars,
                $rate_info_gas_intro_estimated_daily_fee_2000_in_cents,
                $rate_info_gas_intro_estimated_daily_fee_2000_in_dollars,
                $rate_info_gas_green_percentage,
                $rate_info_gas_intro_term,
                $rate_info_gas_monthly_fee,
                $rate_info_gas_name,
                $rate_info_gas_program_code,
                $rate_info_gas_rate_amount,
                $rate_info_gas_rate_amount_in_cents,
                $rate_info_gas_rate_amount_in_dollars,
                $rate_info_gas_rate_source_code,
                $rate_info_gas_rate_uom,
                $rate_info_gas_term,
                $rate_info_gas_term_remaining,
                $rate_info_gas_term_total,
                $rate_info_gas_term_uom,
                $rate_info_gas_tiered_rate_amount,
                $rate_info_gas_yearly_price,
                $rate_info_gas_promo_code,
                $rate_info_gas_promo_name,
                $rate_info_gas_promo_type,
                $rate_info_gas_promo_key,
                $rate_info_gas_promo_text,
                $rate_info_gas_promo_reward,
                $rate_info_rate_amount,
                $rate_info_rate_source_code,
                $rate_info_term,
                $rieetb500,
                $rieetb1000,
                $rieetb1500,
                $rieetb2000,
                $riefr500,
                $riefr1000,
                $riefr1500,
                $riefr2000,
                $service_address_same_as_billing_address,
                $signature_date,
                $start_date,
                $start_date_electric,
                $start_date_gas,
                $state,
                $state_service,
                $state_billing,
                $state_electric,
                $state_gas,
                $text_computed_electric_cancellation_fee,
                $text_computed_electric_cancellation_fee_short,
                $text_computed_gas_cancellation_fee,
                $text_computed_gas_cancellation_fee_short,
                $toc_electric,
                $toc_gas,
                $uecs,
                $uecsf,
                $ugcs,
                $ugcsf,
                $utility_electric_address,
                $utility_electric_address_full,
                $utility_electric_city,
                $utility_electric_customer_service,
                $utility_electric_customer_service_formatted,
                $utility_electric_name,
                $utility_electric_state,
                $utility_electric_website,
                $utility_electric_zip,
                $utility_gas_address,
                $utility_gas_address_full,
                $utility_gas_city,
                $utility_gas_customer_service,
                $utility_gas_customer_service_formatted,
                $utility_gas_name,
                $utility_gas_state,
                $utility_gas_website,
                $utility_gas_zip,
                $utility_name_all,
                $utility_account_number_all,
                $utility_rate_unit,
                $utility_rate_unit_electric,
                $utility_rate_unit_gas,
                $vendor,
                $vendor_name,
                $vendor_phone_number,
                $zip,
                $zip_service,
                $zip_billing,
                $zip_electric,
                $zip_gas,

                $rate_info_electric_intro_final_rate_500kWh,
                $rate_info_electric_intro_final_rate_1000kWh,
                $rate_info_electric_intro_final_rate_1500kWh,
                $rate_info_electric_intro_final_rate_2000kWh,
                $rieir500,
                $rieir1000,
                $rieir1500,
                $rieir2000
            );

            $account_number = $data['finalized_products'][$x][0]['account_number'];
            $address = $data['finalized_products'][$x][0]['service_address1']
                . ' ' . $data['finalized_products'][$x][0]['service_address2'];
            $address_service = $data['finalized_products'][$x][0]['service_address1']
                . ' ' . $data['finalized_products'][$x][0]['service_address2'];
            if (isset($data['finalized_products'][$x][0]['billing_address1'])) {
                $address_billing = $data['finalized_products'][$x][0]['billing_address1']
                    . ' ' . $data['finalized_products'][$x][0]['billing_address2'];
            } else {
                $address_billing = $address_service;
            }

            $service_address_same_as_billing_address = 'Off';
            if ($address_service === $address_billing) {
                $service_address_same_as_billing_address = 'Yes';
            }

            $agent_id_query = BrandUser::select(
                'brand_users.tsr_id',
                'brand_users.license_number',
                'users.first_name',
                'users.last_name',
                'offices.label as office_name'
            )->leftJoin(
                'users',
                'brand_users.user_id',
                'users.id'
            )->leftJoin(
                'brand_user_offices',
                'brand_user_offices.brand_user_id',
                'brand_users.id'
            )->leftJoin(
                'offices',
                'brand_user_offices.office_id',
                'offices.id'
            )->where(
                'brand_users.id',
                $this->eztpv->user_id
            )->where(
                'offices.id',
                $event->office_id
            )->first();
            $agent_id = ($agent_id_query)
                ? $agent_id_query->tsr_id
                : '';

            $office_name = isset($agent_id_query->office_name) ? $agent_id_query->office_name : null;

            if (
                isset($agent_id_query->first_name)
                && isset($agent_id_query->last_name)
            ) {
                $agent_fullname = $agent_id_query->first_name .
                    ' ' . $agent_id_query->last_name;
            } else {
                $agent_fullname = null;
            }

            if (isset($agent_id_query->license_number)) {
                $agent_license = $agent_id_query->license_number;
            } else {
                $agent_license = null;
            }

            // debug('generate_contract_document with '. json_encode($data));

            // $agent_info = BrandUser::select(
            //     'users.first_name',
            //     'users.last_name'
            // )->leftJoin(
            //     'users',
            //     'brand_users.user_id',
            //     'users.id'
            // )->where('brand_users.id', $agent_id)
            // ->first();

            // $agent_info = User::select(
            //     'users.first_name',
            //     'users.last_name'
            // )
            // ->where('users.id', $agent_id)
            // ->first();
            // if ($agent_info) {
            //     $agent_fullname = $agent_info->first_name . " " . $agent_info->last_name;
            // } else {
            //     $agent_fullname = null;
            // }

            // $relationships = BrandAuthRelationship::select(
            //         'brand_auth_relationships.auth_relationship_id',
            //         'auth_relationships.relationship'
            //         )
            //     ->leftJoin('auth_relationships', 'brand_auth_relationships.auth_relationship_id', 'auth_relationships.id')
            //     ->where('brand_auth_relationships.brand_id', $this->eztpv->brand_id)
            //     ->where('brand_auth_relationships.state_id', $this->eztpv->event->products[0]->serviceAddress->address->state->id)
            //     ->get();

            // foreach ($relationships as $relationship) {
            //     $relationship->relationship = str_replace(' ', '_', strtolower($relationship->relationship));
            //     ${'auth_relationship_' . $relationship->relationship} = 'Off';
            //     if ($data['auth_relationship'] == $relationship->auth_relationship_id) {
            //         ${'auth_relationship_'.$relationship->relationship} = 'Yes';
            //     } else {
            //         ${'auth_relationship_'.$relationship->relationship} = 'Off';
            //     }
            // }

            // if ($data['auth_relationship'] != 1) {
            //     $auth_relationship_authorized_agent = 'Yes';
            // } else {
            //     $auth_relationship_authorized_agent = 'Off';
            // }

            // switch ($data['auth_relationship']) {
            // case 'self':
            //     $auth_relationship_self = 'Yes';
            //     $auth_relationship_spouse = 'Off';
            //     break;

            // case 'spouse':
            //     $auth_relationship_self = 'Off';
            //     $auth_relationship_spouse = 'Yes';
            //     break;
            // }

            $ah_date_of_birth = (isset($data['ah_date_of_birth'])) ?
                $data['ah_date_of_birth'] : null;
            $city = $data['finalized_products'][$x][0]['service_city'];
            $city_service = $data['finalized_products'][$x][0]['service_city'];
            if (isset($data['finalized_products'][$x][0]['billing_city'])) {
                $city_billing = $data['finalized_products'][$x][0]['billing_city'];
            } else {
                $city_billing = $city_service;
            }

            $client = Brand::where(
                'id',
                $this->eztpv->brand_id
            )
                ->with('logo')
                ->first();
            $client_name = (isset($client->name)) ? $client->name : null;
            $client_address = (isset($client->address)) ? $client->address : null;
            $client_city = (isset($client->city)) ? $client->city : null;
            $client_state = (isset($client->state)) ? $client->state : null;
            if (is_numeric($client_state)) {
                $__client_state = State::where('id', $client_state)->first();
                if (!empty($__client_state)) {
                    $client_state = $__client_state->state_abbrev;
                }
            }
            $client_zip = (isset($client->zip)) ? $client->zip : null;
            $client_phone_number = (isset($client->service_number)) ? FormatPhoneNumber($client->service_number) : null;
            $client_email_address = (isset($client->email_address)) ? $client->email_address : null;
            $client_logo_path = (isset($client->logo_path)) ? config('services.aws.cloudfront.domain') . '/' . $client->logo_path : null;

            $auth_relationship = $data['finalized_products'][$x][0]['auth_relationship'];
            $business_name = $data['finalized_products'][$x][0]['business_name'];

            $confirmation_code = $event->confirmation_code;
            $date = date('m-d-Y', strtotime($eztpv->created_at));
            $date_plus_3_days = date('m-d-Y', strtotime($eztpv->created_at . '+ 3 weekdays'));
            $date_plus_10_days = date('m-d-Y', strtotime($eztpv->created_at . '+ 10 weekdays'));
            $email_address = $data['email_address'];
            $event_type_electric = 'Off';
            $event_type_gas = 'Off';
            $event_type_electric_and_gas = 'Off';
            $fullname = $data['finalized_products'][$x][0]['bill_first_name']
                . ' ' . $data['finalized_products'][$x][0]['bill_last_name'];
            $bill_fullname = $data['finalized_products'][$x][0]['bill_last_name']
                . ', ' . $data['finalized_products'][$x][0]['bill_first_name'];
            if (isset($data['finalized_products'][$x][0]['auth_middle_name'])) {
                $auth_fullname = $data['finalized_products'][$x][0]['auth_last_name']
                    . ', '
                    . $data['finalized_products'][$x][0]['auth_first_name']
                    . ' '
                    . substr($data['finalized_products'][$x][0]['auth_middle_name'], 0, 1);
                $auth_fullname_fl = $data['finalized_products'][$x][0]['auth_first_name']
                    . ' '
                    . substr($data['finalized_products'][$x][0]['auth_middle_name'], 0, 1)
                    . ' '
                    . $data['finalized_products'][$x][0]['auth_last_name'];
            } else {
                $auth_fullname = $data['finalized_products'][$x][0]['auth_last_name']
                    . ', '
                    . $data['finalized_products'][$x][0]['auth_first_name'];
                $auth_fullname_fl = $data['finalized_products'][$x][0]['auth_first_name']
                    . ' '
                    . $data['finalized_products'][$x][0]['auth_last_name'];
            }
            $bill_fullname_same_as_auth_fullname = 'Off';
            if ($bill_fullname === $auth_fullname) {
                $bill_fullname_same_as_auth_fullname = 'Yes';
            }

            $initials = strtoupper(
                substr(
                    $data['finalized_products'][$x][0]['bill_first_name'],
                    0,
                    1
                )
            ) . strtoupper(substr($data['finalized_products'][$x][0]['bill_last_name'], 0, 1));

            $ip_address = (isset($event->ip_addr)) ? $event->ip_addr : null;

            $no_email = 'Off';

            if ($data['no_email'] = 1) {
                $no_email = 'Yes';
            }

            $phone_number = (!empty($data['phone_number']))
                ? str_replace('+1', '', $data['phone_number'])
                : null;

            $rate_info_electric = '';
            $rate_info_gas = '';

            if ($this->option('debug')) {
                $this->info('rate_info_electric assigned ' . print_r($rate_info_electric, true));
            }

            if ($this->option('debug')) {
                $this->info('rate_info_gas assigned ' . print_r($rate_info_gas, true));
            }

            $rate_info_gas_term = '';
            $rate_info_gas_rate_amount = '';
            $rate_info_electric_rate_amount = '';
            $company_name_gas = '';

            if (
                strlen(trim($data['finalized_products'][$x][0]['service_state'])) > 0
                && $data['finalized_products'][$x][0]['service_state'] > 0
            ) {
                $state_query = State::find(
                    $data['finalized_products'][$x][0]['service_state']
                );

                $state = $state_query->state_abbrev;
                $state_service_query = $state_query;
                $state_service = $state_service_query->state_abbrev;
            } else {
                $zip_query = ZipCode::where(
                    'zip',
                    $data['finalized_products'][$x][0]['service_zip']
                )->first();

                if ($zip_query) {
                    $use_state = $zip_query->state;
                } else {
                    $use_state = $data['finalized_products'][$x][0]['service_state'];
                }

                $state_query = State::where(
                    'state_abbrev',
                    $use_state
                )->first();

                $state = $state_query->state_abbrev;
                $state_service_query = $state_query;
                $state_service = $state_service_query->state_abbrev;
            }

            if (isset($data['finalized_products'][$x][0]['billing_state'])) {
                $state_billing = $data['finalized_products'][$x][0]['billing_state'];
            } else {
                $state_billing = $state_service;
            }

            $zip = $data['finalized_products'][$x][0]['service_zip'];
            $zip_service = $data['finalized_products'][$x][0]['service_zip'];

            $city_state_zip_service = $city_service . ', ' . $state_service . ', ' . $zip_service;

            $computed_multiline_auth_fullname_fl_plus_service_address = $auth_fullname_fl . '\r' . $address_service . '\r' . $city_service . ', ' . $state_service . ' ' . $zip_service;

            if (isset($data['finalized_products'][$x][0]['billing_zip'])) {
                $zip_billing = $data['finalized_products'][$x][0]['billing_zip'];
            } else {
                $zip_billing = $zip_service;
            }
            $full_address = $address . ', ' . $city . ', ' . $state . ' ' . $zip;
            $full_address_service = $address_service . ', ' . $city_service
                . ', ' . $state_service . ' ' . $zip_service;
            $full_address_billing = $address_billing . ', '
                . $city_billing . ', ' . $state_billing . ' ' . $zip_billing;

            if (
                isset($city_billing)
                && isset($state_billing)
                && isset($zip_billing)
            ) {
                $city_state_zip_billing = $city_billing . ', ' . $state_billing . ', ' . $zip_billing;
            } else {
                $city_state_zip_billing = $city_state_zip_service;
            }

            $gps = (isset($event->gps_coords)) ? explode(',', $event->gps_coords) : null;
            if (isset($gps)) {
                $gps_lat = $gps[0];
                $gps_lon = $gps[1];
            } else {
                $gps_lat = null;
                $gps_lon = null;
            }

            $green_product = 'Off';
            $green_percentage = 0;
            $start_date = $date;
            $start_date_electric = '';
            $start_date_gas = '';
            $eft_info = Rate::find($data['finalized_products'][$x][0]['rate_id']);
            if (isset($eft_info->cancellation_fee)) {
                $eft_radio = 'Yes';
            } else {
                $eft_radio = 'No';
            }

            $vendor = Brand::find($this->eztpv->event->vendor_id);
            $vendor_name = (isset($vendor->name)) ? $vendor->name : null;
            $vendor_phone_number = (isset($vendor->service_number)) ? $vendor->service_number : null;
            if (isset($eztpv->signature_customer)) {
                $signature_date = $eztpv->signature_customer->updated_at;
            } elseif (isset($eztpv->signature_date)) {
                $signature_date = $eztpv->signature_date;
            } else {
                $signature_date = null;
            }

            // brand-specific variables
            switch ($this->eztpv->brand_id) {
                case '4e65aab8-4dae-48ef-98ee-dd97e16cbce6':
                case 'eb35e952-04fc-42a9-a47d-715a328125c0':
                    // Indra Energy (staging/production)
                    $cfs = CustomFieldStorage::select(
                        'custom_field_storages.value'
                    )
                        ->join(
                            'custom_fields',
                            'custom_field_storages.custom_field_id',
                            'custom_fields.id'
                        )
                        ->where(
                            'custom_field_storages.event_id',
                            $event->id
                        )
                        ->where(
                            'custom_fields.output_name',
                            'delivery_method'
                        )
                        ->first();

                    if (null != $cfs) {
                        $cfs_array = json_decode($cfs);
                        $delivery_method = $cfs_array->value ? $cfs_array->value : null;
                    }
                    break;
            }

            $rate_info_electric_promo_code = '';
            $rate_info_electric_promo_type = '';
            $rate_info_electric_promo_key = '';
            $rate_info_electric_promo_text = '';
            $rate_info_electric_promo_name = '';
            $rate_info_electric_promo_reward = '';

            $rate_info_gas_promo_code = '';
            $rate_info_gas_promo_type = '';
            $rate_info_gas_promo_key = '';
            $rate_info_gas_promo_text = '';
            $rate_info_gas_promo_name = '';
            $rate_info_gas_promo_reward = '';

            foreach ($data['finalized_products'][$x] as $product) {
                if ($this->option('debug')) {
                    $this->info('processingProduct(0) ' . print_r($product, true));
                }
                $relationships = BrandAuthRelationship::select(
                    'brand_auth_relationships.auth_relationship_id',
                    'auth_relationships.relationship'
                )->leftJoin(
                    'auth_relationships',
                    'brand_auth_relationships.auth_relationship_id',
                    'auth_relationships.id'
                )->where(
                    'brand_auth_relationships.brand_id',
                    $this->eztpv->brand_id
                )->where(
                    'brand_auth_relationships.state_id',
                    $this->eztpv->event->products[0]->serviceAddress->address->state->id
                )->get();

                foreach ($relationships as $relationship) {
                    $codeRelationship = str_replace(
                        ' ',
                        '_',
                        strtolower($relationship->relationship)
                    );

                    ${'auth_relationship_' . $codeRelationship} = 'Off';
                    if ($product['auth_relationship'] == $relationship->relationship) {
                        ${'auth_relationship_' . $codeRelationship} = 'Yes';
                    } else {
                        ${'auth_relationship_' . $codeRelationship} = 'Off';
                    }
                }

                if ('Account Holder' !== $product['auth_relationship']) {
                    $auth_relationship_authorized_agent = 'Yes';
                } else {
                    $auth_relationship_authorized_agent = 'Off';
                }

                $green = Product::select('green_percentage')
                    ->leftJoin('rates', 'products.id', 'rates.product_id')
                    ->where('rates.id', $product['rate_id'])
                    ->first();
                if (isset($green->green_percentage) && 0 != $green->green_percentage) {
                    $green_product = 'Yes';
                    $green_percentage = $green->green_percentage;
                    $green_percentage_formatted = $green_percentage . '%';
                }

                $event_type = EventType::find(@$product['event_type_id']);
                $bill_fullname = $product['bill_last_name'] . ', ' . $product['bill_first_name'];
                $auth_fullname = $product['auth_last_name'] . ', ' . $product['auth_first_name'];

                // product-based custom fields
                if (
                    isset($product['custom'])
                ) {
                    foreach ($product['custom'] as $customField => $customValue) {
                        $$customField = $customValue;
                    }
                }

                if ($event_type && 'Electric' == $event_type->event_type) {
                    $this->info('Electric product');

                    // set electric promo
                    // $rate_info_electric_promo_code = $product['promotion']['code'];
                    // $rate_info_electric_promo_type = $product['promotion']['type'];
                    // $rate_info_electric_promo_key = $product['promotion']['key'];
                    // switch ($this->eztpv->event->language_id) {
                    //     case 1:
                    //         $rate_info_electric_promo_text = $product['promotion']['text_english'];
                    //         $rate_info_electric_promo_name = $product['promotion']['name_english'];
                    //         break;
                    //     case 2:
                    //         $rate_info_electric_promo_text = !empty($product['promotion']['text_spanish']) ? $product['promotion']['text_spanish'] : $product['promotion']['text_english'];
                    //         $rate_info_electric_promo_name = !empty($product['promotion']['name_spanish']) ? $product['promotion']['name_spanish'] : $product['promotion']['name_english'];
                    //         break;
                    // }
                    // $rate_info_electric_promo_reward = $product['promotion']['reward'];

                    $utility_rate_unit = 'kWh';
                    $utility_rate_unit_electric = 'kWh';
                    // $account_number_electric = $product['account_number'];
                    // if (isset($utility_account_number_all)) {
                    //     $utility_account_number_all .= '\r' . $product['account_number'];
                    // } else {
                    //     $utility_account_number_all = $product['account_number'];
                    // }

                    $address_electric = $product['service_address1']
                        . ' ' . $product['service_address2'];
                    $city_electric = $product['service_city'];
                    $electric_utility_account_type_account_number = 'Off';
                    $electric_utility_account_type_customer_number = 'Off';
                    $electric_utility_account_type_supplier_agreement_id = 'Off';
                    $gas_utility_account_type_account_number = 'Off';
                    $gas_utility_account_type_customer_number = 'Off';
                    $event_type_electric = 'Yes';

                    // $utility_electric = UtilitySupportedFuel::select(
                    //     'utilities.name',
                    //     'utility_account_types.account_type',
                    //     'utilities.customer_service',
                    //     'utilities.website',
                    //     'utilities.address1',
                    //     'utilities.address2',
                    //     'utilities.address3',
                    //     'utilities.city',
                    //     'utilities.state',
                    //     'utilities.zip'
                    // )->leftJoin(
                    //     'utilities',
                    //     'utility_supported_fuels.utility_id',
                    //     'utilities.id'
                    // )->leftJoin(
                    //     'utility_account_identifiers',
                    //     'utility_supported_fuels.id',
                    //     'utility_account_identifiers.utility_id'
                    // )->leftJoin(
                    //     'utility_account_types',
                    //     'utility_account_identifiers.utility_account_type_id',
                    //     'utility_account_types.id'
                    // )->where(
                    //     'utility_supported_fuels.id',
                    //     $product['utility_id']
                    // )->whereNull(
                    //     'utility_account_identifiers.deleted_at'
                    // )->first();

                    // $this->info('utility_electric = ' . print_r($utility_electric->toArray(), true));

                    $company_name_electric = null;
                    // if (isset($utility_electric)) {
                    //     $utility_electric_primary_identifier = $utility_electric->account_type;
                    //     if (
                    //         isset($utility_name_all)
                    //         && $utility_name_all !== htmlspecialchars($utility_electric->name)
                    //     ) {
                    //         $utility_name_all .= ', ' . htmlspecialchars($utility_electric->name);
                    //     } else {
                    //         $utility_name_all = htmlspecialchars($utility_electric->name);
                    //     }

                    //     if (
                    //         isset($utility_electric->customer_service)
                    //     ) {
                    //         $utility_electric_customer_service = str_replace('+1', '', $utility_electric->customer_service);
                    //         $utility_electric_customer_service_formatted = '(' . substr($utility_electric_customer_service, 0, 3) . ') ' . substr($utility_electric_customer_service, 3, 3) . '-' . substr($utility_electric_customer_service, 6, 4);
                    //         $uecs = $utility_electric_customer_service;
                    //         $uecsf = $utility_electric_customer_service_formatted;
                    //     } else {
                    //         $utility_electric_customer_service = null;
                    //         $utility_electric_customer_service_formatted = null;
                    //         $uecs = null;
                    //         $uecsf = null;
                    //     }

                    //     $ue_address1 = (isset($utility_electric->address1)) ? $utility_electric->address1 : null;
                    //     $ue_address2 = (isset($utility_electric->address2)) ? $utility_electric->address2 : null;
                    //     $ue_address3 = (isset($utility_electric->address3)) ? $utility_electric->address3 : null;
                    //     $utility_electric_city = (isset($utility_electric->city)) ? $utility_electric->city : null;
                    //     $utility_electric_state = (isset($utility_electric->state)) ? $utility_electric->state : null;
                    //     $utility_electric_zip = (isset($utility_electric->zip)) ? $utility_electric->zip : null;
                    //     $utility_electric_address = $ue_address1 . ' ' . $ue_address2 . ' ' . $ue_address3;
                    //     $utility_electric_address_full = $utility_electric_address . ', ' . $utility_electric_city . ', ' . $utility_electric_state . ', ' . $utility_electric_zip;
                    //     $utility_electric_website = (isset($utility_electric->website)) ? $utility_electric->website : null;
                    //     $utility_electric_name = (isset($utility_electric) && isset($utility_electric->name))
                    //         ? htmlentities($utility_electric->name)
                    //         : null;

                    //     $company_name_electric = $utility_electric->name;

                    //     switch ($utility_electric->account_type) {
                    //         case 'Account Number':
                    //             $electric_utility_account_type_account_number = 'Yes';
                    //             break;

                    //         case 'Customer Number':
                    //             $electric_utility_account_type_customer_number = 'Yes';
                    //             break;

                    //         case 'Supplier Agreement ID':
                    //             $electric_utility_account_type_supplier_agreement_id = 'Yes';
                    //             break;
                    //     }
                    // }

                    $utility_account_types_electric = UtilityAccountType::select(
                        'utility_account_types.id',
                        'utility_account_types.account_type'
                    )->whereNull(
                        'utility_account_types.deleted_at'
                    )->get();

                    if (
                        isset($utility_account_types_electric)
                        && count($utility_account_types_electric) > 0
                    ) {
                        foreach ($utility_account_types_electric as $type) {
                            $type->account_type = str_replace(
                                ' ',
                                '_',
                                strtolower($type->account_type)
                            );

                            ${'utility_account_type_electric_' . $type->account_type} = '';

                            $identifier = EventProductIdentifier::select(
                                'identifier'
                            )
                                ->where(
                                    'event_product_id',
                                    $product['id']
                                )
                                ->where(
                                    'utility_account_type_id',
                                    $type->id
                                )
                                ->first();

                            if ($identifier) {
                                ${'utility_account_type_electric_' . $type->account_type} = $identifier->identifier;
                            }

                            // Set utility_account_type_electric_choice_id for GCE
                            if($this->eztpv->brand_id == 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e' || $this->eztpv->brand_id == '7b08b19d-32a5-4906-a320-6a2c5d6d6372'){
                                if ($type->account_type == 'choice_id'){
                                    if($account_number_electric){
                                        ${'utility_account_type_electric_' . $type->account_type} = $account_number_electric;
                                    }
                                }
                            }
                        }
                    }

                    if (empty($account_number_electric)) {
                        if ($identifier) { // Make sure to check if $identifier is set
                            $account_number_electric = $identifier->identifier;
                            if (isset($utility_account_number_all)) {
                                $utility_account_number_all .= '\r' . $identifier->identifier;
                            } else {
                                $utility_account_number_all = $identifier->identifier;
                            }
                        }
                    }

                    $electric_fixed_rate_checkbox = 'Off';
                    $electric_variable_rate_checkbox = 'Off';
                    $electric_tiered_rate_checkbox = 'Off';
                    $fullname_electric = $product['bill_first_name'] . ', ' . $product['bill_last_name'];

                    // $rate_info_electric = Rate::select(
                    //     'products.daily_fee',
                    //     'products.transaction_fee',
                    //     'products.service_fee',
                    //     'products.green_percentage',
                    //     'products.id AS product_id',
                    //     'products.intro_term',
                    //     'products.monthly_fee',
                    //     'products.name',
                    //     'products.rate_type_id',
                    //     'products.term',
                    //     'products.term_type_id',
                    //     'rates.cancellation_fee',
                    //     'rates.date_to',
                    //     'rates.intro_rate_amount',
                    //     'rates.program_code',
                    //     'rates.rate_amount',
                    //     'rates.rate_currency_id',
                    //     'rates.rate_monthly_fee',
                    //     'rates.custom_data_1',
                    //     'rates.custom_data_2',
                    //     'rates.custom_data_3',
                    //     'rates.custom_data_4',
                    //     'rates.custom_data_5',
                    //     'rates.id',
                    //     'rates.rate_uom_id',
                    //     'rates.rate_source_code',
                    //     'rates.admin_fee'
                    // )->leftJoin(
                    //     'products',
                    //     'rates.product_id',
                    //     'products.id'
                    // )->where(
                    //     'rates.id',
                    //     $product['rate_id']
                    // )->with([
                    //     'rate_uom',
                    //     'term_type',
                    // ])->withTrashed()->first();

                    // if ($this->option('debug')) {
                    //     $this->info('rate_info_electric assigned ' . print_r($rate_info_electric->toArray(), true));
                    // }

                    // if (isset($rate_info_electric->monthly_fee)) {
                    //     $rate_info_electric_monthly_fee = $rate_info_electric->monthly_fee;
                    // } elseif (isset($rate_info_electric->rate_monthly_fee)) {
                    //     $rate_info_electric_monthly_fee = $rate_info_electric->rate_monthly_fee;
                    // } else {
                    //     switch ($this->eztpv->event->language_id) {
                    //         case 2:
                    //             $rate_info_electric_monthly_fee = 'Ninguno';
                    //             break;

                    //         case 1:
                    //         default:
                    //             $rate_info_electric_monthly_fee = 'None';
                    //             break;
                    //     }
                    // }

                    // switch ($rate_info_electric->rate_currency_id) {
                    //     case 1:
                    //         // cents
                    //         $rate_info_electric_calculated_rate_amount = $rate_info_electric->rate_amount * 0.01;
                    //         $rate_info_electric_calculated_intro_rate_amount = $rate_info_electric->intro_rate_amount * 0.01;

                    //         $rate_info_electric_calculated_rate_amount_in_cents = $rate_info_electric->rate_amount;
                    //         $rate_info_electric_calculated_rate_amount_in_dollars = $rate_info_electric->rate_amount * 0.01;

                    //         $rate_info_electric_calculated_intro_rate_amount_in_cents = $rate_info_electric->intro_rate_amount;
                    //         $rate_info_electric_calculated_intro_rate_amount_in_dollars = $rate_info_electric->intro_rate_amount * 0.01;

                    //         switch ($this->eztpv->event->language_id) {
                    //             case 2:
                    //                 $rate_info_electric_currency = 'centavos';
                    //                 break;

                    //             case 1:
                    //             default:
                    //                 $rate_info_electric_currency = 'cents';
                    //                 break;
                    //         }
                    //         break;

                    //     case 2:
                    //         // dollars
                    //         $rate_info_electric_calculated_rate_amount = $rate_info_electric->rate_amount * 0.01;
                    //         $rate_info_electric_calculated_intro_rate_amount = $rate_info_electric->intro_rate_amount * 0.01;

                    //         $rate_info_electric_calculated_rate_amount_in_cents = $rate_info_electric->rate_amount * 100;
                    //         $rate_info_electric_calculated_rate_amount_in_dollars = $rate_info_electric->rate_amount;

                    //         $rate_info_electric_calculated_intro_rate_amount_in_cents = $rate_info_electric->intro_rate_amount * 100;
                    //         $rate_info_electric_calculated_intro_rate_amount_in_dollars = $rate_info_electric->intro_rate_amount;

                    //         switch ($this->eztpv->event->language_id) {
                    //             case 2:
                    //                 $rate_info_electric_currency = 'dólares';
                    //                 break;

                    //             case 1:
                    //             default:
                    //                 $rate_info_electric_currency = 'dollars';
                    //                 break;
                    //         }
                    //         break;
                    // }

                    // $rate_info_electric_yearly_price = ($rate_info_electric_calculated_intro_rate_amount * 75) * 12;

                    // if (isset($rate_info_electric->cancellation_fee) && $rate_info_electric->cancellation_fee > 0) {
                    //     $text_computed_electric_cancellation_fee = '$' . $rate_info_electric->cancellation_fee;
                    //     $text_computed_electric_cancellation_fee_short = $text_computed_electric_cancellation_fee;
                    // } else {
                    //     $text_computed_electric_cancellation_fee = 'No';
                    //     switch ($this->eztpv->event->language_id) {
                    //         case 1:
                    //             $cancelFeeNoneByLanguage = 'None.';
                    //             break;

                    //         case 2:
                    //             $cancelFeeNoneByLanguage = 'Ninguno.';
                    //             break;
                    //     }
                    //     $text_computed_electric_cancellation_fee_short = $cancelFeeNoneByLanguage;
                    // }

                    // $text_computed_electric_cancellation_fee .= ' Early Cancellation Fee.';

                    // $rate_info_electric_date_to = (isset($rate_info_electric->date_to)) ? Carbon::parse($rate_info_electric->date_to, 'America/Chicago')->format('d-m-Y') : null;

                    // $rate_info_electric_green_percentage = (isset($rate_info_electric->green_percentage)) ? $rate_info_electric->green_percentage : 0;

                    // $rate_info_electric_intro_term = (isset($rate_info_electric->intro_term)) ? $rate_info_electric->intro_term : 0;
                    // $rate_info_electric_term_remaining = (isset($rate_info_electric->intro_term)) ? $rate_info_electric->term - $rate_info_electric->intro_term : $rate_info_electric->term;
                    // $rate_info_electric_term_total = (isset($rate_info_electric->intro_term)) ? $rate_info_electric->intro_term + $rate_info_electric->term : $rate_info_electric->term;

                    // $rate_info_electric_name = (isset($rate_info_electric->name)) ? $rate_info_electric->name : null;

                    // $rate_info_electric_program_code = $rate_info_electric->program_code;

                    // if (!isset($rate_info_electric->rate_amount) || 0 == $rate_info_electric->rate_amount) {
                    //     $rate_info_electric->rate_amount = 0.00;
                    // }

                    // $rate_info_electric_rate_amount_in_cents = $rate_info_electric->rate_amount;
                    // $rate_info_electric_daily_fee_in_cents = (isset($rate_info_electric->daily_fee))
                    //     ? $rate_info_electric->daily_fee
                    //     : null;

                    // $rate_info_electric_rate_amount_in_dollars = $rate_info_electric->rate_amount * 0.01;
                    // $rate_info_electric_daily_fee_in_dollars = (isset($rate_info_electric->daily_fee))
                    //     ? $rate_info_electric->daily_fee * 0.01
                    //     : null;

                    // $rate_info_electric_daily_fee = (isset($rate_info_electric->daily_fee))
                    //     ? $rate_info_electric->daily_fee
                    //     : null;

                    // $rate_info_electric_rate_source_code = $rate_info_electric->rate_source_code;

                    $feeCheck = [
                        'None',
                        'Ninguno',
                    ];

                    // $rate_info_electric_custom_data_1 = (isset($rate_info_electric->custom_data_1)) ? $rate_info_electric->custom_data_1 : null;
                    // $rate_info_electric_custom_data_2 = (isset($rate_info_electric->custom_data_2)) ? $rate_info_electric->custom_data_2 : null;
                    // $rate_info_electric_custom_data_3 = (isset($rate_info_electric->custom_data_3)) ? $rate_info_electric->custom_data_3 : null;
                    // $rate_info_electric_custom_data_4 = (isset($rate_info_electric->custom_data_4)) ? $rate_info_electric->custom_data_4 : null;
                    // $rate_info_electric_custom_data_5 = (isset($rate_info_electric->custom_data_5)) ? $rate_info_electric->custom_data_5 : null;

                    // $rate_info_electric_rate_monthly_fee = (isset($rate_info_electric->rate_monthly_fee) ? $rate_info_electric->rate_monthly_fee : null);
                    // $rate_info_electric_transaction_fee = (isset($rate_info_electric->transaction_fee) ? $rate_info_electric->transaction_fee : null);
                    // $rate_info_electric_service_fee = (isset($rate_info_electric->service_fee) ? $rate_info_electric->service_fee : null);
                    // $rate_info_electric_admin_fee = (isset($rate_info_electric->admin_fee) ? $rate_info_electric->admin_fee : null);

                    // Calcualte estimated daily fee and intro daily fee at various usage levels in CENTS and DOLLARS
                    $rate_info_electric_estimated_daily_fee_500_in_cents    = 0;
                    $rate_info_electric_estimated_daily_fee_500_in_dollars  = 0;
                    $rate_info_electric_estimated_daily_fee_1000_in_cents   = 0;
                    $rate_info_electric_estimated_daily_fee_1000_in_dollars = 0;
                    $rate_info_electric_estimated_daily_fee_1500_in_cents   = 0;
                    $rate_info_electric_estimated_daily_fee_1500_in_dollars = 0;
                    $rate_info_electric_estimated_daily_fee_2000_in_cents   = 0;
                    $rate_info_electric_estimated_daily_fee_2000_in_dollars = 0;

                    $rate_info_electric_intro_estimated_daily_fee_500_in_cents    = 0;
                    $rate_info_electric_intro_estimated_daily_fee_500_in_dollars  = 0;
                    $rate_info_electric_intro_estimated_daily_fee_1000_in_cents   = 0;
                    $rate_info_electric_intro_estimated_daily_fee_1000_in_dollars = 0;
                    $rate_info_electric_intro_estimated_daily_fee_1500_in_cents   = 0;
                    $rate_info_electric_intro_estimated_daily_fee_1500_in_dollars = 0;
                    $rate_info_electric_intro_estimated_daily_fee_2000_in_cents   = 0;
                    $rate_info_electric_intro_estimated_daily_fee_2000_in_dollars = 0;

                    // In Cents
                    if (isset($rate_info_electric->daily_fee)) {

                        // Check daily fee currency type. Assume that if value is < 1, then daily fee was entered in dollars
                        // The existing $rate_info_electric_daily_fee_in_dollars assumes that the daily fee was entered in cents
                        // Genie enters theirs in dollars, for example, so that variable cannot be trusted
                        $dailyFee = null;
                        // if($rate_info_electric->daily_fee < 1) { // Daily fee is in dollars, convert to cents
                        //     $dailyFee = $rate_info_electric->daily_fee * 100;
                        // } else { // Daily fee is in cents, use raw value
                        //     $dailyFee = $rate_info_electric->daily_fee;
                        // }

                        $rate_info_electric_estimated_daily_fee_500_in_cents  = $dailyFee * 30 / 500  + $rate_info_electric_calculated_rate_amount_in_cents;
                        $rate_info_electric_estimated_daily_fee_1000_in_cents = $dailyFee * 30 / 1000 + $rate_info_electric_calculated_rate_amount_in_cents;
                        $rate_info_electric_estimated_daily_fee_1500_in_cents = $dailyFee * 30 / 1500 + $rate_info_electric_calculated_rate_amount_in_cents;
                        $rate_info_electric_estimated_daily_fee_2000_in_cents = $dailyFee * 30 / 2000 + $rate_info_electric_calculated_rate_amount_in_cents;

                        $rate_info_electric_intro_estimated_daily_fee_500_in_cents  = $dailyFee * 30 / 500 +  $rate_info_electric_calculated_intro_rate_amount_in_cents;
                        $rate_info_electric_intro_estimated_daily_fee_1000_in_cents = $dailyFee * 30 / 1000 + $rate_info_electric_calculated_intro_rate_amount_in_cents;
                        $rate_info_electric_intro_estimated_daily_fee_1500_in_cents = $dailyFee * 30 / 1500 + $rate_info_electric_calculated_intro_rate_amount_in_cents;
                        $rate_info_electric_intro_estimated_daily_fee_2000_in_cents = $dailyFee * 30 / 2000 + $rate_info_electric_calculated_intro_rate_amount_in_cents;
                    }

                    // In Dollars
                    // if (isset($rate_info_electric->daily_fee)) {

                    //     // Check daily fee currency type. Assume that if value is < 1, then daily fee was entered in dollars
                    //     // The existing $rate_info_electric_daily_fee_in_dollars assumes that the daily fee was entered in cents
                    //     // Genie enters theirs in dollars, for example, so that variable cannot be trusted
                    //     $dailyFee = null;
                    //     // if($rate_info_electric->daily_fee < 1) { // Daily fee is in dollars, use raw value
                    //     //     $dailyFee = $rate_info_electric->daily_fee;
                    //     // } else { // Daily fee is in cents. Convert to dollars
                    //     //     $dailyFee = $rate_info_electric->daily_fee * 0.01;
                    //     // }
                        
                    //     $rate_info_electric_estimated_daily_fee_500_in_dollars  = $dailyFee * 30 / 500  + $rate_info_electric_calculated_rate_amount_in_dollars;
                    //     $rate_info_electric_estimated_daily_fee_1000_in_dollars = $dailyFee * 30 / 1000 + $rate_info_electric_calculated_rate_amount_in_dollars;
                    //     $rate_info_electric_estimated_daily_fee_1500_in_dollars = $dailyFee * 30 / 1500 + $rate_info_electric_calculated_rate_amount_in_dollars;
                    //     $rate_info_electric_estimated_daily_fee_2000_in_dollars = $dailyFee * 30 / 2000 + $rate_info_electric_calculated_rate_amount_in_dollars;

                    //     $rate_info_electric_intro_estimated_daily_fee_500_in_dollars  = $dailyFee * 30 / 500  + $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                    //     $rate_info_electric_intro_estimated_daily_fee_1000_in_dollars = $dailyFee * 30 / 1000 + $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                    //     $rate_info_electric_intro_estimated_daily_fee_1500_in_dollars = $dailyFee * 30 / 1500 + $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                    //     $rate_info_electric_intro_estimated_daily_fee_2000_in_dollars = $dailyFee * 30 / 2000 + $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                    // }

                    $rate_info_electric_final_rate_500kWh = null;
                    $rate_info_electric_final_rate_1000kWh = null;
                    $rate_info_electric_final_rate_1500kWh = null;
                    $rate_info_electric_final_rate_2000kWh = null;
                    $riefr500 = null;
                    $riefr1000 = null;
                    $riefr1500 = null;
                    $riefr2000 = null;
                    $rate_info_electric_estimated_total_bill_500kWh = null;
                    $rate_info_electric_estimated_total_bill_1000kWh = null;
                    $rate_info_electric_estimated_total_bill_1500kWh = null;
                    $rate_info_electric_estimated_total_bill_2000kWh = null;
                    $rieetb500 = null;
                    $rieetb1000 = null;
                    $rieetb1500 = null;
                    $rieetb2000 = null;

                    $rate_info_electric_intro_final_rate_500kWh = null;
                    $rate_info_electric_intro_final_rate_1000kWh = null;
                    $rate_info_electric_intro_final_rate_1500kWh = null;
                    $rate_info_electric_intro_final_rate_2000kWh = null;
                    $rieir500 = null;
                    $rieir1000 = null;
                    $rieir1500 = null;
                    $rieir2000 = null;

                    switch ($this->eztpv->brand_id) {

                        //  Cleansky North-East (Prod and Staging) and Cleansky North-East (Old Staging Server)
                        case '01e58d8e-a2f7-48a4-a43b-bc4fa512d0e0': //
                        case '674b4eb7-f6e4-49c9-ad1b-d92a364d0751': 
                            if (
                                isset($rate_info_electric_rate_amount_in_cents)
                                && null !== $rate_info_electric_rate_amount_in_cents
                            ) {
                                $this->logInfo('rate_info_electric_calculated_rate_amount is ' . $rate_info_electric_calculated_rate_amount, __METHOD__, __LINE__);

                                // Similar to Indra except here we set $riefr2000 and Indra does not...

                                $rate_info_electric_final_rate_500kWh = round($rate_info_electric_calculated_rate_amount * 500, 3);
                                $rate_info_electric_final_rate_1000kWh = round($rate_info_electric_calculated_rate_amount * 1000, 3);
                                $rate_info_electric_final_rate_1500kWh = round($rate_info_electric_calculated_rate_amount * 1500, 3);
                                $riefr500 = $rate_info_electric_final_rate_500kWh;
                                $riefr1000 = $rate_info_electric_final_rate_1000kWh;
                                $riefr1500 = $rate_info_electric_final_rate_1500kWh;
                                $riefr2000 = $rate_info_electric_final_rate_2000kWh;
                            }

                            if (
                                isset($rate_info_electric_calculated_intro_rate_amount)
                                && null !== $rate_info_electric_calculated_intro_rate_amount
                            ) {
                                $this->logInfo('rate_info_electric_calculated_intro_rate_amount is ' . $rate_info_electric_calculated_intro_rate_amount, __METHOD__, __LINE__);

                                $rate_info_electric_estimated_total_bill_500kWh = round($rate_info_electric_calculated_intro_rate_amount * 500, 3);
                                $rate_info_electric_estimated_total_bill_1000kWh = round($rate_info_electric_calculated_intro_rate_amount * 1000, 3);
                                $rate_info_electric_estimated_total_bill_1500kWh = round($rate_info_electric_calculated_intro_rate_amount * 1500, 3);
                                $rate_info_electric_estimated_total_bill_2000kWh = round($rate_info_electric_calculated_intro_rate_amount * 2000, 3);
                                $rieetb500 = $rate_info_electric_estimated_total_bill_500kWh;
                                $rieetb1000 = $rate_info_electric_estimated_total_bill_1000kWh;
                                $rieetb1500 = $rate_info_electric_estimated_total_bill_1500kWh;
                                $rieetb1500 = $rate_info_electric_estimated_total_bill_2000kWh;
                            }

                            break;
                        case '4e65aab8-4dae-48ef-98ee-dd97e16cbce6':
                        case 'eb35e952-04fc-42a9-a47d-715a328125c0':
                            // Indra Energy (staging/production)
                            if (
                                isset($rate_info_electric_rate_amount_in_cents)
                                && null !== $rate_info_electric_rate_amount_in_cents
                            ) {
                                $this->logInfo('rate_info_electric_calculated_rate_amount is ' . $rate_info_electric_calculated_rate_amount, __METHOD__, __LINE__);

                                $rate_info_electric_final_rate_500kWh = round($rate_info_electric_calculated_rate_amount * 500, 3);
                                $rate_info_electric_final_rate_1000kWh = round($rate_info_electric_calculated_rate_amount * 1000, 3);
                                $rate_info_electric_final_rate_1500kWh = round($rate_info_electric_calculated_rate_amount * 1500, 3);
                                $riefr500 = $rate_info_electric_final_rate_500kWh;
                                $riefr1000 = $rate_info_electric_final_rate_1000kWh;
                                $riefr1500 = $rate_info_electric_final_rate_1500kWh;
                            }

                            if (
                                isset($rate_info_electric_calculated_intro_rate_amount)
                                && null !== $rate_info_electric_calculated_intro_rate_amount
                            ) {
                                $this->logInfo('rate_info_electric_calculated_intro_rate_amount is ' . $rate_info_electric_calculated_intro_rate_amount, __METHOD__, __LINE__);

                                $rate_info_electric_estimated_total_bill_500kWh = round($rate_info_electric_calculated_intro_rate_amount * 500, 3);
                                $rate_info_electric_estimated_total_bill_1000kWh = round($rate_info_electric_calculated_intro_rate_amount * 1000, 3);
                                $rate_info_electric_estimated_total_bill_1500kWh = round($rate_info_electric_calculated_intro_rate_amount * 1500, 3);
                                $rieetb500 = $rate_info_electric_estimated_total_bill_500kWh;
                                $rieetb1000 = $rate_info_electric_estimated_total_bill_1000kWh;
                                $rieetb1500 = $rate_info_electric_estimated_total_bill_1500kWh;
                            }

                            break;

                            // RPA (staging/production)
                        case 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e':
                        case '7b08b19d-32a5-4906-a320-6a2c5d6d6372':
                            $rate_info_electric_monthly_fee = (!empty($rate_info_electric_monthly_fee)
                                && ($rate_info_electric_monthly_fee !== 'None' && $rate_info_electric_monthly_fee !== 'Ninguno'))
                                ? $rate_info_electric_monthly_fee
                                : 0;

                            $this->logInfo(
                                'rates.id = ' . $product['rate_id'], __METHOD__, __LINE__
                            );

                            $this->logInfo(
                                'rate_info_electric_rate_amount_in_dollars = '
                                    . $rate_info_electric_rate_amount_in_dollars, __METHOD__, __LINE__
                            );

                            $this->logInfo(
                                'rate_info_electric_monthly_fee = '
                                    . $rate_info_electric_monthly_fee, __METHOD__, __LINE__
                            );

                            if (
                                isset($rate_info_electric_rate_amount_in_dollars)
                                && null !== $rate_info_electric_rate_amount_in_dollars
                                && isset($rate_info_electric_monthly_fee)
                                #&& $rate_info_electric_monthly_fee > 0
                            ) {
                                $this->line($rate_info_electric_rate_amount_in_dollars);
                                $this->line($rate_info_electric_monthly_fee);
                                $rate_info_electric_final_rate_500kWh = round(($rate_info_electric_rate_amount_in_dollars * 500) + $rate_info_electric_monthly_fee, 3);
                                $rate_info_electric_final_rate_1000kWh = round(($rate_info_electric_rate_amount_in_dollars * 1000) + $rate_info_electric_monthly_fee, 3);
                                $rate_info_electric_final_rate_1500kWh = round(($rate_info_electric_rate_amount_in_dollars * 1500) + $rate_info_electric_monthly_fee, 3);
                                $rate_info_electric_final_rate_2000kWh = round(($rate_info_electric_rate_amount_in_dollars * 2000) + $rate_info_electric_monthly_fee, 3);

                                // $rate_info_electric_final_rate_500kWh = (($rate_info_electric_rate_amount_in_dollars * 500) + $rate_info_electric_monthly_fee) / 500;
                                // $rate_info_electric_final_rate_1000kWh = (($rate_info_electric_rate_amount_in_dollars * 1000) + $rate_info_electric_monthly_fee) / 1000;
                                // $rate_info_electric_final_rate_1500kWh = (($rate_info_electric_rate_amount_in_dollars * 1500) + $rate_info_electric_monthly_fee) / 1500;
                                // $rate_info_electric_final_rate_2000kWh = (($rate_info_electric_rate_amount_in_dollars * 2000) + $rate_info_electric_monthly_fee) / 2000;

                                $riefr500 = $rate_info_electric_final_rate_500kWh;
                                $riefr500id = $rate_info_electric_final_rate_500kWh;
                                $riefr1000 = $rate_info_electric_final_rate_1000kWh;
                                $riefr1000id = $rate_info_electric_final_rate_1000kWh;
                                $riefr1500 = $rate_info_electric_final_rate_1500kWh;
                                $riefr1500id = $rate_info_electric_final_rate_1500kWh;
                                $riefr2000 = $rate_info_electric_final_rate_2000kWh;
                                $riefr2000id = $rate_info_electric_final_rate_2000kWh;

                                $this->logInfo('500 = ' . $rate_info_electric_final_rate_500kWh, __METHOD__, __LINE__);
                                $this->logInfo('1000 = ' . $rate_info_electric_final_rate_1000kWh, __METHOD__, __LINE__);
                                $this->logInfo('1500 = ' . $rate_info_electric_final_rate_1500kWh, __METHOD__, __LINE__);
                                $this->logInfo('2000 = ' . $rate_info_electric_final_rate_2000kWh, __METHOD__, __LINE__);
                            }

                            break;

                            // Rushmore Energy (staging/production)
                        case '52f9b7cd-2395-48e9-a534-31f15eebc9d4':
                        case 'faeb80e2-16ce-431c-bb54-1ade365eec16':
                            $rate_info_electric_monthly_fee = (
                                !empty($rate_info_electric_monthly_fee) 
                                && strtolower($rate_info_electric_monthly_fee) !== 'none'
                                && strtolower($rate_info_electric_monthly_fee) !== 'ninguno'
                            )
                                ? $rate_info_electric_monthly_fee
                                : 0;

                            $this->logInfo('Rushmore Energy contract, state is: ' . $state, __METHOD__, __LINE__);

                            if (!in_array(strtolower($state), ['pa'])) {
                                $rate_info_electric_rate_amount_500kWh_in_cents = $rate_info_electric_rate_amount_in_cents;
                                $rate_info_electric_rate_amount_1000kWh_in_cents = $rate_info_electric_rate_amount_in_cents;
                                $rate_info_electric_rate_amount_1500kWh_in_cents = $rate_info_electric_rate_amount_in_cents;
                                $rate_info_electric_rate_amount_2000kWh_in_cents = $rate_info_electric_rate_amount_in_cents;
                            } else if (!empty($rate_info_electric_custom_data_1) && !empty($rate_info_electric_custom_data_2) && !empty($rate_info_electric_custom_data_3)) {
                                $rate_info_electric_rate_amount_500kWh_in_cents = $rate_info_electric_custom_data_1;
                                $rate_info_electric_rate_amount_1000kWh_in_cents = $rate_info_electric_custom_data_2;
                                $rate_info_electric_rate_amount_1500kWh_in_cents = $rate_info_electric_custom_data_3;
                                $rate_info_electric_rate_amount_2000kWh_in_cents = $rate_info_electric_custom_data_3;
                            } else {
                                $brand_id = $this->eztpv->brand_id;
                                $product_name = $product['name'];
                                $program_code = $rate_info_electric->program_code;
                                $slackMsg = [
                                    "Rushmore contract misconfiguration",
                                    "```",
                                    "Brand id: $brand_id",
                                    "Product name: $product_name",
                                    "Program code: $program_code",
                                    "rate_info_electric_rate_amount_in_cents: $rate_info_electric_rate_amount_in_cents",
                                    "rate_info_electric_custom_data_1: $rate_info_electric_custom_data_1",
                                    "rate_info_electric_custom_data_2: $rate_info_electric_custom_data_2",
                                    "rate_info_electric_custom_data_3: $rate_info_electric_custom_data_3",
                                    "```",
                                    "All three custom fields must be set or none at all"
                                ];
                                SendTeamMessage('triage', implode("\n", $slackMsg));
                                return [
                                    'error',
                                    'Contract Generation Failed: Rushmore contract misconfiguration. Values rate_info_electric_custom_data_1, rate_info_electric_custom_data_2, rate_info_electric_custom_data_3 should all be set or none at all.',
                                ];
                            }

                            $this->logInfo('rate_info_electric_rate_amount_in_cents = ' . $rate_info_electric_rate_amount_in_cents, __METHOD__, __LINE__);
                            $this->logInfo('rate_info_electric_rate_amount_500kWh_in_cents = ' . $rate_info_electric_custom_data_1, __METHOD__, __LINE__);
                            $this->logInfo('rate_info_electric_rate_amount_1000kWh_in_cents = ' . $rate_info_electric_custom_data_2, __METHOD__, __LINE__);
                            $this->logInfo('rate_info_electric_rate_amount_1500kWh_in_cents = ' . $rate_info_electric_custom_data_3, __METHOD__, __LINE__);
                            $this->logInfo('rate_info_electric_rate_amount_2000kWh_in_cents = ' . $rate_info_electric_custom_data_3, __METHOD__, __LINE__);
                            $this->logInfo('rate_info_electric_monthly_fee = ' . $rate_info_electric_monthly_fee, __METHOD__, __LINE__);

                            if (!empty($rate_info_electric_rate_amount_500kWh_in_cents) && !empty($rate_info_electric_rate_amount_1000kWh_in_cents) && !empty($rate_info_electric_rate_amount_1500kWh_in_cents) && !empty($rate_info_electric_rate_amount_2000kWh_in_cents)) {
                                $rate_info_electric_final_rate_500kWh = $rate_info_electric_rate_amount_500kWh_in_cents + (($rate_info_electric_monthly_fee * 100) / 500);
                                $rate_info_electric_final_rate_1000kWh = $rate_info_electric_rate_amount_1000kWh_in_cents + (($rate_info_electric_monthly_fee * 100) / 1000);
                                $rate_info_electric_final_rate_1500kWh = $rate_info_electric_rate_amount_1500kWh_in_cents + (($rate_info_electric_monthly_fee * 100) / 1500);
                                $rate_info_electric_final_rate_2000kWh = $rate_info_electric_rate_amount_2000kWh_in_cents + (($rate_info_electric_monthly_fee * 100) / 2000);

                                $riefr500 = $rate_info_electric_final_rate_500kWh;
                                $riefr1000 = $rate_info_electric_final_rate_1000kWh;
                                $riefr1500 = $rate_info_electric_final_rate_1500kWh;
                                $riefr2000 = $rate_info_electric_final_rate_2000kWh;

                                $this->logInfo('riefr500 = ' . $riefr500, __METHOD__, __LINE__);
                                $this->logInfo('riefr1000 = ' . $riefr1000, __METHOD__, __LINE__);
                                $this->logInfo('riefr1500 = ' . $riefr1500, __METHOD__, __LINE__);
                                $this->logInfo('riefr2000 = ' . $riefr2000, __METHOD__, __LINE__);

                                $rate_info_electric_final_rate_500kWh_in_cents = $rate_info_electric_final_rate_500kWh;
                                $rate_info_electric_final_rate_500kWh_in_dollars = $rate_info_electric_final_rate_500kWh_in_cents / 100;
                                $rate_info_electric_final_rate_1000kWh_in_cents = $rate_info_electric_final_rate_1000kWh;
                                $rate_info_electric_final_rate_1000kWh_in_dollars = $rate_info_electric_final_rate_1000kWh_in_cents / 100;
                                $rate_info_electric_final_rate_1500kWh_in_cents = $rate_info_electric_final_rate_1500kWh;
                                $rate_info_electric_final_rate_1500kWh_in_dollars = $rate_info_electric_final_rate_1500kWh_in_cents / 100;
                                $rate_info_electric_final_rate_2000kWh_in_cents = $rate_info_electric_final_rate_2000kWh;
                                $rate_info_electric_final_rate_2000kWh_in_dollars = $rate_info_electric_final_rate_2000kWh_in_cents / 100;

                                $riefr500ic = $rate_info_electric_final_rate_500kWh_in_cents;
                                $riefr500id = $rate_info_electric_final_rate_500kWh_in_dollars;
                                $riefr1000ic = $rate_info_electric_final_rate_1000kWh_in_cents;
                                $riefr1000id = $rate_info_electric_final_rate_1000kWh_in_dollars;
                                $riefr1500ic = $rate_info_electric_final_rate_1500kWh_in_cents;
                                $riefr1500id = $rate_info_electric_final_rate_1500kWh_in_dollars;
                                $riefr2000ic = $rate_info_electric_final_rate_2000kWh_in_cents;
                                $riefr2000id = $rate_info_electric_final_rate_2000kWh_in_dollars;

                                $this->logInfo('riefr500ic = ' . $riefr500ic, __METHOD__, __LINE__);
                                $this->logInfo('riefr500id = ' . $riefr500id, __METHOD__, __LINE__);
                                $this->logInfo('riefr1000ic = ' . $riefr1000ic, __METHOD__, __LINE__);
                                $this->logInfo('riefr1000id = ' . $riefr1000id, __METHOD__, __LINE__);
                                $this->logInfo('riefr1500ic = ' . $riefr1500ic, __METHOD__, __LINE__);
                                $this->logInfo('riefr1500id = ' . $riefr1500id, __METHOD__, __LINE__);
                                $this->logInfo('riefr2000ic = ' . $riefr2000ic, __METHOD__, __LINE__);
                                $this->logInfo('riefr2000id = ' . $riefr2000id, __METHOD__, __LINE__);

                                $rate_info_electric_estimated_total_bill_500kWh = ((($rate_info_electric_rate_amount_500kWh_in_cents * 500) / 100) + $rate_info_electric_monthly_fee);
                                $rate_info_electric_estimated_total_bill_1000kWh = (($rate_info_electric_rate_amount_1000kWh_in_cents * 1000) / 100) + $rate_info_electric_monthly_fee;
                                $rate_info_electric_estimated_total_bill_1500kWh = (($rate_info_electric_rate_amount_1500kWh_in_cents * 1500) / 100) + $rate_info_electric_monthly_fee;
                                $rate_info_electric_estimated_total_bill_2000kWh = (($rate_info_electric_rate_amount_2000kWh_in_cents * 2000) / 100) + $rate_info_electric_monthly_fee;

                                $rieetb500 = $rate_info_electric_estimated_total_bill_500kWh;
                                $rieetb1000 = $rate_info_electric_estimated_total_bill_1000kWh;
                                $rieetb1500 = $rate_info_electric_estimated_total_bill_1500kWh;
                                $rieetb2000 = $rate_info_electric_estimated_total_bill_2000kWh;

                                $this->logInfo('rieetb500 = ' . $rieetb500, __METHOD__, __LINE__);
                                $this->logInfo('rieetb1000 = ' . $rieetb1000, __METHOD__, __LINE__);
                                $this->logInfo('rieetb1500 = ' . $rieetb1500, __METHOD__, __LINE__);
                                $this->logInfo('rieetb2000 = ' . $rieetb2000, __METHOD__, __LINE__);
                            }
                            break;

                        case 'c03d58ed-1bb0-4e35-9e11-94c1e3bd59cc': // Clearview Energy (staging and production)
                            if (
                                isset($rate_info_electric_rate_amount_in_dollars)
                                && null !== $rate_info_electric_rate_amount_in_dollars
                            ) {
                                if (
                                    isset($rate_info_electric_monthly_fee)
                                    && !in_array($rate_info_electric_monthly_fee, $feeCheck)
                                    && $rate_info_electric_monthly_fee > 0
                                ) {
                                    $this->logInfo('rate_info_electric_rate_amount_in_dollars is ' . $rate_info_electric_rate_amount_in_dollars, __METHOD__, __LINE__);
                                    $this->logInfo('rate_info_electric_monthly_fee is ' . $rate_info_electric_monthly_fee, __METHOD__, __LINE__);

                                    $rate_info_electric_final_rate_500kWh = round(
                                        (($rate_info_electric_rate_amount_in_dollars * 500) + $rate_info_electric_monthly_fee) / 500,
                                        4
                                    );

                                    $rate_info_electric_final_rate_1000kWh = round(
                                        (($rate_info_electric_rate_amount_in_dollars * 1000) + $rate_info_electric_monthly_fee) / 1000,
                                        4
                                    );

                                    $rate_info_electric_final_rate_1500kWh = round(
                                        (($rate_info_electric_rate_amount_in_dollars * 1500) + $rate_info_electric_monthly_fee) / 1500,
                                        4
                                    );

                                    $rate_info_electric_final_rate_2000kWh = round(
                                        (($rate_info_electric_rate_amount_in_dollars * 2000) + $rate_info_electric_monthly_fee) / 2000,
                                        4
                                    );

                                    $this->logInfo('((' . $rate_info_electric_rate_amount_in_dollars . ' * 500) + ' . $rate_info_electric_monthly_fee . ') / 500', __METHOD__, __LINE__);
                                    $this->logInfo('500 = ' . $rate_info_electric_final_rate_500kWh, __METHOD__, __LINE__);

                                    $this->logInfo('((' . $rate_info_electric_rate_amount_in_dollars . ' * 1000) + ' . $rate_info_electric_monthly_fee . ') / 1000', __METHOD__, __LINE__);
                                    $this->logInfo('1000 = ' . $rate_info_electric_final_rate_1000kWh, __METHOD__, __LINE__);

                                    $this->logInfo('((' . $rate_info_electric_rate_amount_in_dollars . ' * 1500) + ' . $rate_info_electric_monthly_fee . ') / 1500', __METHOD__, __LINE__);
                                    $this->logInfo('1500 = ' . $rate_info_electric_final_rate_1500kWh, __METHOD__, __LINE__);

                                    $this->logInfo('((' . $rate_info_electric_rate_amount_in_dollars . ' * 2000) + ' . $rate_info_electric_monthly_fee . ') / 2000', __METHOD__, __LINE__);
                                    $this->logInfo('2000 = ' . $rate_info_electric_final_rate_2000kWh, __METHOD__, __LINE__);

                                    // $rate_info_electric_final_rate_500kWh = round($rate_info_electric_rate_amount_in_dollars + (($rate_info_electric_monthly_fee * 100) / 500), 2);
                                    // $rate_info_electric_final_rate_1000kWh = round($rate_info_electric_rate_amount_in_dollars + (($rate_info_electric_monthly_fee * 100) / 1000), 2);
                                    // $rate_info_electric_final_rate_1500kWh = round($rate_info_electric_rate_amount_in_dollars + (($rate_info_electric_monthly_fee * 100) / 1500), 2);
                                    // $rate_info_electric_final_rate_2000kWh = round($rate_info_electric_rate_amount_in_dollars + (($rate_info_electric_monthly_fee * 100) / 2000), 2);

                                    $rate_info_electric_estimated_total_bill_500kWh = round(((($rate_info_electric_rate_amount_in_dollars * 500) / 100) + $rate_info_electric_monthly_fee), 2);
                                    $rate_info_electric_estimated_total_bill_1000kWh = round((($rate_info_electric_rate_amount_in_dollars * 1000) / 100) + $rate_info_electric_monthly_fee, 2);
                                    $rate_info_electric_estimated_total_bill_1500kWh = round((($rate_info_electric_rate_amount_in_dollars * 1500) / 100) + $rate_info_electric_monthly_fee, 2);
                                } else {
                                    $rate_info_electric_final_rate_500kWh = $rate_info_electric_rate_amount_in_dollars;
                                    $rate_info_electric_final_rate_1000kWh = $rate_info_electric_rate_amount_in_dollars;
                                    $rate_info_electric_final_rate_1500kWh = $rate_info_electric_rate_amount_in_dollars;
                                    $rate_info_electric_final_rate_2000kWh = $rate_info_electric_rate_amount_in_dollars;


                                    $rate_info_electric_estimated_total_bill_500kWh = round(((($rate_info_electric_rate_amount_in_dollars * 500) / 100)), 2);
                                    $rate_info_electric_estimated_total_bill_1000kWh = round((($rate_info_electric_rate_amount_in_dollars * 1000) / 100), 2);
                                    $rate_info_electric_estimated_total_bill_1500kWh = round((($rate_info_electric_rate_amount_in_dollars * 1500) / 100), 2);
                                }

                                $riefr500 = $rate_info_electric_final_rate_500kWh;
                                $riefr1000 = $rate_info_electric_final_rate_1000kWh;
                                $riefr1500 = $rate_info_electric_final_rate_1500kWh;
                                $riefr2000 = $rate_info_electric_final_rate_2000kWh;

                                $rieetb500 = $rate_info_electric_estimated_total_bill_500kWh;
                                $rieetb1000 = $rate_info_electric_estimated_total_bill_1000kWh;
                                $rieetb1500 = $rate_info_electric_estimated_total_bill_1500kWh;
                            }
                            if (
                                isset($rate_info_electric_calculated_intro_rate_amount_in_dollars)
                                && $rate_info_electric_calculated_intro_rate_amount_in_dollars > 0
                            ) {
                                if ($rate_info_electric_monthly_fee > 0) {
                                    $this->logInfo('rate_info_electric_calculated_intro_rate_amount_in_dollars is ' . $rate_info_electric_calculated_intro_rate_amount_in_dollars, __METHOD__, __LINE__);
                                    $this->logInfo('rate_info_electric_monthly_fee is ' . $rate_info_electric_monthly_fee, __METHOD__, __LINE__);

                                    $rate_info_electric_intro_final_rate_500kWh = round(
                                        (($rate_info_electric_calculated_intro_rate_amount_in_dollars * 500) + $rate_info_electric_monthly_fee) / 500,
                                        4
                                    );

                                    $rieir500 = $rate_info_electric_intro_final_rate_500kWh;

                                    $rate_info_electric_intro_final_rate_1000kWh = round(
                                        (($rate_info_electric_calculated_intro_rate_amount_in_dollars * 1000) + $rate_info_electric_monthly_fee) / 1000,
                                        4
                                    );

                                    $rieir1000 = $rate_info_electric_intro_final_rate_1000kWh;

                                    $rate_info_electric_intro_final_rate_1500kWh = round(
                                        (($rate_info_electric_calculated_intro_rate_amount_in_dollars * 1500) + $rate_info_electric_monthly_fee) / 1500,
                                        4
                                    );

                                    $rieir1500 = $rate_info_electric_intro_final_rate_1500kWh;

                                    $rate_info_electric_intro_final_rate_2000kWh = round(
                                        (($rate_info_electric_calculated_intro_rate_amount_in_dollars * 2000) + $rate_info_electric_monthly_fee) / 2000,
                                        4
                                    );

                                    $rieir2000 = $rate_info_electric_intro_final_rate_2000kWh;

                                    $this->logInfo('((' . $rate_info_electric_calculated_intro_rate_amount_in_dollars . ' * 500) + ' . $rate_info_electric_monthly_fee . ') / 500', __METHOD__, __LINE__);
                                    $this->logInfo('500 = ' . $rate_info_electric_intro_final_rate_500kWh, __METHOD__, __LINE__);

                                    $this->logInfo('((' . $rate_info_electric_calculated_intro_rate_amount_in_dollars . ' * 1000) + ' . $rate_info_electric_monthly_fee . ') / 1000', __METHOD__, __LINE__);
                                    $this->logInfo('1000 = ' . $rate_info_electric_intro_final_rate_1000kWh, __METHOD__, __LINE__);

                                    $this->logInfo('((' . $rate_info_electric_calculated_intro_rate_amount_in_dollars . ' * 1500) + ' . $rate_info_electric_monthly_fee . ') / 1500', __METHOD__, __LINE__);
                                    $this->logInfo('1500 = ' . $rate_info_electric_intro_final_rate_1500kWh, __METHOD__, __LINE__);

                                    $this->logInfo('((' . $rate_info_electric_calculated_intro_rate_amount_in_dollars . ' * 2000) + ' . $rate_info_electric_monthly_fee . ') / 2000', __METHOD__, __LINE__);
                                    $this->logInfo('2000 = ' . $rate_info_electric_intro_final_rate_2000kWh, __METHOD__, __LINE__);
                                } else {
                                    $rate_info_electric_intro_final_rate_500kWh = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rate_info_electric_intro_final_rate_1000kWh = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rate_info_electric_intro_final_rate_1500kWh = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rate_info_electric_intro_final_rate_2000kWh = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rieir500 = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rieir1000 = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rieir1500 = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                    $rieir2000 = $rate_info_electric_calculated_intro_rate_amount_in_dollars;
                                }
                            }
                            break;

                        default:
                            $genie_brand_id_array = [self::BRAND_IDS['idt_energy']['production'], self::BRAND_IDS['idt_energy']['staging'], self::BRAND_IDS['residents_energy']['production'],
                                    self::BRAND_IDS['residents_energy']['staging'], self::BRAND_IDS['townsquare_energy']['production'], self::BRAND_IDS['townsquare_energy']['staging']];

                            // If its a Genie Brand we need to apply some special logic
                            // if (in_array($this->eztpv->brand_id, $genie_brand_id_array)) {
                            //     // Intro Rates are used to display the Final Rates in the Contracts for Rate Type ID of 3 which is a 'Tiered' rate
                            //     if (in_array(strtolower($state), ['il']) && $rate_info_electric && $rate_info_electric->rate_type_id == 3) {
                            //         // Amern IL, and ComEd IL Utilities under IDT
                            //         if (in_array($utility->name, ['Ameren Energy', 'COMED'])) {
                            //             $this->info('Genie Special Handling, state is: ' . $state . 'for Utility: ' . $utility->name);
                            //             $this->logInfo('Genie Special Handling, state is: ' . $state . 'for Utility: ' . $utility->name,  __METHOD__, __LINE__);
        
                            //             // Final Rate to be displayed in the Contract will include the Daily Fees (The Daily Fee, although labeled as Cents, is in Dollar Values) as example "11.76 cents"
                            //             $rate_info_electric_intro_final_rate_500kWh  = $rate_info_electric_daily_fee_in_cents * 30 / 500  + $rate_info_electric_calculated_rate_amount_in_dollars;
                            //             $rate_info_electric_intro_final_rate_1000kWh = $rate_info_electric_daily_fee_in_cents * 30 / 1000 + $rate_info_electric_calculated_rate_amount_in_dollars;
                            //             $rate_info_electric_intro_final_rate_1500kWh = $rate_info_electric_daily_fee_in_cents * 30 / 1500 + $rate_info_electric_calculated_rate_amount_in_dollars;
                            //         }
                            //     }
                            // }

                            if (
                                isset($rate_info_electric_rate_amount_in_dollars)
                                && null !== $rate_info_electric_rate_amount_in_dollars
                                && isset($rate_info_electric_daily_fee_in_dollars)
                                && null !== $rate_info_electric_daily_fee_in_dollars
                            ) {
                                $rate_info_electric_final_rate_500kWh = (($rate_info_electric_rate_amount_in_dollars * 500) + (30 * $rate_info_electric_daily_fee_in_dollars)) / 500;
                                $rate_info_electric_final_rate_1000kWh = (($rate_info_electric_rate_amount_in_dollars * 1000) + (30 * $rate_info_electric_daily_fee_in_dollars)) / 1000;
                                $rate_info_electric_final_rate_1500kWh = (($rate_info_electric_rate_amount_in_dollars * 1500) + (30 * $rate_info_electric_daily_fee_in_dollars)) / 1500;
                                $rate_info_electric_final_rate_2000kWh = (($rate_info_electric_rate_amount_in_dollars * 2000) + (30 * $rate_info_electric_daily_fee_in_dollars)) / 2000;
                                break;
                            }

                            // Total estimated bill vars
                            // This will handle total estimated bill where we don't have 
                            // brand specific logic for that value.
                            // The estimate bill will be calculated as follows:
                            // (rate * estimated usage) + (total of fees)
                            if (
                                isset($rate_info_electric_rate_amount_in_dollars)
                                && null !== $rate_info_electric_rate_amount_in_dollars
                            ) {
                                // Calculate total fees
                                $total_electric_fees = 0;

                                // Add Daily fee?
                                if (isset($rate_info_electric_daily_fee_in_dollars) && null !== $rate_info_electric_daily_fee_in_dollars) {
                                    $total_electric_fees .= (30 * $rate_info_electric_daily_fee_in_dollars);
                                }

                                // Add Monthly fee?
                                if (isset($rate_info_electric_rate_monthly_fee) && null !== $rate_info_electric_rate_monthly_fee) {
                                    $total_electric_fees .= $rate_info_electric_rate_monthly_fee;
                                }

                                // Add Transaction Fee?
                                if (isset($rate_info_electric_transaction_fee) && null !== $rate_info_electric_transaction_fee) {
                                    $total_electric_fees .= $rate_info_electric_transaction_fee;
                                }

                                // Add Service Fee?
                                if (isset($rate_info_electric_service_fee) && null !== $rate_info_electric_service_fee) {
                                    $total_electric_fees .= $rate_info_electric_service_fee;
                                }

                                // Add Admin Fee?
                                if (isset($rate_info_electric_admin_fee) && null !== $rate_info_electric_admin_fee) {
                                    $total_electric_fees .= $rate_info_electric_admin_fee;
                                }

                                // Calculate estimated total bill at usage levels
                                $rate_info_electric_estimated_total_bill_500kWh  = (($rate_info_electric_rate_amount_in_cents * 500) / 100) + $total_electric_fees;
                                $rate_info_electric_estimated_total_bill_1000kWh = (($rate_info_electric_rate_amount_in_cents * 1000) / 100) + $total_electric_fees;
                                $rate_info_electric_estimated_total_bill_1500kWh = (($rate_info_electric_rate_amount_in_cents * 1500) / 100) + $total_electric_fees;
                                $rate_info_electric_estimated_total_bill_2000kWh = (($rate_info_electric_rate_amount_in_cents * 2000) / 100) + $total_electric_fees;
                            }
                    }

                    $computed_electric_other_fees = '';
                    if (!isset($rate_info_electric_daily_fee) || 0 == $rate_info_electric_daily_fee) {
                        $computed_electric_other_fees = '\rNone.';
                    } else {
                        $computed_electric_other_fees = 'You will be charged a fee of ' . $rate_info_electric_daily_fee_in_cents . utf8_decode('¢') . '/day in addition to the charges for your metered electricity usage.\rAverage Monthly Usage     |     500kWh     |     1000kWh     |     2000kWh \rAverage Price per kWh      |     $' . $rate_info_electric_final_rate_500kWh . '     |     $' . $rate_info_electric_final_rate_1000kWh . '       |     $' . $rate_info_electric_final_rate_2000kWh;
                    }

                    $computed_electric_rate_type_plus_green = '';

                    // switch ($rate_info_electric->rate_type_id) {
                    //     case '1':
                    //     case 1:
                    //         $rate_info_electric_rate_amount = $rate_info_electric->rate_amount;
                    //         $electric_fixed_rate_checkbox = 'Yes';
                    //         $computed_electric_rate_type_plus_green .= 'Fixed';
                    //         break;

                    //     case '2':
                    //     case 2:
                    //         $rate_info_electric_rate_amount = $rate_info_electric->rate_amount;
                    //         $electric_variable_rate_checkbox = 'Yes';
                    //         $computed_electric_rate_type_plus_green .= 'Variable';
                    //         break;

                    //     case '3':
                    //     case 3:
                    //         $rate_info_electric_rate_amount = $rate_info_electric->intro_rate_amount;
                    //         $rate_info_electric_tiered_rate_amount = $rate_info_electric->rate_amount;
                    //         $electric_tiered_rate_checkbox = 'Yes';
                    //         $computed_electric_rate_type_plus_green .= 'Tiered';
                    //         break;
                    // }

                    // $rate_info_electric_rate_uom = (isset($rate_info_electric->rate_uom->uom)) ? $rate_info_electric->rate_uom->uom : null;

                    $computed_electric_green_product = 'No';
                    // if (0 != $rate_info_electric_green_percentage) {
                    //     $computed_electric_green_product = 'Yes';
                    //     $computed_electric_rate_type_plus_green .= ' Green';
                    // }

                    // $rate_info_electric_term = $rate_info_electric->term;
                    if (
                        isset($rate_info_electric_term)
                        && $rate_info_electric_term != 0
                    ) {
                        // $end_date_electric = $eztpv->created_at->add($rate_info_electric_term, $rate_info_electric->term_type->term_type . 's')->format('m-d-Y');
                        // $end_date_electric_month_year = $eztpv->created_at->add($rate_info_electric_term, $rate_info_electric->term_type->term_type . 's')->format('m-Y');
                        // Formula should be Today's date + Intro Term (1 Month) + Term (13 Months)
                        // $renewal_date_electric = $eztpv->created_at->add($rate_info_electric_term + $rate_info_electric_intro_term, $rate_info_electric->term_type->term_type . 's')->format('m-d-Y');
                        // if (2 == $this->eztpv->event->language_id) {
                        //     $rate_info_electric_term_uom = 'meses';
                        //     switch ($rate_info_electric->term_type->term_type) {
                        //         case 'day':
                        //             $rate_info_electric->term_type->term_type = 'dias';
                        //             break;
                        //         case 'week':
                        //             $rate_info_electric->term_type->term_type = 'semanas';
                        //             break;
                        //         case 'year':
                        //             $rate_info_electric->term_type->term_type = 'años';
                        //             break;
                        //         default:
                        //             $rate_info_electric->term_type->term_type = 'meses';
                        //             break;
                        //     }
                        //     $rate_info_electric_term_uom = $rate_info_electric->term_type->term_type;
                        // } else {
                        //     $rate_info_electric_term_uom = 'month';
                        //     if (
                        //         isset($rate_info_electric)
                        //         && isset($rate_info_electric->term_type)
                        //         && isset($rate_info_electric->term_type->term_type)
                        //     ) {
                        //         $rate_info_electric_term_uom = $rate_info_electric->term_type->term_type . 's';
                        //     }
                        // }
                    }

                    $start_date_electric = $date;
                    // $state_query = State::where($product['service_state']);
                    $state_electric = $state_query->state_abbrev;
                    $zip_electric = $product['service_zip'];

                    if (isset($product['toc'])) {
                        $toc_electric = strip_tags($product['toc']);
                    } else {
                        $toc_electric = '';
                    }
                }

                if ($event_type && 'Natural Gas' == $event_type->event_type) {
                    $this->info('Gas product');
                    $utility_rate_unit = 'therm';
                    $utility_rate_unit_gas = 'therm';
                    $account_number_gas = $product['account_number'];
                    if (isset($utility_account_number_all)) {
                        $utility_account_number_all .= '\r' . $product['account_number'];
                    } else {
                        $utility_account_number_all = $product['account_number'];
                    }

                    // set Gas promo
                    $rate_info_gas_promo_code = $product['promotion']['code'];
                    $rate_info_gas_promo_type = $product['promotion']['type'];
                    $rate_info_gas_promo_key = $product['promotion']['key'];
                    switch ($this->eztpv->event->language_id) {
                        case 1:
                            $rate_info_gas_promo_text = $product['promotion']['text_english'];
                            $rate_info_gas_promo_name = $product['promotion']['name_english'];
                            break;
                        case 2:
                            $rate_info_gas_promo_text = !empty($product['promotion']['text_spanish']) ? $product['promotion']['text_spanish'] : $product['promotion']['text_english'];
                            $rate_info_gas_promo_name = !empty($product['promotion']['name_spanish']) ? $product['promotion']['name_spanish'] : $product['promotion']['name_english'];
                            break;
                    }
                    $rate_info_gas_promo_reward = $product['promotion']['reward'];

                    $address_gas = $product['service_address1']
                        . ' ' . $product['service_address2'];
                    $city_gas = $product['service_city'];
                    $event_type_gas = 'Yes';
                    $utility_gas = Utility::select(
                        'utilities.name',
                        'utility_account_types.account_type',
                        'utilities.customer_service',
                        'utilities.website',
                        'utilities.address1',
                        'utilities.address2',
                        'utilities.address3',
                        'utilities.city',
                        'utilities.state',
                        'utilities.zip'
                    )->leftJoin(
                        'utility_supported_fuels',
                        'utility_supported_fuels.utility_id',
                        'utilities.id'
                    )->leftJoin(
                        'utility_account_identifiers',
                        'utility_account_identifiers.utility_id',
                        'utility_supported_fuels.id'
                    )->leftJoin(
                        'utility_account_types',
                        'utility_account_identifiers.utility_account_type_id',
                        'utility_account_types.id'
                    )->where(
                        'utility_supported_fuels.id',
                        $product['utility_id']
                    )->whereNull(
                        'utility_account_identifiers.deleted_at'
                    )->first();

                    if (isset($utility_gas)) {
                        $utility_gas_primary_identifier = $utility_gas->account_type;
                        if (
                            isset($utility_name_all)
                            && $utility_name_all !== htmlspecialchars($utility_gas->name)
                        ) {
                            $utility_name_all .= ', ' . htmlspecialchars($utility_gas->name);
                        } else {
                            $utility_name_all = htmlspecialchars($utility_gas->name);
                        }

                        if (
                            isset($utility_gas->customer_service)
                        ) {
                            $utility_gas_customer_service = str_replace('+1', '', $utility_gas->customer_service);
                            $utility_gas_customer_service_formatted = '(' . substr($utility_gas_customer_service, 0, 3) . ') ' . substr($utility_gas_customer_service, 3, 3) . '-' . substr($utility_gas_customer_service, 6, 4);
                            $ugcs = $utility_gas_customer_service;
                            $ugcsf = $utility_gas_customer_service_formatted;
                        } else {
                            $utility_gas_customer_service = null;
                            $utility_gas_customer_service_formatted = null;
                            $ugcs = null;
                            $ugcsf = null;
                        }

                        $ug_address1 = (isset($utility_gas->address1)) ? $utility_gas->address1 : null;
                        $ug_address2 = (isset($utility_gas->address2)) ? $utility_gas->address2 : null;
                        $ug_address3 = (isset($utility_gas->address3)) ? $utility_gas->address3 : null;
                        $utility_gas_city = (isset($utility_gas->city)) ? $utility_gas->city : null;
                        $utility_gas_state = (isset($utility_gas->state)) ? $utility_gas->state : null;
                        $utility_gas_zip = (isset($utility_gas->zip)) ? $utility_gas->zip : null;
                        $utility_gas_address = $ug_address1 . ' ' . $ug_address2 . ' ' . $ug_address3;
                        $utility_gas_address_full = $utility_gas_address . ', ' . $utility_gas_city . ', ' . $utility_gas_state . ', ' . $utility_gas_zip;
                        $utility_gas_website = (isset($utility_gas->website)) ? $utility_gas->website : null;
                        $utility_gas_name = (isset($utility_gas->name)) ? htmlentities($utility_gas->name) : null;
                    }

                    $company_name_gas = $utility_gas->name;

                    $utility_account_types_gas = UtilityAccountType::select(
                        'utility_account_types.id',
                        'utility_account_types.account_type'
                    )->whereNull(
                        'utility_account_types.deleted_at'
                    )->get();

                    if (
                        isset($utility_account_types_gas)
                        && count($utility_account_types_gas) > 0
                    ) {
                        foreach ($utility_account_types_gas as $type) {
                            $type->account_type = str_replace(
                                ' ',
                                '_',
                                strtolower($type->account_type)
                            );

                            ${'utility_account_type_gas_' . $type->account_type} = '';

                            $identifier = EventProductIdentifier::select(
                                'identifier'
                            )
                                ->where(
                                    'event_product_id',
                                    $product['id']
                                )
                                ->where(
                                    'utility_account_type_id',
                                    $type->id
                                )
                                ->first();

                            if ($identifier) {
                                ${'utility_account_type_gas_' . $type->account_type} = $identifier->identifier;
                            }
                        }
                    }

                    switch ($utility_gas->account_type) {
                        case 'Account Number':
                            $gas_utility_account_type_account_number = 'Yes';
                            break;

                        case 'Customer Number':
                            $gas_utility_account_type_customer_number = 'Yes';
                            break;
                    }

                    $gas_fixed_rate_checkbox = 'Off';
                    $gas_variable_rate_checkbox = 'Off';
                    $gas_tiered_rate_checkbox = 'Off';
                    $fullname_gas = $product['bill_first_name']
                        . ', ' . $product['bill_last_name'];
                    $rate_info_gas = Rate::select(
                        'products.daily_fee',
                        'products.green_percentage',
                        'products.id AS product_id',
                        'products.intro_term',
                        'products.monthly_fee',
                        'products.name',
                        'products.rate_type_id',
                        'products.term',
                        'products.term_type_id',
                        'rates.cancellation_fee',
                        'rates.date_to',
                        'rates.intro_rate_amount',
                        'rates.program_code',
                        'rates.rate_amount',
                        'rates.rate_currency_id',
                        'rates.rate_monthly_fee',
                        'rates.custom_data_1',
                        'rates.custom_data_2',
                        'rates.custom_data_3',
                        'rates.custom_data_4',
                        'rates.custom_data_5',
                        'rates.id',
                        'rates.rate_uom_id',
                        'rates.rate_source_code'
                    )
                        ->leftJoin(
                            'products',
                            'rates.product_id',
                            'products.id'
                        )
                        ->where(
                            'rates.id',
                            $product['rate_id']
                        )
                        ->with([
                            'rate_uom',
                            'term_type',
                        ])
                        ->withTrashed()
                        ->first();

                    if ($this->option('debug')) {
                        $this->info('rate_info_gas assigned ' . print_r($rate_info_gas->toArray(), true));
                    }

                    if (isset($rate_info_gas->monthly_fee)) {
                        $rate_info_gas_monthly_fee = $rate_info_gas->monthly_fee;
                    } elseif (isset($rate_info_gas->rate_monthly_fee)) {
                        $rate_info_gas_monthly_fee = $rate_info_gas->rate_monthly_fee;
                    } else {
                        switch ($this->eztpv->event->language_id) {
                            case 2:
                                $rate_info_gas_monthly_fee = 'Ninguno';
                                break;

                            case 1:
                            default:
                                $rate_info_gas_monthly_fee = 'None';
                                break;
                        }
                    }

                    switch ($rate_info_gas->rate_currency_id) {
                        case 1:
                            // cents
                            $rate_info_gas_calculated_rate_amount = $rate_info_gas->rate_amount;
                            $rate_info_gas_calculated_intro_rate_amount = $rate_info_gas->intro_rate_amount;

                            $rate_info_gas_calculated_intro_rate_amount_in_cents = $rate_info_gas->intro_rate_amount;
                            $rate_info_gas_calculated_intro_rate_amount_in_dollars = $rate_info_gas->intro_rate_amount * 0.01;

                            $rate_info_gas_calculated_rate_amount_in_cents = $rate_info_gas->rate_amount;
                            $rate_info_gas_calculated_rate_amount_in_dollars = $rate_info_gas->rate_amount * 0.01;

                            switch ($this->eztpv->event->language_id) {
                                case 2:
                                    $rate_info_gas_currency = 'centavos';
                                    break;

                                case 1:
                                default:
                                    $rate_info_gas_currency = 'cents';
                                    break;
                            }
                            break;

                        case 2:
                            // dollars
                            $rate_info_gas_calculated_rate_amount = $rate_info_gas->rate_amount;
                            $rate_info_gas_calculated_intro_rate_amount = $rate_info_gas->intro_rate_amount;

                            $rate_info_gas_calculated_intro_rate_amount_in_cents = $rate_info_gas->intro_rate_amount * 100;
                            $rate_info_gas_calculated_intro_rate_amount_in_dollars = $rate_info_gas->intro_rate_amount;

                            $rate_info_gas_calculated_rate_amount_in_cents = $rate_info_gas->rate_amount * 100;
                            $rate_info_gas_calculated_rate_amount_in_dollars = $rate_info_gas->rate_amount;

                            switch ($this->eztpv->event->language_id) {
                                case 2:
                                    $rate_info_gas_currency = 'dólares';
                                    break;

                                case 1:
                                default:
                                    $rate_info_gas_currency = 'dollars';
                                    break;
                            }
                            break;
                    }

                    $rate_info_gas_yearly_price = ($rate_info_gas_calculated_intro_rate_amount_in_dollars * 75) * 12;

                    if (isset($rate_info_gas->cancellation_fee) && $rate_info_gas->cancellation_fee > 0) {
                        $text_computed_gas_cancellation_fee = '$' . $rate_info_gas->cancellation_fee;
                        $text_computed_gas_cancellation_fee_short = $text_computed_gas_cancellation_fee;
                    } else {
                        $text_computed_gas_cancellation_fee = 'No';
                        switch ($this->eztpv->event->language_id) {
                            case 1:
                                $cancelFeeNoneByLanguage = 'None.';
                                break;

                            case 2:
                                $cancelFeeNoneByLanguage = 'Ninguno.';
                                break;
                        }
                        $text_computed_gas_cancellation_fee_short = $cancelFeeNoneByLanguage;
                    }
                    $text_computed_gas_cancellation_fee .= ' Early Cancellation Fee.';

                    $rate_info_gas_date_to = (isset($rate_info_gas->date_to)) ? Carbon::parse($rate_info_gas->date_to, 'America/Chicago')->format('d-m-Y') : null;

                    $rate_info_gas_green_percentage = (isset($rate_info_gas->green_percentage)) ? $rate_info_gas->green_percentage : 0;

                    $rate_info_gas_intro_term = (isset($rate_info_gas->intro_term)) ? $rate_info_gas->intro_term : 0;
                    $rate_info_gas_term_remaining = (isset($rate_info_gas->intro_term)) ? $rate_info_gas->term - $rate_info_gas->intro_term : $rate_info_gas->term;
                    $rate_info_gas_term_total = (isset($rate_info_gas->intro_term)) ? $rate_info_gas->intro_term + $rate_info_gas->term : $rate_info_gas->term;

                    $rate_info_gas_name = (isset($rate_info_gas->name)) ? $rate_info_gas->name : null;

                    $rate_info_gas_program_code = $rate_info_gas->program_code;

                    $rate_info_gas_rate_amount_in_cents = $rate_info_gas->rate_amount;
                    $rate_info_gas_daily_fee_in_cents = (isset($rate_info_gas->daily_fee))
                        ? $rate_info_gas->daily_fee
                        : null;

                    $rate_info_gas_rate_amount_in_dollars = $rate_info_gas->rate_amount * 0.01;
                    $rate_info_gas_daily_fee_in_dollars = (isset($rate_info_gas->daily_fee))
                        ? $rate_info_gas->daily_fee * 0.01
                        : null;

                    $rate_info_gas_rate_source_code = $rate_info_gas->rate_source_code;

                    $computed_gas_rate_type_plus_green = '';

                    $rate_info_gas_custom_data_1 = (isset($rate_info_gas->custom_data_1)) ? $rate_info_gas->custom_data_1 : null;
                    $rate_info_gas_custom_data_2 = (isset($rate_info_gas->custom_data_2)) ? $rate_info_gas->custom_data_2 : null;
                    $rate_info_gas_custom_data_3 = (isset($rate_info_gas->custom_data_3)) ? $rate_info_gas->custom_data_3 : null;
                    $rate_info_gas_custom_data_4 = (isset($rate_info_gas->custom_data_4)) ? $rate_info_gas->custom_data_4 : null;
                    $rate_info_gas_custom_data_5 = (isset($rate_info_gas->custom_data_5)) ? $rate_info_gas->custom_data_5 : null;

                    // Calcualte estimated daily fee and intro daily fee at various usage levels in CENTS and DOLLARS
                    $rate_info_gas_estimated_daily_fee_500_in_cents    = 0;
                    $rate_info_gas_estimated_daily_fee_500_in_dollars  = 0;
                    $rate_info_gas_estimated_daily_fee_1000_in_cents   = 0;
                    $rate_info_gas_estimated_daily_fee_1000_in_dollars = 0;
                    $rate_info_gas_estimated_daily_fee_1500_in_cents   = 0;
                    $rate_info_gas_estimated_daily_fee_1500_in_dollars = 0;
                    $rate_info_gas_estimated_daily_fee_2000_in_cents   = 0;
                    $rate_info_gas_estimated_daily_fee_2000_in_dollars = 0;

                    $rate_info_gas_intro_estimated_daily_fee_500_in_cents    = 0;
                    $rate_info_gas_intro_estimated_daily_fee_500_in_dollars  = 0;
                    $rate_info_gas_intro_estimated_daily_fee_1000_in_cents   = 0;
                    $rate_info_gas_intro_estimated_daily_fee_1000_in_dollars = 0;
                    $rate_info_gas_intro_estimated_daily_fee_1500_in_cents   = 0;
                    $rate_info_gas_intro_estimated_daily_fee_1500_in_dollars = 0;
                    $rate_info_gas_intro_estimated_daily_fee_2000_in_cents   = 0;
                    $rate_info_gas_intro_estimated_daily_fee_2000_in_dollars = 0;

                    // In Cents
                    if (isset($rate_info_gas->daily_fee)) {

                        // Check daily fee currency type. Assume that if value is < 1, then daily fee was entered in dollars
                        // The existing $rate_info_electric_daily_fee_in_dollars assumes that the daily fee was entered in cents
                        // Genie enters theirs in dollars, for example, so that variable cannot be trusted
                        $dailyFee = null;
                        if($rate_info_gas->daily_fee < 1) { // Daily fee is in dollars, convert to cents
                            $dailyFee = $rate_info_gas->daily_fee * 100;
                        } else { // Daily fee is in cents, use raw value
                            $dailyFee = $rate_info_gas->daily_fee;
                        }

                        $rate_info_gas_estimated_daily_fee_500_in_cents  = $dailyFee * 30 / 500  + $rate_info_gas_calculated_rate_amount_in_cents;
                        $rate_info_gas_estimated_daily_fee_1000_in_cents = $dailyFee * 30 / 1000 + $rate_info_gas_calculated_rate_amount_in_cents;
                        $rate_info_gas_estimated_daily_fee_1500_in_cents = $dailyFee * 30 / 1500 + $rate_info_gas_calculated_rate_amount_in_cents;
                        $rate_info_gas_estimated_daily_fee_2000_in_cents = $dailyFee * 30 / 2000 + $rate_info_gas_calculated_rate_amount_in_cents;

                        $rate_info_gas_intro_estimated_daily_fee_500_in_cents  = $dailyFee * 30 / 500  + $rate_info_gas_calculated_intro_rate_amount_in_cents;
                        $rate_info_gas_intro_estimated_daily_fee_1000_in_cents = $dailyFee * 30 / 1000 + $rate_info_gas_calculated_intro_rate_amount_in_cents;
                        $rate_info_gas_intro_estimated_daily_fee_1500_in_cents = $dailyFee * 30 / 1500 + $rate_info_gas_calculated_intro_rate_amount_in_cents;
                        $rate_info_gas_intro_estimated_daily_fee_2000_in_cents = $dailyFee * 30 / 2000 + $rate_info_gas_calculated_intro_rate_amount_in_cents;
                    }

                    // In Dollars
                    if (isset($rate_info_gas->daily_fee)) {

                        // Check daily fee currency type. Assume that if value is < 1, then daily fee was entered in dollars
                        // The existing $rate_info_electric_daily_fee_in_dollars assumes that the daily fee was entered in cents
                        // Genie enters theirs in dollars, for example, so that variable cannot be trusted
                        $dailyFee = null;
                        if($rate_info_gas->daily_fee < 1) { // Daily fee is in dollars, convert to cents
                            $dailyFee = $rate_info_gas->daily_fee * 100;
                        } else { // Daily fee is in cents, use raw value
                            $dailyFee = $rate_info_gas->daily_fee;
                        }

                        $rate_info_gas_estimated_daily_fee_500_in_dollars  = $dailyFee * 30 / 500  + $rate_info_gas_calculated_rate_amount_in_dollars;
                        $rate_info_gas_estimated_daily_fee_1000_in_dollars = $dailyFee * 30 / 1000 + $rate_info_gas_calculated_rate_amount_in_dollars;
                        $rate_info_gas_estimated_daily_fee_1500_in_dollars = $dailyFee * 30 / 1500 + $rate_info_gas_calculated_rate_amount_in_dollars;
                        $rate_info_gas_estimated_daily_fee_2000_in_dollars = $dailyFee * 30 / 2000 + $rate_info_gas_calculated_rate_amount_in_dollars;

                        $rate_info_gas_intro_estimated_daily_fee_500_in_dollars  = $dailyFee * 30 / 500  + $rate_info_gas_calculated_intro_rate_amount_in_dollars;
                        $rate_info_gas_intro_estimated_daily_fee_1000_in_dollars = $dailyFee * 30 / 1000 + $rate_info_gas_calculated_intro_rate_amount_in_dollars;
                        $rate_info_gas_intro_estimated_daily_fee_1500_in_dollars = $dailyFee * 30 / 1500 + $rate_info_gas_calculated_intro_rate_amount_in_dollars;
                        $rate_info_gas_intro_estimated_daily_fee_2000_in_dollars = $dailyFee * 30 / 2000 + $rate_info_gas_calculated_intro_rate_amount_in_dollars;
                    }

                    switch ($rate_info_gas->rate_type_id) {
                        case '1':
                        case 1:
                            $rate_info_gas_rate_amount = round($rate_info_gas->rate_amount, 2);
                            $gas_fixed_rate_checkbox = 'Yes';
                            $computed_gas_rate_type_plus_green .= 'Fixed';
                            break;

                        case '2':
                        case 2:
                            $rate_info_gas_rate_amount = round($rate_info_gas->rate_amount, 2);
                            $gas_variable_rate_checkbox = 'Yes';
                            $computed_gas_rate_type_plus_green .= 'Variable';
                            break;

                        case '3':
                        case 3:
                            $rate_info_gas_rate_amount = round($rate_info_gas->intro_rate_amount, 2);
                            $rate_info_gas_tiered_rate_amount = round($rate_info_gas->rate_amount, 2);
                            $gas_tiered_rate_checkbox = 'Yes';
                            $computed_gas_rate_type_plus_green .= 'Tiered';
                            break;
                    }

                    $computed_gas_green_product = 'No';
                    if (0 != $rate_info_gas_green_percentage) {
                        $computed_gas_green_product = 'Yes';
                        $computed_gas_rate_type_plus_green .= ' Green';
                    }

                    $rate_info_gas_term = $rate_info_gas->term;
                    if (
                        isset($rate_info_gas_term)
                        && $rate_info_gas_term != 0
                    ) {
                        $end_date_gas = $eztpv->created_at->add($rate_info_gas_term, $rate_info_gas->term_type->term_type . 's')->format('m-d-Y');
                        // Formula should be Today's date + Intro Term (1 Month) + Term (13 Months)
                        $renewal_date_gas = $eztpv->created_at->add($rate_info_gas_term + $rate_info_gas_intro_term, $rate_info_gas->term_type->term_type . 's')->format('m-d-Y');
                        if (2 == $this->eztpv->event->language_id) {
                            $rate_info_gas_term_uom = 'meses';
                            if (
                                isset($rate_info_gas)
                                && isset($rate_info_gas->term_type)
                                && isset($rate_info_gas->term_type->term_type)
                            ) {
                                switch ($rate_info_gas->term_type->term_type) {
                                    case 'day':
                                        $rate_info_gas_term_uom = 'dias';
                                        break;
                                    case 'week':
                                        $rate_info_gas_term_uom = 'semanas';
                                        break;
                                    case 'month':
                                        $rate_info_gas_term_uom = 'meses';
                                        break;
                                    case 'year':
                                        $rate_info_gas_term_uom = 'años';
                                        break;
                                }
                            }
                        } else {
                            $rate_info_gas_term_uom = 'months';
                            if (
                                isset($rate_info_gas)
                                && isset($rate_info_gas->term_type)
                                && isset($rate_info_gas->term_type->term_type)
                            ) {
                                $rate_info_gas_term_uom = $rate_info_gas->term_type->term_type . 's';
                            }
                        }
                    }

                    $rate_info_gas_rate_uom = (isset($rate_info_gas->rate_uom->uom)) ? $rate_info_gas->rate_uom->uom : null;

                    // $state_query = State::find($product['service_state']);
                    $state_gas = $state_query->state_abbrev;
                    $zip_gas = $product['service_zip'];
                    $start_date_gas = $date;

                    if (isset($product['toc'])) {
                        $toc_gas = strip_tags($product['toc']);
                    } else {
                        $toc_gas = '';
                    }
                }
            }

            if ('Yes' == $event_type_electric && 'Yes' == $event_type_gas) {
                $event_type_electric_and_gas = 'Yes';
                $event_type_electric = 'Off';
                $event_type_gas = 'Off';
            }

            if (@$company_name_gas == @$company_name_electric && @$fullname_gas == @$fullname_electric) {
                $gas_info_same_as_electric = 'Yes';
            } else {
                $gas_info_same_as_electric = 'Off';
            }

            $commodity = '';
            if ('Yes' == $event_type_electric_and_gas) {
                $commodity = 'dual';
                $commodity_type = 'Dual Fuel';
                $commodity_type_all = 'Electric\rNatural Gas';
            } elseif ('Yes' == $event_type_electric) {
                $commodity = 'electric';
                $commodity_type = 'Electric';
                $commodity_type_all = 'Electric';
            } elseif ('Yes' == $event_type_gas) {
                $commodity = 'gas';
                $commodity_type = 'Natural Gas';
                $commodity_type_all = 'Natural Gas';
            }

            if ($contract_id) {
                $this->info('Using preset contract id: ' . $contract_id);
                $pdf_info = BrandEztpvContract::find($contract_id);
            } else {
                DB::enableQueryLog();
                $__cdata = [
                    'brand_id' => $this->eztpv->brand_id,
                    'state_id' => $this->eztpv->event->products[0]->serviceAddress->address->state->id,
                    'channel_id' => $this->eztpv->event->channel_id,
                    'market_id' => $this->eztpv->event->products[0]->market_id,
                    'utility_id' => $this->eztpv->event->products[0]->utility_id,
                    'commodity' => $commodity,
                    'event_type_electric_and_gas' => $event_type_electric_and_gas,
                    'event_type_electric' => $event_type_electric,
                    'event_type_gas' => $event_type_gas,

                ];
                $this->info('Searching for contract with base data: ' . json_encode($__cdata));

                $pdf_info = BrandEztpvContract::where('brand_id', $this->eztpv->brand_id)
                    ->where('state_id', $this->eztpv->event->products[0]->serviceAddress->address->state->id)
                    ->where('commodity', $commodity);

                if (isset($this->eztpv->event->channel_id)) {
                    $pdf_info = $pdf_info->where('channel_id', $this->eztpv->event->channel_id);
                }

                if (isset($this->eztpv->event->products[0]->market_id)) {
                    $pdf_info = $pdf_info->where('market_id', $this->eztpv->event->products[0]->market_id);
                }

                $this->logInfo('BRAND IS ' . $this->eztpv->brand_id, __METHOD__, __LINE__);

                switch ($this->eztpv->brand_id) {
                    case self::BRAND_IDS['nordic_energy']['staging']:
                    case self::BRAND_IDS['nordic_energy']['production']:
                    case self::BRAND_IDS['greenwave_energy']['staging']:
                    case self::BRAND_IDS['greenwave_energy']['production']:
                    case self::BRAND_IDS['median']['staging']:
                    case self::BRAND_IDS['median']['production']:
                    case self::BRAND_IDS['spark_energy']['staging']:
                    case self::BRAND_IDS['spark_energy']['staging2']:
                    case self::BRAND_IDS['spark_energy']['production']:
                        $this->info("Updating query where language_id = to the event->language_id value: " . $this->eztpv->event->language_id,
                            __METHOD__, __LINE__);
                        // Update the query, where language_id clause to use language_id from the event
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        break;
                    case '98294147-15cc-4f5a-95fa-c709dfe43583':
                    case '5f51d408-a26c-4573-a4f0-ffe794a4783e':
                        // Harborside Energy

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                $green_harborside = $rate_info_electric->green_percentage !== null && $rate_info_electric->green_percentage > 0;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                $green_harborside = $rate_info_gas->green_percentage !== null && $rate_info_gas->green_percentage > 0;
                                break;
                        }

                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        if ($green_harborside) {
                            if ($this->option('debug')) {
                                $this->info('Harborside Energy product is green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 1);
                        } else {
                            if ($this->option('debug')) {
                                $this->info('Harborside Energy product is NOT green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 0);
                        }

                        break;

                    case self::BRAND_IDS['browns_energy']['production']:
                    case self::BRAND_IDS['browns_energy']['staging']:

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                $green_browns = $rate_info_electric->green_percentage !== null && $rate_info_electric->green_percentage > 0;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                $green_browns = $rate_info_gas->green_percentage !== null && $rate_info_gas->green_percentage > 0;
                                break;
                        }

                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        if ($green_browns) {
                            if ($this->option('debug')) {
                                $this->info('Browns Energy product is green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 1);
                        } else {
                            if ($this->option('debug')) {
                                $this->info('Browns Energy product is NOT green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 0);
                        }

                        break;
                                                    
                    case '568bc259-b147-4369-97e5-9a99825d0d7a':
                        // USG&E

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $green_usge = $rate_info_electric->green_percentage !== null && $rate_info_electric->green_percentage > 0;
                                break;

                            case 'gas':
                                $green_usge = $rate_info_gas->green_percentage !== null && $rate_info_gas->green_percentage > 0;
                                break;
                        }

                        if ($green_usge) {
                            if ($this->option('debug')) {
                                $this->info('USG&E Energy product is green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 1);
                        } else {
                            if ($this->option('debug')) {
                                $this->info('USG&E Energy product is NOT green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 0);
                        }
                    case self::BRAND_IDS['idt_energy']['production']:
                        // IDT Energy
                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $green_genie = $rate_info_electric->green_percentage !== null && $rate_info_electric->green_percentage > 0;
                                $rate_type = $rate_info_electric->rate_type_id;
                                $rate_amount = $rate_info_electric->rate_amount;
                                $intro_rate_amount = $rate_info_electric->intro_rate_amount;
                                $product_id = $rate_info_electric->product_id;
                                break;

                            case 'gas':
                                $green_genie = $rate_info_gas->green_percentage !== null && $rate_info_gas->green_percentage > 0;
                                $rate_type = $rate_info_gas->rate_type_id;
                                $rate_amount = $rate_info_gas->rate_amount;
                                $intro_rate_amount = $rate_info_gas->intro_rate_amount;
                                $product_id = $rate_info_gas->product_id;
                                break;
                        }
                        $pdf_info = $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        if ($green_genie) {
                            if ($this->option('debug')) {
                                $this->info('IDT Energy product is green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 1);
                        } else {
                            if ($this->option('debug')) {
                                $this->info('IDT Energy product is NOT green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 0);
                        }

                        // UTILITY SPECIFIC CONDITIONS (if State is IL and RateTypeId is Tiered and Utility_id present)
                        //$data['finalized_products'][$x][0]['utility_id']
                        if ($__cdata['state_id'] == 14 && in_array($rate_type, [2, 3])) {
                            $this->info('IDT ENERGY (STATE IS IL and RATE TYPE IS TIERED(3) ************');
                            $pdf_info = $pdf_info->where('utility_id', $data['finalized_products'][$x][0]['utility_id']);
                        }

                        switch ($rate_type) {
                            case '1':
                            case '2':
                            case 1:
                            case 2:
                                break;

                            case '3':
                            case 3:
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        '%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        '%tiered-variable%'
                                    );
                                }
                                break;

                            default:
                                break;
                        }
                        break;

                    case self::BRAND_IDS['townsquare_energy']['production']:
                    case self::BRAND_IDS['townsquare_energy']['staging']:
                        // Townsquare Energy
                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $green_genie = $rate_info_electric->green_percentage !== null && $rate_info_electric->green_percentage > 0;
                                $rate_type = $rate_info_electric->rate_type_id;
                                $rate_amount = $rate_info_electric->rate_amount;
                                $intro_rate_amount = $rate_info_electric->intro_rate_amount;
                                $product_id = $rate_info_electric->product_id;
                                break;

                            case 'gas':
                                $green_genie = $rate_info_gas->green_percentage !== null && $rate_info_gas->green_percentage > 0;
                                $rate_type = $rate_info_gas->rate_type_id;
                                $rate_amount = $rate_info_gas->rate_amount;
                                $intro_rate_amount = $rate_info_gas->intro_rate_amount;
                                $product_id = $rate_info_gas->product_id;
                                break;
                        }
                        $pdf_info = $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        if ($__cdata['state_id'] == 14 && in_array($rate_type, [2, 3])) {
                            $this->info('TOWNSQUARE (STATE IS IL and RATE TYPE IS TIERED(3) ************');
                            $pdf_info = $pdf_info->where('utility_id', $data['finalized_products'][$x][0]['utility_id']);
                        }

                        if ($green_genie) {
                            if ($this->option('debug')) {
                                $this->info('Townsquare Energy product is green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 1);
                        } else {
                            if ($this->option('debug')) {
                                $this->info('Townsquare Energy product is NOT green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 0);
                        }

                        switch ($rate_type) {
                            case '1':
                            case '2':
                            case 1:
                            case 2:
                                break;

                            case '3':
                            case 3:
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        '%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        '%tiered-variable%'
                                    );
                                }
                                break;

                            default:
                                break;
                        }
                        break;

                    case '0e80edba-dd3f-4761-9b67-3d4a15914adb':
                        // Residents
                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $green_genie = $rate_info_electric->green_percentage !== null && $rate_info_electric->green_percentage > 0;
                                $rate_type = $rate_info_electric->rate_type_id;
                                $rate_amount = $rate_info_electric->rate_amount;
                                $intro_rate_amount = $rate_info_electric->intro_rate_amount;
                                $product_id = $rate_info_electric->product_id;
                                break;

                            case 'gas':
                                $green_genie = $rate_info_gas->green_percentage !== null && $rate_info_gas->green_percentage > 0;
                                $rate_type = $rate_info_gas->rate_type_id;
                                $rate_amount = $rate_info_gas->rate_amount;
                                $intro_rate_amount = $rate_info_gas->intro_rate_amount;
                                $product_id = $rate_info_gas->product_id;
                                break;
                        }

                        $pdf_info = $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        // UTILITY SPECIFIC CONDITIONS (if State is IL and RateTypeId is Tiered and Utility_id present)
                        if ($__cdata['state_id'] == 14 && in_array($rate_type, [2, 3])) {
                            $this->info('RESIDENT ENERGY (STATE IS IL and RATE TYPE IS TIERED(3) ************');
                            $pdf_info = $pdf_info->where('utility_id', $data['finalized_products'][$x][0]['utility_id']);
                        }

                        if ($green_genie) {
                            if ($this->option('debug')) {
                                $this->info('Residents Energy product is green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 1);
                        } else {
                            if ($this->option('debug')) {
                                $this->info('Residents Energy product is NOT green');
                            }
                            $pdf_info = $pdf_info->where('product_type', 0);
                        }

                        switch ($rate_type) {
                            case '1':
                            case '2':
                            case 1:
                            case 2:
                                break;

                            case '3':
                            case 3:
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        '%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        '%tiered-variable%'
                                    );
                                }
                                break;

                            default:
                                break;
                        }
                        //$this->info($pdf_info->toSql());
                        //print_r($pdf_info->getBindings());
                        break;

                    case '2C958990-AF67-485B-BBCF-488F1E5E2DD3':
                        // Frontier
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        break;

                    case 'bc90e0f2-2b82-46d8-8c6d-ce491fb1f227':
                    case 'ec6e7aad-3d65-42c5-b242-145fb61c6c99':
                        // Waste Management
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $rate_info_electric->product_id
                        );
                        break;

                    case '04B0F894-172C-470F-813B-4F58DBD35BAE':
                        // Forward Thinking Energy
                        break;

                    case '135fd066-a5cf-447e-af5c-e2d18a7b6cb6':
                        //Median
                        break;

                    case '1e9b6cd1-fa78-4f37-8bc7-05bb44566ee0':
                        // GAP
                        // if ($this->eztpv->event->products[0]->serviceAddress->address->state->id != 14) {
                        //     $pdf_info->where('utility_supported_fuels_id', $data['sorted_products'][$x][0]['utility_id']);
                        //     $contract_rate = Rate::find($data['sorted_products'][$x][0]['rate_id']);
                        //     $pdf_info->where('program_code', $contract_rate->program_code);
                        // }
                        if (36 == $this->eztpv->event->products[0]->serviceAddress->address->state->id) {
                            switch ($commodity) {
                                case 'gas':
                                case 'dual':
                                    $filenames[] = ['file' => 'skipped'];
                                    if ($x >= count($data['sorted_products']) - 1) {
                                        return $filenames;
                                    }

                                    continue 3;
                                case 'electric':
                                    //proceed
                                    break;
                            }
                        }

                        break;

                    case '1f402ff3-dace-4aea-a6b2-a96bbdf82fee':
                    case 'd758c445-6144-4b9c-b683-717aadec83aa':
                        // Spring
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                $rate_amount = $rate_info_electric->rate_amount;
                                $intro_rate_amount = $rate_info_electric->intro_rate_amount;
                                $product_id = $rate_info_electric->product_id;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                $rate_amount = $rate_info_gas->rate_amount;
                                $intro_rate_amount = $rate_info_gas->intro_rate_amount;
                                $product_id = $rate_info_gas->product_id;
                                break;
                        }

                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        switch ($rate_type) {
                            case '1':
                            case '2':
                            case 1:
                            case 2:
                                break;

                            case '3':
                            case 3:
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'spring%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'spring%tiered-variable%'
                                    );
                                }
                                break;

                            default:
                                break;
                        }

                        // product-specific hard-coding
                        $specificProducts = [
                            
                            // STAGING
                            'c5f465bb-9a4e-4de2-afd3-94235e8de008', // Spring Green 50 - MD
                            '9f23980e-fe96-49dc-b1aa-d2f7b82dceaa', // Spring Green 50 - PA
                            'e6cb63cc-0cd5-4070-b86a-e56d37bdd181', // Spring Guard Electricity - MD
                            '9417be74-1c97-477a-8e5b-3dbee6827ca1', // Spring Green Electricity - MD
                            '2a11432a-fed5-4f0f-a583-eb5c4c581311', // Spring Guard Gas - MD
                            '80f518a2-4a65-4ba9-b485-d161d40dc658', // Zero Gas - MD
                            '9d66cfdf-b398-4c02-abd9-9a567f5303ba', // Zero Gas 50 - PA
                            'cd2282e3-1672-463e-8b1f-87f804445ab4', // Zero Gas 50 - MD

                            // PRODUCTION
                            'a303fafd-e36a-4fa9-bc53-8751001de8eb', // Spring Green 50 - MD
                            '60c42cdc-ff64-4cbe-9516-e0373b4341e0', // Spring Green 50 - PA
                            'cd778efb-4813-4b4a-842b-1cc8abcebbb5', // Spring Green Electricity - MD
                            'aa4bdefd-b08d-4af7-af64-567db52e6c30', // Spring Guard Gas - MD
                            '50594bc7-a0ed-482f-9ab4-2f5c599b2423', // Zero Gas - MD
                            'd57f2d70-4a22-4a68-b5bf-2ac017604e34', // Zero Gas 50 - MD
                            'd7376765-aed6-4ad0-83ad-ecad033d7efb', // Zero Gas 50 - PA
                            
                            // TEMP
                            '6e5ec0cc-e775-4ada-9eae-cc3f8aa5c66f', // Spring Green Electricity - MD
                            '5c11e603-d3a8-4ce9-a6ff-c951b59785f0', // Spring Zero Gas - MD
                        ];

                        if (

                            isset($product_id)
                            && in_array($product_id, $specificProducts)
                        ) {
                            // select by products.id
                            $pdf_info = $pdf_info->where(
                                'product_id',
                                $product_id
                            );
                        } else {
                            // do not select by product_id
                            $pdf_info = $pdf_info->whereNull(
                                'product_id'
                            );
                        }
                        break;

                    case 'd3970a96-e933-4cae-a923-e0daa7a59b4d':
                    case '31b177d0-33d6-4c51-9907-5b57f68a9526':
                        // Kiwi
                        $pdf_info = $pdf_info->where('language_id', $this->eztpv->event->language_id);

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                $rate_amount = $rate_info_electric->rate_amount;
                                $intro_rate_amount = $rate_info_electric->intro_rate_amount;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                $rate_amount = $rate_info_gas->rate_amount;
                                $intro_rate_amount = $rate_info_gas->intro_rate_amount;
                                break;
                        }

                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        switch ($rate_type) {
                            case '1':
                            case '2':
                            case 1:
                            case 2:
                                break;

                            case '3':
                            case 3:
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'kiwi%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'kiwi%tiered-variable%'
                                    );
                                }
                                break;

                            default:
                                break;
                        }

                        $states_id = intval($this->eztpv->event->products[0]->serviceAddress->address->state->id);

                        $this->info('States ID = ' . $states_id);

                        switch ($states_id) {
                                // select by product for some states
                            case 33:
                                $this->info('Commodity = ' . $commodity);

                                switch ($commodity) {
                                    case 'dual':
                                    case 'electric':
                                        $pdf_info = $pdf_info->where(
                                            'product_id',
                                            $rate_info_electric->product_id
                                        );
                                        break;
                                    case 'gas':
                                        $pdf_info = $pdf_info->where(
                                            'product_id',
                                            $rate_info_gas->product_id
                                        );
                                        break;
                                }
                                break;
                        }
                        break;

                    case '83800c3d-c139-46e9-bf33-8d60165c403c': // stage
                    case '6fb31120-1255-4a48-96b4-0f34be99c658': // prod
                        // Greenlight

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                break;
                        }

                        $pdf_info = $pdf_info->where('rate_type_id', $rate_type);

                        break;

                    case '4e65aab8-4dae-48ef-98ee-dd97e16cbce6':
                    case 'eb35e952-04fc-42a9-a47d-715a328125c0':
                        // Indra
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                $rate_amount = $rate_info_electric->rate_amount;
                                $intro_rate_amount = $rate_info_electric->intro_rate_amount;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                $rate_amount = $rate_info_gas->rate_amount;
                                $intro_rate_amount = $rate_info_gas->intro_rate_amount;
                                break;
                        }

                        $pdf_info->where('rate_type_id', $rate_type);

                        $this->info('Indra extra contract selection for ' . $commodity);

                        switch ($this->eztpv->event->products[0]->serviceAddress->address->state->id) {
                            case 14:
                                $this->info('Illinois indra commodity is ' . $commodity);
                                // Illinois
                                switch ($commodity) {
                                    case 'electric':
                                        $pdf_info = $pdf_info->where(
                                            'product_id',
                                            $rate_info_electric->product_id
                                        );
                                        break;
                                    case 'gas':
                                        $pdf_info = $pdf_info->where(
                                            'product_id',
                                            $rate_info_gas->product_id
                                        );
                                        break;
                                }
                                break;

                            default:
                                $this->info('Indra not illinois selection');
                                // everywhere else
                                switch ($rate_type) {
                                    case '1':
                                    case '2':
                                    case 1:
                                    case 2:
                                        break;

                                    case '3':
                                    case 3:
                                        // tiered: Fixed Tiered and Tiered Variable
                                        if (
                                            $rate_amount > 0
                                        ) {
                                            $pdf_info->where(
                                                'contract_pdf',
                                                'LIKE',
                                                'indra%fixed-tiered%'
                                            );
                                        } else {
                                            $pdf_info->where(
                                                'contract_pdf',
                                                'LIKE',
                                                'indra%tiered-variable%'
                                            );
                                        }
                                        break;

                                    default:
                                        break;
                                }
                                break;
                        }
                        break;

                    case 'f2941e4f-9633-4b43-b4b5-e1cc84d8c46e':
                    case '7b08b19d-32a5-4906-a320-6a2c5d6d6372':
                        // RPA Energy
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);

                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                break;
                        }

                        $pdf_info->where('rate_type_id', $rate_type);

                        break;

                    case 'c03d58ed-1bb0-4e35-9e11-94c1e3bd59cc':
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);

                        // Clearview Energy
                        switch ($commodity) {
                            case 'electric':
                                // $rate_product_name = explode(' - ', $rate_info_electric->name);
                                // $pdf_info = $pdf_info->where(
                                //     'contract_pdf',
                                //     'LIKE',
                                //     '%' . $rate_product_name[0] . '\_%'
                                // );

                                // select by products.id
                                $pdf_info = $pdf_info->where(
                                    'product_id',
                                    $rate_info_electric->product_id
                                );
                                break;

                            case 'gas':
                                // $rate_product_name = explode(' - ', $rate_info_gas->name);
                                // $pdf_info = $pdf_info->where(
                                //     'contract_pdf',
                                //     'LIKE',
                                //     '%' . $rate_product_name[0] . '\_%'
                                // );

                                // select by products.id
                                $pdf_info = $pdf_info->where(
                                    'product_id',
                                    $rate_info_gas->product_id
                                );
                                break;

                            case 'dual':
                                return [
                                    'error',
                                    'Contract Generation Failed: Clearview contract submitted as dual-fuel. Should be commodity-specific.',
                                ];
                                break;
                        }
                        break;

                    case '363ef739-3f2c-4a18-9221-46d46c869eb9':
                    case '293c51ca-87de-41c6-bb98-948c7537bc11':
                        // Atlantic Energy
                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                break;
                        }

                        $pdf_info->where('rate_type_id', $rate_type);
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);
                        break;

                    case '52f9b7cd-2395-48e9-a534-31f15eebc9d4':
                    case 'faeb80e2-16ce-431c-bb54-1ade365eec16':
                        // Rushmore Energy
                        $pdf_info->where('language_id', $this->eztpv->event->language_id);

                        $api_submission = false;
                        foreach ($event->interactions as $interaction) {
                            if ($interaction->interaction_type_id === 11) {
                                $api_submission = true;
                            }
                        }
                        if (
                            $api_submission === true
                        ) {
                            switch ($commodity) {
                                case 'electric':
                                    // select by products.id
                                    $pdf_info = $pdf_info->where(
                                        'product_id',
                                        $rate_info_electric->product_id
                                    )->where(
                                        'rate_id',
                                        $rate_info_electric->id
                                    );
                                    break;

                                case 'gas':
                                    // select by products.id
                                    $pdf_info = $pdf_info->where(
                                        'product_id',
                                        $rate_info_gas->product_id
                                    )->where(
                                        'rate_id',
                                        $rate_info_gas->id
                                    );
                                    break;
                            }
                            break;
                        } else {
                            // on 10-20 Paul and Lauren requested product-specific contract selection for specific product ids, having been informed at the time that this code will break when IDs/products are updated
                            // on 11-13 Paul requested product/rate-specific contract selection for specific prodcuts/rates, as above
                            $rushmoreProducts = [
                                '35fe3857-13ae-402b-ac3b-57ac3d383a8e',
                                '01349271-3b0d-4856-ac0e-fb417dd2f084',
                                '3163144d-7c73-4d6f-9671-96deec6230d7',
                                'df0fb122-bc8e-4b79-944b-94eabd9dfc48',
                                '3d5719a3-7625-4c44-8d72-19e745f053f4',
                                'f58328ae-5245-47cd-91d8-d299a6eeb83d',
                                'cb4d3e7c-7d73-48c4-a681-f7fca68905cd',
                                'f5726206-98c4-4757-80d4-6780d33a462e',
                                'd7a1ab7f-367d-4347-88c0-4a4b787b5470', // PA - Door to Door - Fixed - 36 Month Production
                                'a3592c0b-3aec-4b97-a736-4ffa4460e4a5', // PA - Door to Door - Fixed - 36 Month New Staging
                                'd3172ab4-cb4f-4182-a211-311ff96dc58e'  // PA - Door to Door - Fixed - 36 Month Old Staging
                            ];

                            $rateSpecificProducts = [
                                '9cc79fd7-3581-4347-8b6b-46f0e07e2921',
                                'cea2c2e4-7175-497e-a8b6-dacde0abcc69'
                            ];

                            if (
                                isset($product->rate)
                                && isset($product->rate->product)
                                && isset($product->rate->product->id)
                                && in_array($product->rate->product->id, $rushmoreProducts)
                            ) {
                                // select by products.id
                                $pdf_info = $pdf_info->where(
                                    'product_id',
                                    $product->rate->product->id
                                );
                            } elseif (
                                isset($product->rate)
                                && isset($product->rate->product_id)
                                && in_array($product->rate->product_id, $rateSpecificProducts)
                            ) {
                                // select by products.id and rates.id
                                $pdf_info = $pdf_info->where(
                                    'product_id',
                                    $product->rate->product->id
                                )
                                    ->where(
                                        'rate_id',
                                        $product->rate->id
                                    );
                            } else {
                                // do not select by product_id
                                $pdf_info = $pdf_info->whereNull(
                                    'product_id'
                                );
                            }
                        }
                        break;
                    case self::BRAND_IDS['sunsea_energy']['production']:
                    case self::BRAND_IDS['sunsea_energy']['staging']:                        
                        // Allow Sunsea to use "Contract Only" (contract_type = 2) to select Contract Templates to include rate_type_id
                        switch ($commodity) {
                            case 'dual':
                            case 'electric':
                                $rate_type = $rate_info_electric->rate_type_id;
                                break;

                            case 'gas':
                                $rate_type = $rate_info_gas->rate_type_id;
                                break;
                        }
                        $pdf_info->where('rate_type_id', $rate_type);                        

                        break;
                }

                // OLIVER DEBUG
                $this->info('PDF INFO QUERY 1');
                $this->info($pdf_info->toSql());
                $this->info('Bindings:');
                $this->info(print_r($pdf_info->getBindings(), true));
                $pdf_info = $pdf_info->first();

                // store db Query for reporting
                $this->dbQuery = DB::getQueryLog();

                if ($this->option('debug')) {
                    $this->line(print_r($this->dbQuery, true));
                }
            }

            // integrity check
            if (!$pdf_info) {
                return [
                    'error',
                    'Contract Generation Failed: no matching brand_eztpv_contract record found',
                ];
            }

            $this->info('Using contract: ' . $pdf_info->id);

            // embed variable values in pdfdata
            $vars = [
                'account_number_electric',
                'account_number_gas',
                'address',
                'address_service',
                'address_billing',
                'address_electric',
                'address_gas',
                'agent_fullname',
                'agent_id',
                'agent_license',
                'ah_date_of_birth',
                'auth_fullname',
                'auth_fullname_fl',
                'auth_relationship',
                'auth_relationship_account_holder',
                'auth_relationship_authorized_agent',
                'auth_relationship_self',
                'auth_relationship_spouse',
                'bill_fullname',
                'bill_fullname_same_as_auth_fullname',
                'business_name',
                'city',
                'city_service',
                'city_billing',
                'city_electric',
                'city_gas',
                'city_state_zip_billing',
                'city_state_zip_service',
                'client_name',
                'client_address',
                'client_city',
                'client_state',
                'client_zip',
                'client_phone_number',
                'client_email_address',
                'client_logo_path',
                'commodity_type',
                'commodity_type_all',
                'company_name',
                'company_name_electric',
                'company_name_gas',
                'computed_electric_green_product',
                'computed_electric_other_fees',
                'computed_electric_rate_type_plus_green',
                'computed_gas_green_product',
                'computed_gas_rate_type_plus_green',
                'computed_multiline_auth_fullname_fl_plus_service_address',
                'confirmation_code',
                'date',
                'date_plus_3_days',
                'date_plus_10_days',
                'delivery_method',
                'eft_radio',
                'electric_fixed_rate_checkbox',
                'electric_tiered_rate_checkbox',
                'utility_electric_primary_identifier',
                'electric_utility_account_type_account_number',
                'electric_utility_account_type_customer_number',
                'electric_utility_account_type_supplier_agreement_id',
                'electric_variable_rate_checkbox',
                'email_address',
                'end_date_electric',
                'end_date_electric_month_year',
                'end_date_gas',
                'renewal_date_electric',
                'renewal_date_gas',
                'event_type_electric',
                'event_type_electric_and_gas',
                'event_type_gas',
                'full_address',
                'full_address_service',
                'full_address_billing',
                'fullname',
                'fullname_electric',
                'fullname_gas',
                'gas_fixed_rate_checkbox',
                'gas_info_same_as_electric',
                'gas_tiered_rate_checkbox',
                'utility_gas_primary_identifier',
                'gas_utility_account_type_account_number',
                'gas_utility_account_type_customer_number',
                'gas_variable_rate_checkbox',
                'gps_lat',
                'gps_lon',
                'green_product',
                'green_percentage',
                'green_percentage_formatted',
                'initials',
                'ip_address',
                'no_email',
                'office_name',
                'phone_number',
                'rate_info_electric_admin_fee',
                'rate_info_electric_currency',
                'rate_info_electric_calculated_intro_rate_amount',
                'rate_info_electric_calculated_intro_rate_amount_in_cents',
                'rate_info_electric_calculated_intro_rate_amount_in_dollars',
                'rate_info_electric_calculated_rate_amount',
                'rate_info_electric_calculated_rate_amount_in_cents',
                'rate_info_electric_calculated_rate_amount_in_dollars',
                'rate_info_electric_custom_data_1',
                'rate_info_electric_custom_data_2',
                'rate_info_electric_custom_data_3',
                'rate_info_electric_custom_data_4',
                'rate_info_electric_custom_data_5',
                'rate_info_electric_daily_fee',
                'rate_info_electric_daily_fee_in_cents',
                'rate_info_electric_daily_fee_in_dollars',
                'rate_info_electric_date_to',
                'rate_info_electric_estimated_daily_fee_500_in_cents',
                'rate_info_electric_estimated_daily_fee_500_in_dollars',
                'rate_info_electric_estimated_daily_fee_1000_in_cents',
                'rate_info_electric_estimated_daily_fee_1000_in_dollars',
                'rate_info_electric_estimated_daily_fee_1500_in_cents',
                'rate_info_electric_estimated_daily_fee_1500_in_dollars',
                'rate_info_electric_estimated_daily_fee_2000_in_cents',
                'rate_info_electric_estimated_daily_fee_2000_in_dollars',
                'rate_info_electric_intro_estimated_daily_fee_500_in_cents',
                'rate_info_electric_intro_estimated_daily_fee_500_in_dollars',
                'rate_info_electric_intro_estimated_daily_fee_1000_in_cents',
                'rate_info_electric_intro_estimated_daily_fee_1000_in_dollars',
                'rate_info_electric_intro_estimated_daily_fee_1500_in_cents',
                'rate_info_electric_intro_estimated_daily_fee_1500_in_dollars',
                'rate_info_electric_intro_estimated_daily_fee_2000_in_cents',
                'rate_info_electric_intro_estimated_daily_fee_2000_in_dollars',
                'rate_info_electric_estimated_total_bill_500kWh',
                'rate_info_electric_estimated_total_bill_1000kWh',
                'rate_info_electric_estimated_total_bill_1500kWh',
                'rate_info_electric_estimated_total_bill_2000kWh',
                'rate_info_electric_final_rate_500kWh',
                'rate_info_electric_final_rate_1000kWh',
                'rate_info_electric_final_rate_1500kWh',
                'rate_info_electric_final_rate_2000kWh',
                'rate_info_electric_green_percentage',
                'rate_info_electric_intro_term',
                'rate_info_electric_monthly_fee',
                'rate_info_electric_name',
                'rate_info_electric_program_code',
                'rate_info_electric_rate_amount',
                'rate_info_electric_rate_amount_in_cents',
                'rate_info_electric_rate_amount_in_dollars',
                'rate_info_electric_rate_amount_500kWh_in_cents',
                'rate_info_electric_rate_amount_1000kWh_in_cents',
                'rate_info_electric_rate_amount_1500kWh_in_cents',
                'rate_info_electric_rate_amount_2000kWh_in_cents',
                'rate_info_electric_rate_monthly_fee',
                'rate_info_electric_rate_source_code',
                'rate_info_electric_rate_uom',
                'rate_info_electric_term',
                'rate_info_electric_term_remaining',
                'rate_info_electric_term_total',
                'rate_info_electric_term_uom',
                'rate_info_electric_tiered_rate_amount',
                'rate_info_electric_yearly_price',
                'rate_info_electric_promo_code',
                'rate_info_electric_promo_name',
                'rate_info_electric_promo_type',
                'rate_info_electric_promo_key',
                'rate_info_electric_promo_text',
                'rate_info_electric_promo_reward',
                'rate_info_electric_service_fee',
                'rate_info_electric_transaction_fee',
                'rate_info_gas_currency',
                'rate_info_gas_calculated_intro_rate_amount',
                'rate_info_gas_calculated_intro_rate_amount_in_cents',
                'rate_info_gas_calculated_intro_rate_amount_in_dollars',
                'rate_info_gas_calculated_rate_amount',
                'rate_info_gas_calculated_rate_amount_in_cents',
                'rate_info_gas_calculated_rate_amount_in_dollars',
                'rate_info_gas_custom_data_1',
                'rate_info_gas_custom_data_2',
                'rate_info_gas_custom_data_3',
                'rate_info_gas_custom_data_4',
                'rate_info_gas_custom_data_5',
                'rate_info_gas_date_to',
                'rate_info_gas_estimated_daily_fee_500_in_cents',
                'rate_info_gas_estimated_daily_fee_500_in_dollars',
                'rate_info_gas_estimated_daily_fee_1000_in_cents',
                'rate_info_gas_estimated_daily_fee_1000_in_dollars',
                'rate_info_gas_estimated_daily_fee_1500_in_cents',
                'rate_info_gas_estimated_daily_fee_1500_in_dollars',
                'rate_info_gas_estimated_daily_fee_2000_in_cents',
                'rate_info_gas_estimated_daily_fee_2000_in_dollars',
                'rate_info_gas_intro_estimated_daily_fee_500_in_cents',
                'rate_info_gas_intro_estimated_daily_fee_500_in_dollars',
                'rate_info_gas_intro_estimated_daily_fee_1000_in_cents',
                'rate_info_gas_intro_estimated_daily_fee_1000_in_dollars',
                'rate_info_gas_intro_estimated_daily_fee_1500_in_cents',
                'rate_info_gas_intro_estimated_daily_fee_1500_in_dollars',
                'rate_info_gas_intro_estimated_daily_fee_2000_in_cents',
                'rate_info_gas_intro_estimated_daily_fee_2000_in_dollars',
                'rate_info_gas_green_percentage',
                'rate_info_gas_intro_term',
                'rate_info_gas_monthly_fee',
                'rate_info_gas_name',
                'rate_info_gas_program_code',
                'rate_info_gas_rate_amount',
                'rate_info_gas_rate_amount_in_cents',
                'rate_info_gas_rate_amount_in_dollars',
                'rate_info_gas_rate_source_code',
                'rate_info_gas_rate_uom',
                'rate_info_gas_term',
                'rate_info_gas_term_remaining',
                'rate_info_gas_term_total',
                'rate_info_gas_term_uom',
                'rate_info_gas_tiered_rate_amount',
                'rate_info_gas_yearly_price',
                'rate_info_gas_promo_code',
                'rate_info_gas_promo_name',
                'rate_info_gas_promo_type',
                'rate_info_gas_promo_key',
                'rate_info_gas_promo_text',
                'rate_info_gas_promo_reward',
                'rate_info_rate_amount',
                'rate_info_rate_source_code',
                'rate_info_term',
                'rieetb500',
                'rieetb1000',
                'rieetb1500',
                'rieetb2000',
                'riefr500',
                'riefr1000',
                'riefr1500',
                'riefr2000',
                'rate_info_electric_final_rate_500kWh_in_cents',
                'rate_info_electric_final_rate_500kWh_in_dollars',
                'rate_info_electric_final_rate_1000kWh_in_cents',
                'rate_info_electric_final_rate_1000kWh_in_dollars',
                'rate_info_electric_final_rate_1500kWh_in_cents',
                'rate_info_electric_final_rate_1500kWh_in_dollars',
                'rate_info_electric_final_rate_2000kWh_in_cents',
                'rate_info_electric_final_rate_2000kWh_in_dollars',
                'riefr500ic',
                'riefr500id',
                'riefr1000ic',
                'riefr1000id',
                'riefr1500ic',
                'riefr1500id',
                'riefr2000ic',
                'riefr2000id',
                'signature_date',
                'service_address_same_as_billing_address',
                'start_date',
                'start_date_electric',
                'start_date_gas',
                'state',
                'state_service',
                'state_billing',
                'state_electric',
                'state_gas',
                'text_computed_electric_cancellation_fee',
                'text_computed_electric_cancellation_fee_short',
                'text_computed_gas_cancellation_fee',
                'text_computed_gas_cancellation_fee_short',
                'toc_electric',
                'toc_gas',
                'uecs',
                'uecsf',
                'ugcs',
                'ugcsf',
                'utility_electric_customer_service',
                'utility_electric_customer_service_formatted',
                'utility_electric_address',
                'utility_electric_address_full',
                'utility_electric_city',
                'utility_electric_name',
                'utility_electric_state',
                'utility_electric_zip',
                'utility_electric_website',
                'utility_gas_address',
                'utility_gas_address_full',
                'utility_gas_city',
                'utility_gas_customer_service',
                'utility_gas_customer_service_formatted',
                'utility_gas_name',
                'utility_gas_state',
                'utility_gas_zip',
                'utility_gas_website',
                'utility_name_all',
                'utility_account_number_all',
                'utility_rate_unit',
                'utility_rate_unit_electric',
                'utility_rate_unit_gas',
                'vendor_name',
                'vendor_phone_number',
                'zip',
                'zip_service',
                'zip_billing',
                'zip_electric',
                'zip_gas',

                'rate_info_electric_intro_final_rate_500kWh',
                'rate_info_electric_intro_final_rate_1000kWh',
                'rate_info_electric_intro_final_rate_1500kWh',
                'rate_info_electric_intro_final_rate_2000kWh',
                'rieir500',
                'rieir1000',
                'rieir1500',
                'rieir2000',
            ];

            foreach ($relationships as $relationship) {
                $vars[] = 'auth_relationship_' . $relationship->relationship;
            }

            if (isset($utility_account_types_electric)) {
                foreach ($utility_account_types_electric as $type) {
                    $vars[] = 'utility_account_type_electric_' . $type->account_type;
                }
            }
            if (isset($utility_account_types_gas)) {
                foreach ($utility_account_types_gas as $type) {
                    $vars[] = 'utility_account_type_gas_' . $type->account_type;
                }
            }

            // event custom variables added to vars list
            foreach ($eventCustomFields as $eventField => $eventValue) {
                $vars[] = $eventField;
            }

            //custom field variables added to vars list
            foreach ($data['finalized_products'][$x] as $product) {
                if (
                    isset($product['custom'])
                ) {
                    foreach ($product['custom'] as $field => $value) {
                        $vars[] = $field;
                    }
                }
            }

            //check document_file_type_id
            switch ($pdf_info->document_file_type_id) {
                case '2':
                    // doc

                    // instantiate PHPWord
                    $phpword = new PhpWord();

                    $this->logInfo('filename is ' . $pdf_info->file_name, __METHOD__, __LINE__);

                    // set source document
                    if (Storage::disk('s3')->exists('contracts/' . $pdf_info->file_name)) {
                        $this->logInfo('contract existed in S3 - pulling it down', __METHOD__, __LINE__);

                        $s3File = Storage::disk('s3')->get('contracts/' . $pdf_info->file_name);
                        $fileString = md5(date('d.m.Y.H.i.s') . mt_rand(1000000, 9999999));
                        $sourceDoc = public_path('tmp/' . $fileString . '.docx');
                        try {
                            file_put_contents(
                                $sourceDoc,
                                $s3File
                            );
                        } catch (\Exception $e) {
                            return ['error', 'getting contract: ' . $e->getMessage()];
                        }

                        $this->logInfo('sourceDoc is ' . $sourceDoc, __METHOD__, __LINE__);

                        if (file_exists($sourceDoc)) {
                            $this->logInfo('Source file downloaded from s3 and verified locally.', __METHOD__, __LINE__);
                        } else {
                            $this->logInfo('Downloaded source file not found on local drive.', __METHOD__, __LINE__);

                            return [
                                'error',
                                'Contract generation failed: Downloaded source file not found on local drive.',
                            ];
                        }
                    } elseif (file_exists(resource_path('assets/documents/' . $pdf_info->contract_pdf))) {
                        $sourceDoc = resource_path('assets/documents/' . $pdf_info->contract_pdf);

                        $this->logInfo('Source DOC is ' . $sourceDoc, __METHOD__, __LINE__);
                    } else {
                        return [
                            'error',
                            'Contract Generation Failed: Source document not found.',
                        ];
                    }
                    $sourceDocPathInfo = pathinfo($sourceDoc);
                    // set temp filename
                    $filepath = public_path('uploads/eztpv/documents/');
                    $filename = $filepath . 'eztpv' . '_' . $eztpv->id
                        . '_' . date('d.m.Y.H.i.s') . '_' . mt_rand(1, 99999);
                    $tempFile = $filename . '.' . $sourceDocPathInfo['extension'];
                    // load source document as template
                    $wordTemplate = $phpword->loadTemplate($sourceDoc);
                    // replace variables with values
                    foreach ($vars as $var) {
                        if (empty($$var)) { // empty($var) is the same as (!isset($var) || empty($var))
                            switch ($var) {
                                case 'rate_info_electric_daily_fee':
                                case 'rate_info_gas_daily_fee':
                                case 'text_computed_electric_cancellation_fee':
                                case 'text_computed_electric_cancellation_fee_short':
                                case 'text_computed_gas_cancellation_fee':
                                case 'text_computed_gas_cancellation_fee_short':
                                case 'rate_info_electric_monthly_fee':
                                case 'rate_info_gas_monthly_fee':
                                    $$var = '0';
                                    break;

                                case 'rate_info_electric_promo_code':
                                case 'rate_info_electric_promo_name':
                                case 'rate_info_electric_promo_type':
                                case 'rate_info_electric_promo_key':
                                case 'rate_info_electric_promo_text':
                                case 'rate_info_electric_promo_reward':
                                case 'rate_info_gas_promo_code':
                                case 'rate_info_gas_promo_name':
                                case 'rate_info_gas_promo_type':
                                case 'rate_info_gas_promo_key':
                                case 'rate_info_gas_promo_text':
                                case 'rate_info_gas_promo_reward':
                                    $$var = '';
                                    break;

                                default:
                                    $$var = 'N/A';
                                    break;
                            }
                        }
                        $wordTemplate->setValue($var, htmlspecialchars(@$$var, \ENT_XML1, 'UTF-8', false));
                    }
                    $api_submission = false;
                    foreach ($event->interactions as $interaction) {
                        if ($interaction->interaction_type_id === 11) {
                            $api_submission = true;
                        }
                    }

                    $eztpvConfig = json_decode($event->eztpvConfig->config, true);
                    $customer_signature_embed = 'blank';

                    if (
                        !$this->option('preview')
                    ) {
                        $this->logInfo('$pdf_info->signature_required_customer is ' . $pdf_info->signature_required_customer, __METHOD__, __LINE__);

                        // If the customer signature is required, let's configure for it.
                        if (
                            $pdf_info->signature_required_customer == 1
                        ) {
                            if (
                                $api_submission
                            ) {
                                if (isset($eztpv->signature_customer)) {
                                    $customer_signature_embed = 'signature';
                                } elseif (isset($eztpv->signature)) {
                                    $customer_signature_embed = 'signature';
                                } else {
                                    $customer_signature_embed = 'text';
                                }
                            } elseif (
                                $eztpv->webenroll == 0
                                && isset($pdf_info->signature_info_customer)
                            ) {
                                $customer_signature_embed = 'signature';
                            }
                        }
                    }

                    if ($event->channel_id === 2) {
                        // $this->logInfo('channel_id is ' . $event->channel_id, __METHOD__, __LINE__);
                        // $this->logInfo('state id is ' . $event->products[0]->serviceAddress->address->state->id, __METHOD__, __LINE__);
                        // $this->logInfo('config for customer signature is ' . $eztpvConfig[$event->products[0]->serviceAddress->address->state->id]['channels'][$event->channel_id]['customer_signature_device'], __METHOD__, __LINE__);

                        // if (
                        //     isset($eztpvConfig[$event->products[0]->serviceAddress->address->state->id]['channels'][$event->channel_id]['customer_signature_device'])
                        //     && $eztpvConfig[$event->products[0]->serviceAddress->address->state->id]['channels'][$event->channel_id]['customer_signature_device'] === 'customer'
                        // ) {
                        //     $customer_signature_embed = 'signature';
                        // }
                    }

                    $this->logInfo('customer_signature_embed is ' . $customer_signature_embed, __METHOD__, __LINE__);

                    switch ($customer_signature_embed) {
                        case 'text':
                            $this->info('Customer Signature setting: text');
                            $wordTemplate->setValue('signature_customer', 'Inbound customer call.  Voice authorization saved to file. ' . $event->confirmation_code);
                            break;

                        case 'signature':
                            $this->info('Customer Signature setting: signature');
                            // generate signature image
                            if (isset($eztpv->signature_customer)) {
                                $this->info('Customer signature found: eztpv->signature_customer->signature');
                                $customer_signature = $eztpv->signature_customer->signature;
                            } elseif (isset($eztpv->signature)) {
                                $this->info('Customer signature found: eztpv->signature');
                                $customer_signature = $eztpv->signature;
                            } elseif ($event->channel_id === 2) {
                                // if (
                                //     isset($eztpvConfig[$event->products[0]->serviceAddress->address->state->id]['channels'][$event->channel_id]['customer_signature_device'])
                                //     && $eztpvConfig[$event->products[0]->serviceAddress->address->state->id]['channels'][$event->channel_id]['customer_signature_device'] === 'customer'
                                // ) {
                                //     $this->info('Customer signature found (2): eztpv->signature_customer->signature');
                                //     $customer_signature = $eztpv->signature_customer->signature;
                                // } else {
                                //     $this->info('Customer signature not found');
                                //     $customer_signature = null;
                                // }
                            } else {
                                return [
                                    'error',
                                    'GenerateEzTpvContracts@generate_contract_document - no customer signature found',
                                ];
                            }

                            if (isset($customer_signature)) {
                                $sigfile_customer = $this->generateSigfile($customer_signature);
                                if (
                                    $sigfile_customer
                                    && is_array($sigfile_customer)
                                    && $sigfile_customer[0] === 'error'
                                ) {
                                    return [
                                        'error',
                                        'GenerateEzTpvContracts@generate_contract_document - customer signature failed to generate',
                                    ];
                                } else {
                                    $this->info('Attaching Customer Signature (1)');
                                    $wordTemplate->setImageValue('signature_customer', $sigfile_customer);
                                    $wordTemplate->setImageValue('signature_customer2', $sigfile_customer);
                                }
                                $this->info('Deleting customer sigfile (1)');
                                $this->unlinkFile($sigfile_customer);
                            } else {
                                $this->info('No Customer Signature Set');
                                $wordTemplate->setValue('signature_customer', '');
                                $wordTemplate->setValue('signature_customer2', '');
                            }
                            break;

                        case 'blank':
                        default:
                            $this->info('Customer Signature setting: blank');
                            $wordTemplate->setValue('signature_customer', '');
                            $wordTemplate->setValue('signature_customer2', '');
                            break;
                    }
                    $agent_signature_embed = 'blank';
                    if (
                        !$this->option('preview')
                    ) {
                        if (
                            $pdf_info->signature_required_agent == 1
                        ) {
                            if (
                                $api_submission === true
                            ) {
                                // commented out for now, all api submitted agent sigs will be blank

                                // if (isset($eztpv->signature_agent)) {
                                //     $agent_signature_embed = 'signature';
                                // } elseif (isset($eztpv->signature)) {
                                //     $agent_signature_embed = 'signature';
                                // } else {
                                //     $agent_signature_embed = 'text';
                                $agent_signature_embed = 'blank';
                                // }
                            } elseif (
                                $eztpv->webenroll == 0
                                && isset($pdf_info->signature_info_agent)
                            ) {
                                $agent_signature_embed = 'signature';
                            }
                        }
                    }
                    switch ($agent_signature_embed) {
                        case 'text':
                            $this->info('Agent Signature setting: text');
                            $wordTemplate->setValue('signature_agent', 'Inbound customer call.  Voice authorization saved to file. ' . $event->confirmation_code);
                            break;

                        case 'signature':
                            $this->info('Agent Signature setting: signature');
                            // generate signature image
                            if (isset($eztpv->signature_agent)) {
                                $this->info('Agent signature found: eztpv->signature_agent->signature');
                                $agent_signature = $eztpv->signature_agent->signature;
                            } elseif (isset($eztpv->signature2)) {
                                $this->info('Agent signature found: eztpv->signature2');
                                $agent_signature = $eztpv->signature2;
                            } elseif ($event->channel_id === 2) {
                                // $this->info('Agent signature not found');
                                // $agent_signature = null;
                            } else {
                                return [
                                    'error',
                                    'GenerateEzTpvContracts@generate_contract_document - no agent signature found',
                                ];
                            }

                            if (isset($agent_signature)) {
                                $sigfile_agent = $this->generateSigfile($agent_signature);
                                if (
                                    $sigfile_agent
                                    && is_array($sigfile_agent)
                                    && $sigfile_agent[0] === 'error'
                                ) {
                                    return [
                                        'error',
                                        'GenerateEzTpvContracts@generate_contract_document - agent signature failed to generate',
                                    ];
                                } else {
                                    $this->info('Attaching Agent Signature (1)');
                                    $wordTemplate->setImageValue('signature_agent', $sigfile_agent);
                                }
                                $this->info('Deleting agent sigfile (1)');
                                $this->unlinkFile($sigfile_agent);
                            } else {
                                $this->info('No Agent Signature set');
                                $wordTemplate->setValue('signature_agent', '');
                            }
                            break;

                        case 'blank':
                        default:
                            $this->info('Agent Signature setting: blank');
                            $wordTemplate->setValue('signature_agent', '');
                            break;
                    }

                    // save temp file
                    $wordTemplate->saveAs($tempFile);

                    if (file_exists($tempFile)) {
                        $this->logInfo('Filled template doc saved locally.', __METHOD__, __LINE__);
                    } else {
                        $this->logInfo('Filled template doc missing.', __METHOD__, __LINE__);

                        return [
                            'error',
                            'Contract generation failed: Filled template doc missing.',
                        ];
                    }

                    // convert temp file to pdf
                    $tmpDirName = '/tmp/soffice-' . time() . mt_rand(1000000, 9999999);

                    mkdir($tmpDirName);

                    /*
                    soffice

                    Requires Libre Office is installed, not Open Office.  Despite same filename of soffice.exe, they work differently
                    Used to convert DocX to PDF
                    - Libre Office is available for Windows, Mac, and 'nix
                    - MUST HAVE a 'PATH' to soffice.exe 
                    - Windows -> System Properties -> Advanced tab, click 'Environment Varaibles' button just above 'OK Cancel Apply' buttons
                      - in new window 'System Variables' panel at bottom, NOT 'user variables for %user%' at top, find PATH and hit EDIT
                      - in PATH variable, add 'C:\Program Files\LibreOffice\program' (default installation path) where the .exe file exists
                      - this *should* allow Windows to run Generate Contracts commands for this file
                      - Windows using Homestead / Docker will need to treat as a 'nix install, "sudo apt install libreoffice" or "brew install libreoffice" for Mac if homebrew is used

                    TODO: Need to update documenation to install on Mac / 'nix
                    https://www.linuxcapable.com/how-to-install-libreoffice-on-ubuntu-linux/
                    */

                    $exec_string = 'soffice';

                    // $this->options('windows') returns an Array, this deals with laravel screwiness in checking the value of the option for the --windows cmd line parameter
                    $windows_array = $this->options('windows');
                    $isWindows = $windows_array['windows'];
                    // If not running as Windows (should be our Default)
                    if (!$isWindows) {
                        // This is needed for 'nix, Ubuntu, Mac to run, does not work on Windows
                        $exec_string .= ' -env:UserInstallation=file://' . $tmpDirName;
                    }

                    // These options are needed for batch conversion of DocX to PDF without user interaction
                    $exec_string .= ' --headless --nocrashreport --nodefault --nofirststartwizard --nolockcheck --nologo --norestore --convert-to pdf ' . $tempFile . ' --outdir ' . $filepath;
                    // If --debug argument, log the command.  The command can be run from any CMD / Terminal / Bash prompt
                    if ($this->options('debug')) { $this->info('EXEC STRING: ' . $exec_string); }
                    $this->info('EXEC STRING: ' . $exec_string);
                    $this->logInfo('EXEC STRING: ' . $exec_string, __METHOD__, __LINE__);
                    $output = [];
                    $cRetVal = null;
                    exec($exec_string, $output, $cRetVal);

                    // Add --windows in "php artisan eztpv:generateContracts --noSlack --windows --confirmation_code=123" if script runs on Windows
                    if ($this->option('windows')) rmdir($tmpDirName);
                    else                          exec('rm -r ' . $tmpDirName);

                    if ($cRetVal != 0) {
                        $this->logError('DOC to PDF Conversion failed, and returned: ' . $cRetVal, __METHOD__, __LINE__);
                        $this->logError(print_r($output, true), __METHOD__, __LINE__);

                        return [
                            'error',
                            'DOC to PDF Conversion, pdf failed to convert and returned ' . $cRetVal,
                            $output,
                        ];
                    }
                    $this->logDebug('EXEC RETURNED: ' . $cRetVal, __METHOD__, __LINE__, $output);
                    $pdfFilename = $filename . '.pdf';
                    $this->logDebug('New PDF filename: ' . $pdfFilename, __METHOD__, __LINE__);
                    if (file_exists($pdfFilename)) {
                        $this->logInfo('Doc converted to pdf and saved locally.', __METHOD__, __LINE__);
                    } else {
                        $this->logInfo('Doc converted to pdf NOT found.', __METHOD__, __LINE__);

                        return [
                            'error',
                            sprintf('Contract generation failed: Doc converted to pdf NOT found. (%s)', $pdfFilename),
                            $output
                        ];
                    }
                    chmod($pdfFilename, 0744);
                    if (isset($s3File)) {
                        unlink($sourceDoc);
                        unset($s3File);
                    }
                    break;
                case '1':
                default:
                    // pdf
                    $fdf_data = $pdf_info->contract_fdf;
                    foreach ($vars as $var) {
                        if ($this->option('debug')) {
                            $this->info($var . ': ' . @$$var . PHP_EOL);
                        }

                        $search = $var;
                        $fdf_data = str_replace('[' . $search . ']', @$$var, $fdf_data);
                    }

                    // embed pdfdata
                    $pdfdata = '%FDF-1.2
                        %âãÏÓ
                        1 0 obj
                        <<
                        /FDF
                        <<
                        /Fields [';
                    $pdfdata .= $fdf_data;
                    $pdfdata .= ']
                        >>
                        >>
                        endobj
                        trailer

                        <<
                        /Root 1 0 R
                        >>
                        %%EOF';

                    //set filename for new files
                    $filepath = public_path('uploads/eztpv/documents/');
                    $filename = $filepath . 'eztpv' . '_' . $eztpv->id
                        . '_' . date('d.m.Y.H.i.s') . '_' . mt_rand(1, 99999);
                    //append fdf extension to filename for file ops
                    $extension = '.fdf';
                    $datafile = $filename . $extension;

                    //open (create) fdf file
                    $file = fopen($datafile, 'w');
                    //write data to fdf file
                    fwrite($file, $pdfdata);
                    //close fdf file
                    fclose($file);
                    //change permissions on fdf file
                    chmod($datafile, 0744);

                    if (!file_exists($datafile)) {
                        return [
                            'error',
                            'Contract generation failed: local generated fdf file not found',
                        ];
                    }

                    // set exec string to create pdf file from fdf file
                    $pdftk = (config('app.pdftk_path')) ? config('app.pdftk_path') : 'pdftk';
                    $this->logInfo('pdftk area - ' . $pdf_info->contract_pdf, __METHOD__, __LINE__);
                    $pdfCheck = explode('.', $pdf_info->contract_pdf);
                    if (
                        !isset($pdfCheck)
                        || (isset($pdfCheck)
                            && (count($pdfCheck) < 2
                                || $pdfCheck[1] != 'pdf'))
                    ) {
                        $this->logError('PDF Generation cannot proceed: Contract configured for PDF, but file is DOCX', __METHOD__, __LINE__);

                        return [
                            'error',
                            'PDF Generation cannot proceed: Contract configured for PDF, but file is DOCX',
                        ];
                    }
                    $exec_string = $pdftk . ' '
                        . resource_path('assets/documents/' . $pdf_info->contract_pdf)
                        . ' fill_form '
                        . $datafile
                        . ' output '
                        . $filename . '.pdf'
                        . ' flatten'; //2>&1
                    $this->logDebug('EXEC_STRING IS ' . $exec_string, __METHOD__, __LINE__);

                    //execute exec string
                    passthru($exec_string, $output);
                    if ($output != 0) {
                        $this->logError('PDF Generation failed, and returned: ' . $output, __METHOD__, __LINE__);

                        return [
                            'error',
                            'PDF Generation Error (1), pdf failed to generate. Code: ' . $output,
                        ];
                    }
                    $this->logDebug('EXEC RETURNED: ' . $output, __METHOD__, __LINE__);
                    break;
            }

            $dir = 'uploads/pdfs/' . $eztpv->brand_id . '/'
                . $this->eztpv->event->vendor_id . '/' . date('Y-m-d') . '/tmp';
            $s3filename = md5($filename) . '.pdf';
            $keyname = $dir . '/' . $s3filename;

            $uploadAttempt = $this->s3Upload($keyname, $filename . '.pdf');

            // Store the last contract
            $this->contract_data = ['file' => $keyname, 'content' => file_get_contents($filename . '.pdf')];

            if (
                is_array($uploadAttempt)
                && $uploadAttempt[0] === 'success'
            ) {
                if (isset($datafile)) {
                    unlink($datafile);
                }
                if (isset($tempFile)) {
                    if (!$this->option('debug')) {
                        unlink($tempFile);
                    } else {
                        $this->info('Temp DOC file in: ' . $tempFile);
                    }
                }
                if (isset($filename) && file_exists($filename . '.pdf')) {
                    unlink($filename . '.pdf');
                }
                $filenames[] = [
                    'file' => $keyname,
                    'pdf_info_id' => $pdf_info->id,
                ];

                $this->info(config('services.aws.cloudfront.domain') . '/' . $keyname);
            } elseif (
                is_array($uploadAttempt)
                && $uploadAttempt[0] === 'error'
            ) {
                return $uploadAttempt;
            }
        }

        // needs to be an array of filenames
        return $filenames;
    }

    public function complete_contract_document($eztpv, $data, $event_id, $signature_customer, $signature_agent)
    {
        $this->logInfo('Entered function...', __METHOD__, __LINE__);

        unset($error);

        $contactcheck = false;

        $previewReturn = [
            0 => 'preview',
            'files' => [],
        ];

        for ($x = 0; $x <= count($data['contracts']) - 1; ++$x) {
            if (Storage::disk('s3')->exists($data['contracts'][$x]['file'])) {
                if (
                    $this->eztpv->contract_type != 4
                ) {
                    $this->logInfo('----------', __METHOD__, __LINE__);
                    $this->logInfo('Contracts: ' . print_r($data['contracts'], true), __METHOD__, __LINE__);
                    $this->logInfo('----------', __METHOD__, __LINE__);
                    $this->logInfo('Complete PDF: ' . $data['contracts'][$x]['file'] . ' exists on s3.', __METHOD__, __LINE__);

                    $pdf_info = BrandEztpvContract::find($data['contracts'][$x]['pdf_info_id']);

                    $this->logInfo('----------', __METHOD__, __LINE__);
                    $this->logInfo('PDF Info pulled: ' . $pdf_info, __METHOD__, __LINE__);
                    $this->logInfo('----------', __METHOD__, __LINE__);

                    if (
                        $pdf_info->document_file_type_id == 1
                        || $pdf_info->document_file_type_id == '1'
                    ) {
                        if (
                            $signature_customer
                            && $pdf_info->signature_required_customer == 1
                            && !$this->option('preview')
                        ) {
                            $sigfile_customer = $this->generateSigfile($signature_customer);
                            if (
                                $sigfile_customer
                                && is_array($sigfile_customer)
                                && $sigfile_customer[0] === 'error'
                            ) {
                                return $sigfile_customer;
                            } else {
                                // $sigfile_customer_measure = $this->getSignatureDimensions($sigfile_customer);
                                // if (
                                //     is_array($sigfile_customer_measure)
                                //     && $sigfile_customer_measure[0] == 'error'
                                //     ) {
                                //     return $sigfile_customer_measure;
                                // }
                            }
                        }

                        if (
                            $signature_agent
                            && $pdf_info->signature_required_agent == 1
                            && !$this->option('preview')
                        ) {
                            $sigfile_agent = $this->generateSigfile($signature_agent);
                            if (
                                $sigfile_agent
                                && is_array($sigfile_agent)
                                && $sigfile_agent[0] === 'error'
                            ) {
                                return $sigfile_agent;
                            } else {
                                // $sigfile_agent_measure = $this->getSignatureDimensions(@$sigfile_agent);
                                // if (
                                //     is_array($sigfile_agent_measure)
                                //     && $sigfile_agent_measure[0] == 'error'
                                //     ) {
                                //     return $sigfile_agent_measure;
                                // }
                            }
                        }

                        // $pdf_sig_info_customer = $pdf_info->signature_info_customer;
                        // $pdf_sig_info_agent = $pdf_info->signature_info_agent;

                        if (Storage::disk('s3')->exists($data['contracts'][$x]['file'])) {
                            $this->logInfo('Filled template exists on s3.', __METHOD__, __LINE__);
                        } else {
                            $this->logInfo('Filled template not found on s3.', __METHOD__, __LINE__);

                            return [
                                'error',
                                'Contract generation failed: Filled template not found on s3.',
                            ];
                        }
                        $sourceFile = Storage::disk('s3')->get($data['contracts'][$x]['file']);
                        $deletS3File = Storage::disk('s3')->delete($data['contracts'][$x]['file']);

                        $pdfOutputFile = public_path('tmp/' . mt_rand(1, 9999999999) . '.pdf');

                        $tmpFile = fopen($pdfOutputFile, 'w');
                        fwrite($tmpFile, $sourceFile);
                        fclose($tmpFile);

                        if (file_exists($pdfOutputFile)) {
                            $this->logInfo('Filled templated downloaded from s3 and saved locally.', __METHOD__, __LINE__);
                        } else {
                            $this->logInfo('Filled template downloaded from s3 but not found locally.', __METHOD__, __LINE__);

                            return [
                                'error',
                                'Contract generation failed: Filled template downloaded from s3 but not found locally.',
                            ];
                        }

                        if (
                            (1 === $pdf_info->signature_required_customer
                                || 1 === $pdf_info->signature_required_agent)
                            && !$this->option('preview')
                        ) {
                            if ('none' != $pdf_info->signature_info_customer) {
                                if (isset($sigfile_customer)) {
                                    // PDF extension
                                    // $siginfo = json_decode($pdf_info->signature_info);
                                    $pageSize = $pdf_info->page_size;

                                    $pdf = new \setasign\Fpdi\Fpdi('P', 'pt', $pageSize);
                                    $pdf->setSourceFile($pdfOutputFile);
                                    //$numberOfPages = $pdf_info->number_of_pages;

                                    $pdf = $this->signatureEmbed(
                                        $pdf_info->number_of_pages,
                                        $pdf,
                                        json_decode($pdf_info->signature_info_customer),
                                        json_decode($pdf_info->signature_info_agent),
                                        $sigfile_customer,
                                        // $sigfile_customer_measure,
                                        @$sigfile_agent
                                        // @$sigfile_agent_measure
                                    );
                                } else {
                                    return [
                                        'sigfile_customer_error',
                                        'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/sigfile_customer_error',
                                    ];
                                }

                                // if (
                                //     'none' != $pdf_info->signature_info_agent
                                //     || !is_null($pdf_info->signature_info_agent)
                                // ) {
                                //     // agent
                                //     $pdf = $this->signatureEmbed(
                                //         $pdf_info->number_of_pages,
                                //         $pdf,
                                //         json_decode($pdf_info->signature_info_agent),
                                //         $sigfile_agent
                                //     );
                                // }

                                $pdf->Output(
                                    'F',
                                    $pdfOutputFile
                                );

                                if (file_exists($pdfOutputFile)) {
                                    $this->logInfo('Output pdf saved locally.', __METHOD__, __LINE__);
                                } else {
                                    $this->logInfo('Output pdf not found on local drive.', __METHOD__, __LINE__);

                                    return [
                                        'error',
                                        'Contract generation failed: Output pdf not found on local drive.',
                                    ];
                                }
                            }
                        }

                        $dir = 'uploads/pdfs/' . $eztpv->brand_id . '/'
                            . $this->eztpv->event->vendor_id . '/' . date('Y-m-d');
                        $s3filename = md5($data['contracts'][$x]['file']) . '.pdf';
                        $keyname = $dir . '/' . $s3filename;
                        try {
                            $s3 = Storage::disk('s3')->put(
                                $keyname,
                                file_get_contents($pdfOutputFile),
                                'public'
                            );
                            if (Storage::disk('s3')->exists($keyname)) {
                                unlink($pdfOutputFile);
                            } else {
                                return [
                                    's3_error',
                                    'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/s3_reports_file_does_not_exist',
                                ];
                            }
                        } catch (\Aws\S3\Exception\S3Exception $e) {
                            return [
                                's3_error',
                                'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/s3_exception',
                                $e,
                            ];
                        }
                    }

                    switch ($pdf_info->document_file_type_id) {
                        case '2':
                        case 2:
                            $fileToUpload = $data['contracts'][$x]['file'];
                            break;

                        case '1':
                        case 1:
                        default:
                            $fileToUpload = $keyname;
                            break;
                    }
                }

                if ($this->eztpv->contract_type == 4) {
                    $fileToUpload = $data['contracts'][$x]['file'];
                }

                $this->logInfo('Creating new Upload record in DB...', __METHOD__, __LINE__);
                $upload = new Upload();
                $upload->brand_id = $this->eztpv->event->vendor_id;
                $upload->user_id = ($this->eztpv->user_id !== null)
                    ? $this->getUserId($this->eztpv->user_id)
                    : null;
                $upload->upload_type_id = 3;
                $upload->filename = $fileToUpload;
                $upload->save();

                $previewReturn['files'][] = $fileToUpload;

                $this->logInfo('Creating new EzTpvDocument record in DB...', __METHOD__, __LINE__);
                $document = new EzTpvDocument();
                $document->eztpv_id = $eztpv->id;
                $document->event_id = $event_id;
                $document->sales_agent_id = ($this->eztpv->user_id !== null)
                    ? $this->getUserId($this->eztpv->user_id)
                    : null;
                $document->uploads_id = $upload->id;
                $document->channel_id = $this->eztpv->event->channel_id;
                $document->market_id = $data['market_id'];
                $document->state_id = $this->eztpv->event->products[0]->serviceAddress->address->state->id;
                $document->products_index = $x;
                if ($this->option('preview')) {
                    $document->preview_only = 1;
                }
                $document->save();

                $this->logInfo('Checking if event->synced should be reset to 0...', __METHOD__, __LINE__);
                $event = Event::where(
                    'id',
                    $event_id
                )->where(
                    'synced',
                    1
                )->first();

                if ($event) {
                    $this->logInfo('Resetting event->synced to 0...', __METHOD__, __LINE__);
                    // If a new contract is generated,
                    //   and brand file sync has already been run,
                    //   reset synced to 0
                    $event->synced = 0;
                    $event->save();
                }

                if (!$this->option('preview')) {
                    $contactcheck = true;
                }
            } else {
                $this->logInfo(
                    'Complete PDF: The file: '
                        . $data['contracts'][$x]['file']
                        . ' does not exist.  skipping...', 
                    __METHOD__, __LINE__
                );

                continue;
            }
        }

        if ($contactcheck) {
            // email contracts?
            if (
                isset($this->eztpv->eztpv_contract_delivery)
                && 'email' === $this->eztpv->eztpv_contract_delivery
            ) {
                $this->logInfo('Delivering contract via Email...', __METHOD__, __LINE__);
                $this->sendCustomerEmail($event_id, $data, $eztpv);

                $event2 = Event::where('id', $event_id)->first();                

                // If Brand Service is Enabled, then an Email Copy is sent to the Sales Agent
                $this->sendEmailCopyToAccountManagers($event2, $eztpv, $data);
            }

            // text contracts?
            if (
                isset($this->eztpv->eztpv_contract_delivery)
                && 'text' === $this->eztpv->eztpv_contract_delivery
            ) {
                $this->logInfo('Delivery contract via Text...', __METHOD__, __LINE__);
                $this->sendCustomerText($data, $eztpv);
            }

            // delete signature temp file
            if (isset($sigfile_customer)) {
                $this->info('Deleting customer sigfile');
                $this->unlinkFile($sigfile_customer);
            }
            if (isset($sigfile_agent)) {
                $this->info('Deleting agent sigfile');
                $this->unlinkFile($sigfile_agent);
            }
        }

        if ($this->option('preview')) {
            return $previewReturn;
        } else {
            return true;
        }
    }

    private function generateSigfile($sig_output)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        $sig_data = explode(',', trim($sig_output));
        if (isset($sig_data) && count($sig_data) > 1) {
            $sig_data[1] = trim($sig_data[1]);
            if (
                isset($sig_data)
                && isset($sig_data[1])
                && strlen($sig_data[1]) > 0
            ) {
                $sigfile = public_path('uploads/eztpv/signature' . bin2hex(random_bytes(12)) . '.png');
                $sig = fopen($sigfile, 'wb');
                if ($sig !== false) {
                    $ret = fwrite($sig, base64_decode($sig_data[1]));
                    if ($ret === false) {
                        fclose($sig);
                        return [
                            'error',
                            'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/cannot_write_signature_data',
                        ];
                    }
                    $ret = fclose($sig);
                    if ($ret === false) {
                        return [
                            'error',
                            'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/error_closing_signature_file',
                        ];
                    }
                    $ret = chmod($sigfile, 0744);
                    if ($ret === false) {
                        return [
                            'error',
                            'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/cannot_repermission_sig_file',
                        ];
                    }
                } else {
                    return [
                        'error',
                        'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/cannot_write_file',
                    ];
                }
            } else {
                return [
                    'error',
                    'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/incomplete_signature_data',
                ];
            }

            return $sigfile;
        }
    }

    private function signatureEmbed(
        $numberOfPages,
        $pdf,
        $siginfo_customer,
        $siginfo_agent = null,
        $sigfile_customer,
        // $sigfile_customer_measure,
        $sigfile_agent = null
        // $sigfile_agent_measure = null
    ) {
        for ($pageCounter = 1; $pageCounter <= $numberOfPages; ++$pageCounter) {
            $pdfTemplate = $pdf->importPage($pageCounter);
            $pdf->addPage();
            $pdf->useTemplate($pdfTemplate);
            //customer
            foreach ($siginfo_customer as $page => $info) {
                if ($page == $pageCounter) {
                    foreach ($info as $key => $value) {
                        $values = explode(',', $value);
                        if (
                            isset($values[0])
                            && isset($values[1])
                            && isset($values[2])
                            && isset($values[3])
                        ) {
                            if (isset($sigfile_customer)) {
                                // $targetHeightInPx = intval($values[3] * 1.33);
                                // dd('siginfo value: ' . $values[3] . ', calculated pixels: ' . $targetHeightInPx);
                                $imageSize = getimagesize($sigfile_customer);
                                $heightInPts = $imageSize[1] / 1.33;
                                if ($heightInPts > $values[3]) {
                                    $this->resizeImageByHeight($sigfile_customer, $values[3]);
                                    $heightInPts = $values[3];
                                }
                                // dd($height);
                                // $height = 16;
                                // $after = getimagesize($sigfile_customer);
                                // dd('Before: ' . $before[3] . ', After: ' . $after[3]);
                                // // $this->resizeImageByWidth($sigfile_customer, $values[2]);
                                // switch ($sigfile_customer_measure) {
                                //     case 'tall':
                                //         $targetHeightInPx = intval($values[3] * 1.33);
                                //         $sigfile_customer = Image::make($sigfile_customer)->heighten($targetHeightInPx, function ($constraint) {
                                //             $constraint->upsize();
                                //         });
                                //         $sigfile_customer = $sigfile_customer->dirname . '/' . $sigfile_customer->basename;
                                $pdf->Image(
                                    $sigfile_customer,
                                    $values[0],
                                    $values[1],
                                    0,
                                    $heightInPts
                                );
                                //         break;

                                //     case 'wide':
                                //         $targetWidthInPx = intval($values[2] * 1.33);
                                //         $sigfile_customer = Image::make($sigfile_customer)->widen($targetWidthInPx, function ($constraint) {
                                //             $constraint->upsize();
                                //         });
                                //         $sigfile_customer = $sigfile_customer->dirname . '/' . $sigfile_customer->basename;
                                // $pdf->Image(
                                //     $sigfile_customer,
                                //     $values[0],
                                //     $values[1],
                                //     $values[2],
                                //     0
                                // );
                                // break;
                                // }
                            } else {
                                return [
                                    'signature_error',
                                    'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/required_signature_missing',
                                ];
                            }
                        } else {
                            return [
                                'signature_error',
                                'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/signature_info_failure',
                            ];
                        }
                    }
                }
            }
            if ($siginfo_agent && $sigfile_agent) {
                foreach ($siginfo_agent as $page => $info) {
                    if ($page == $pageCounter) {
                        foreach ($info as $key => $value) {
                            $values = explode(',', $value);
                            if (
                                isset($values[0])
                                && isset($values[1])
                                && isset($values[3])
                            ) {
                                if (isset($sigfile_agent)) {
                                    // $targetHeightInPx = intval($values[3] * 1.33);
                                    $imageSize = getimagesize($sigfile_agent);
                                    $heightInPts = $imageSize[1] / 1.33;
                                    if ($heightInPts > $values[3]) {
                                        $this->resizeImageByHeight($sigfile_agent, $values[3]);
                                        $heightInPts = $values[3];
                                    }
                                    // $this->resizeImageByWidth($sigfile_agent, $values[2]);
                                    // switch ($sigfile_agent_measure) {
                                    //     case 'tall':
                                    //         $targetHeightInPx = intval($values[3] * 1.33);
                                    //         $sigfile_agent = Image::make($sigfile_agent)->heighten($targetHeightInPx, function ($constraint) {
                                    //             $constraint->upsize();
                                    //         });
                                    //         $sigfile_agent = $sigfile_agent->dirname . '/' . $sigfile_agent->basename;
                                    $pdf->Image(
                                        $sigfile_agent,
                                        $values[0],
                                        $values[1],
                                        0,
                                        $heightInPts
                                    );
                                    //         break;

                                    //     case 'wide':
                                    //         $targetWidthInPx = intval($values[2] * 1.33);
                                    //         $sigfile_agent = Image::make($sigfile_agent)->widen($targetWidthInPx, function ($constraint) {
                                    //             $constraint->upsize();
                                    //         });
                                    //         $sigfile_agent = $sigfile_agent->dirname . '/' . $sigfile_agent->basename;
                                    // $pdf->Image(
                                    //     $sigfile_agent,
                                    //     $values[0],
                                    //     $values[1],
                                    //     $values[2],
                                    //     0
                                    // );
                                    // break;
                                    // }
                                } else {
                                    return [
                                        'signature_error',
                                        'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/required_signature_missing',
                                    ];
                                }
                            } else {
                                return [
                                    'signature_error',
                                    'MGMT/generateEztpvContracts/EzTpvDocumentTrait/complete_contract_document/signature_info_failure',
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $pdf;
    }

    public function getAddresses($product)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        $service_address = [];
        $billing_address = [];
        if (isset($product['addresses'])) {
            for ($i = 0; $i < count($product['addresses']); ++$i) {
                if ('e_p:service' == $product['addresses'][$i]['id_type']) {
                    $service_address['address']
                        = $product['addresses'][$i]['address']['line_1'];
                    $service_address['address2']
                        = $product['addresses'][$i]['address']['line_2'];
                    $service_address['city']
                        = $product['addresses'][$i]['address']['city'];
                    $service_address['state']
                        = $product['addresses'][$i]['address']['state_province'];
                    $service_address['zip']
                        = $product['addresses'][$i]['address']['zip'];

                    $zips = Cache::remember(
                        'zip_code_by_zip' . $service_address['zip'],
                        7200,
                        function () use ($service_address) {
                            return ZipCode::where(
                                'zip',
                                $service_address['zip']
                            )->first();
                        }
                    );

                    if ($zips) {
                        $service_address['county'] = $zips->county;
                        $service_address['country'] = (2 == $zips->country)
                            ? 'Canada' : 'United States';
                    } else {
                        $service_address['county'] = null;
                        $service_address['country'] = 'United States';
                    }
                } else {
                    $billing_address['address']
                        = $product['addresses'][$i]['address']['line_1'];
                    $billing_address['address2']
                        = $product['addresses'][$i]['address']['line_2'];

                    $billing_address['city']
                        = $product['addresses'][$i]['address']['city'];
                    $billing_address['state']
                        = $product['addresses'][$i]['address']['state_province'];
                    $billing_address['zip']
                        = $product['addresses'][$i]['address']['zip'];

                    $zips = Cache::remember(
                        'zip_code_by_zip' . $billing_address['zip'],
                        7200,
                        function () use ($billing_address) {
                            return ZipCode::where(
                                'zip',
                                $billing_address['zip']
                            )->first();
                        }
                    );

                    if ($zips) {
                        $billing_address['county'] = $zips->county;
                        $billing_address['country'] = (2 == $zips->country)
                            ? 'Canada' : 'United States';
                    } else {
                        $billing_address['county'] = null;
                        $billing_address['country'] = 'United States';
                    }
                }
            }
        }

        if (!isset($billing_address['address'])) {
            $billing_address = $service_address;
        }

        return ['service' => $service_address, 'billing' => $billing_address];
    }    

    /**
     * Extract Image from Request.
     *
     * @param string $image - image string
     *
     * @return bool
     */
    public function extractImageFromRequest($image)
    {
        $this->logInfo('Extracing image data...', __METHOD__, __LINE__);

        $photo_data = explode(',', $image);
        if (isset($photo_data[1])) {
            return $photo_data[1];
        }

        return false;
    }

    public function savePhotoLocal($image, $filename)
    {
        $this->logInfo('Saving local image file from image data...', __METHOD__, __LINE__);

        $photo = fopen($filename, 'wb');
        fwrite($photo, base64_decode($image));
        fclose($photo);
        chmod($filename, 0744);

        return;
    }

    public function getUserId($brand_user_id)
    {
        $this->logInfo('Retrieving user ID...', __METHOD__, __LINE__);

        $bu = BrandUser::withTrashed()
            ->find($brand_user_id);

        return $bu->user_id;
    }

    // isGreenBrandId(Uuid $brand_id, ?int $state_id)
    // - Author: Damian McQueen, Aug 3rd, 2022
    // - Uses private const brand_id array for associative arrays and value pairs so we can see in code what is what
    // - Returns true if given $brand_id is the ID for a "Green" Company, $state_id is optional for some companies
    private function isGreenBrandId($brand_id, $state_id = null){
        // null brand_ids can not be green
        if (!$brand_id) return false;

        // if $brand_id is for a "Green" company return true, otherwise dont return anything which evals to false

        if (in_array($brand_id, self::BRAND_IDS['idt_energy'])) return true;
        if (in_array($brand_id, self::BRAND_IDS['residents_energy'])) return true;
        if (in_array($brand_id, self::BRAND_IDS['townsquare_energy'])) return true;
        if (in_array($brand_id, self::BRAND_IDS['browns_energy'])) return true;
        if (in_array($brand_id, self::BRAND_IDS['txu_energy'])) return true;
        if (in_array($brand_id, self::BRAND_IDS['usge'])) return true;
    }    

    private function getPdfInfo(
        $mode,
        $event,
        $brand_id,
        $state_id,
        $channel_id,
        $market_id,
        $commodity,
        $language_id,
        $document_type_id,
        $product,
        $rate_type_id = null,
        $rate_amount = null,
        $intro_rate_amount = null
    ) {
        DB::enableQueryLog();

        $this->logInfo('Getting PDF info for: ' . $event->confirmation_code, __METHOD__, __LINE__);

        $this->info('Running getPdfInfo() for ' . $event->confirmation_code);
        // info(print_r($product->toArray(), true));

        $pdf_info = BrandEztpvContract::where(
            'brand_id',
            $brand_id
        )->where(
            'state_id',
            $state_id
        )->where(
            'channel_id',
            $channel_id
        )->where(
            'market_id',
            $market_id
        )->where(
            'language_id',
            $language_id
        );

        // Check if Brand ID and state ID are configure to use green contracts
        if ($this->isGreenBrandId($brand_id, $state_id)) {
            $this->logInfo('Running isGreenBrandId specific code', __METHOD__, __LINE__);

            $green = 0;
            if (
                isset($product->rate)
                && isset($product->rate->product)
                && isset($product->rate->product->green_percentage)
            ) {
                $green = (isset($product->rate->product->green_percentage)
                    && $product->rate->product->green_percentage > 0)
                    ? 1 : 0;
            }

            $pdf_info = $pdf_info->where(
                'product_type',
                $green
            );
        }

        if (
            $brand_id == 'c03d58ed-1bb0-4e35-9e11-94c1e3bd59cc' // clearview
            && $state_id == 36 // Ohio
        ) {
            // do nothing
        } else {
            $pdf_info = $pdf_info->where(
                'commodity',
                $commodity
            );
        }

        if (
            $rate_type_id !== null
            && (
                // exclude FTE eztpvs from selection by rate type
                $brand_id != '04B0F894-172C-470F-813B-4F58DBD35BAE')
        ) {
            if ($this->option('debug')) {
                $this->info('Using rate type as hint for contract selection.');
            }
            $pdf_info = $pdf_info->where(
                'rate_type_id',
                $rate_type_id
            );

            switch ($rate_type_id) {
                case '1':
                case 1:
                    // nothing needed for Fixed
                    break;

                case '2':
                case 2:
                    // nothing needed for Variable
                    break;

                case '3':
                case 3:
                    // tiered: Fixed Tiered and Tiered Variable
                    if (
                        $rate_amount > 0
                    ) {
                        $pdf_info->where(
                            'contract_pdf',
                            'LIKE',
                            '%fixed-tiered%'
                        );
                    } else {
                        $pdf_info->where(
                            'contract_pdf',
                            'LIKE',
                            '%tiered-variable%'
                        );
                    }
                    break;
            }
        } else {
            if ($this->option('debug')) {
                $this->info('Generic Rate type not included for contract selection (1)');
            }
        }

        $this->logInfo('Brand ID is ' . $brand_id, __METHOD__, __LINE__);

        // brand-specific contract selection
        switch ($brand_id) {
            case self::BRAND_IDS['nordic_energy']['staging']:
            case self::BRAND_IDS['nordic_energy']['production']:
            case self::BRAND_IDS['greenwave_energy']['staging']:
            case self::BRAND_IDS['greenwave_energy']['production']:
            case self::BRAND_IDS['spark_energy']['staging']:
            case self::BRAND_IDS['spark_energy']['staging2']:
            case self::BRAND_IDS['spark_energy']['production']:
                // Add language_id to sql query for cases above
                $pdf_info->where('language_id', $this->eztpv->event->language_id);
                break;
            case 'c03d58ed-1bb0-4e35-9e11-94c1e3bd59cc':
                // Clearview Energy

                // $rate_product_name = explode(' - ', $product->rate->product->name);
                // $pdf_info = $pdf_info->where(
                //     'contract_pdf',
                //     'LIKE',
                //     '%' . $rate_product_name[0] . '\_%'
                // );

                // select by products.id
                if (
                    isset($product)
                    && isset($product->rate)
                    && isset($product->rate->product)
                    && isset($product->rate->product->id)
                ) {
                    $pdf_info = $pdf_info->where(
                        'product_id',
                        $product->rate->product->id
                    );
                }

                break;

            case '4e65aab8-4dae-48ef-98ee-dd97e16cbce6':
            case 'eb35e952-04fc-42a9-a47d-715a328125c0':
                // Indra
                $pdf_info->where('language_id', $this->eztpv->event->language_id);

                $pdf_info->where('rate_type_id', $product->rate->product->rate_type_id);

                switch ($product->serviceAddress->address->state->id) {
                    case 14:
                        // Illinois

                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product->rate->product->id
                        );
                        break;

                    default:
                        $this->info('Indra not illinois selection');
                        // everywhere else
                        switch ($product->rate->product->rate_type_id) {
                            case '1':
                            case '2':
                            case 1:
                            case 2:
                                break;

                            case '3':
                            case 3:
                                // tiered: Fixed Tiered and Tiered Variable
                                if (
                                    $product->rate->rate_amount > 0
                                ) {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'indra%fixed-tiered%'
                                    );
                                } else {
                                    $pdf_info->where(
                                        'contract_pdf',
                                        'LIKE',
                                        'indra%tiered-variable%'
                                    );
                                }
                                break;

                            default:
                                break;
                        }
                        break;
                }
                break;

            case '0e80edba-dd3f-4761-9b67-3d4a15914adb':
                // Residents Energy
                //$this->eztpv->event->products[0]->utility_id
                if ($state_id == 14 && in_array($rate_type_id, [2, 3])) {
                    $this->info('STATE is Illinois and is in RESIDENCE ENERGY *************');
                    $pdf_info = $pdf_info->where('utility_id', $product->utility_id);
                }
                break;

            case '77c6df91-8384-45a5-8a17-3d6c67ed78bf':
                // IDT Energy
                if ($state_id == 14 && in_array($rate_type_id, [2, 3])) {
                    $this->info('STATE is Illinois and is IDT ENERGY *************');
                    $pdf_info = $pdf_info->where('utility_id', $product->utility_id);
                }
                break;

            case '872c2c64-9d19-4087-a35a-fb75a48a1d0f':
            case 'dda4ac42-c7b8-4796-8230-9668ad64f261':
                // Town Square
                if ($state_id == 14 && in_array($rate_type_id, [2, 3])) {
                    $this->info('STATE is Illinois and is TOWNSQUARE *************');
                    $pdf_info = $pdf_info->where('utility_id', $product->utility_id);
                }
                break;

            case '52f9b7cd-2395-48e9-a534-31f15eebc9d4':
            case 'faeb80e2-16ce-431c-bb54-1ade365eec16':
                // Rushmore Energy
                $api_submission = false;
                foreach ($event->interactions as $interaction) {
                    if ($interaction->interaction_type_id === 11) {
                        $api_submission = true;
                    }
                }
                if (
                    $api_submission === true
                ) {
                    switch ($commodity) {
                        case 'electric':
                            // select by products.id
                            $pdf_info = $pdf_info->where(
                                'product_id',
                                $product->rate->product->id
                            )->where(
                                'rate_id',
                                $product->rate->id
                            );
                            break;

                        case 'gas':
                            // select by products.id
                            $pdf_info = $pdf_info->where(
                                'product_id',
                                $product->rate->product->id
                            )->where(
                                'rate_id',
                                $product->rate->id
                            );
                            break;
                    }
                    break;
                } else {
                    // on 10-20 Paul and Lauren requested product-specific contract selection for specific product ids, having been informed at the time that this code will break when IDs/products are updated
                    // on 11-13 Paul requested product/rate-specific contract selection for specific prodcuts/rates, as above
                    $rushmoreProducts = [
                        '35fe3857-13ae-402b-ac3b-57ac3d383a8e',
                        '01349271-3b0d-4856-ac0e-fb417dd2f084',
                        '3163144d-7c73-4d6f-9671-96deec6230d7',
                        'df0fb122-bc8e-4b79-944b-94eabd9dfc48',
                        '3d5719a3-7625-4c44-8d72-19e745f053f4',
                        'f58328ae-5245-47cd-91d8-d299a6eeb83d',
                        'cb4d3e7c-7d73-48c4-a681-f7fca68905cd',
                        'f5726206-98c4-4757-80d4-6780d33a462e',
                        'd7a1ab7f-367d-4347-88c0-4a4b787b5470', // PA - Door to Door - Fixed - 36 Month Production
                        'a3592c0b-3aec-4b97-a736-4ffa4460e4a5', // PA - Door to Door - Fixed - 36 Month New Staging
                        'd3172ab4-cb4f-4182-a211-311ff96dc58e'  // PA - Door to Door - Fixed - 36 Month Old Staging
                    ];

                    $rateSpecificProducts = [
                        '9cc79fd7-3581-4347-8b6b-46f0e07e2921',
                        'cea2c2e4-7175-497e-a8b6-dacde0abcc69'
                    ];

                    if (in_array($product->rate->product->id, $rushmoreProducts)) {
                        // select by products.id
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product->rate->product->id
                        );
                    } elseif (in_array($product->rate->product_id, $rateSpecificProducts)) {
                        // select by products.id and rates.id
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product->rate->product->id
                        )
                            ->where(
                                'rate_id',
                                $product->rate->id
                            );
                    } else {
                        // do not select by product_id
                        $pdf_info = $pdf_info->whereNull(
                            'product_id'
                        );
                    }
                }
                break;

            case 'd3970a96-e933-4cae-a923-e0daa7a59b4d':
            case '31b177d0-33d6-4c51-9907-5b57f68a9526':
                // Kiwi
                switch ($product->serviceAddress->address->state->id) {
                    case 33:
                        // New York
                        $pdf_info = $pdf_info->where(
                            'product_id',
                            $product->rate->product->id
                        );
                        break;
                    default:
                        // Nothing
                }

                break;

            case '1f402ff3-dace-4aea-a6b2-a96bbdf82fee':
            case 'd758c445-6144-4b9c-b683-717aadec83aa':
                // Spring

                // product-specific hard-coding
                // $specificProducts = [
                //     'aa4bdefd-b08d-4af7-af64-567db52e6c30', // Spring Guard Gas - MD (production, deleted)
                //     '29bc91d6-f61c-4772-bf17-1199f8a47175', // Spring Guard Electricity - MD (production, deleted)
                //     '2a11432a-fed5-4f0f-a583-eb5c4c581311', // Spring Guard Gas - MD (staging)
                //     'e6cb63cc-0cd5-4070-b86a-e56d37bdd181', // Spring Guard Electricity - MD (staging)
                //     '9417be74-1c97-477a-8e5b-3dbee6827ca1', // Spring Green Electricity - MD (staging)
                //     '80f518a2-4a65-4ba9-b485-d161d40dc658', // Zero Gas - MD (staging)
                //     '0e3a5926-4ada-44b8-9f95-4cc2ccacafaf', // Spring Green Electricity - MD (production)
                //     '8ee8c997-cbbc-43d8-b2d9-88de49fb4e64', // Zero Gas - MD (production)
                // ];
                $specificProducts = [

                    // STAGING                    
                    'c5f465bb-9a4e-4de2-afd3-94235e8de008', // Spring Green 50 - MD
                    '9f23980e-fe96-49dc-b1aa-d2f7b82dceaa', // Spring Green 50 - PA
                    '9417be74-1c97-477a-8e5b-3dbee6827ca1', // Spring Green Electricity - MD
                    '2a11432a-fed5-4f0f-a583-eb5c4c581311', // Spring Guard Gas - MD
                    'e6cb63cc-0cd5-4070-b86a-e56d37bdd181', // Spring Guard Electricity - MD
                    '80f518a2-4a65-4ba9-b485-d161d40dc658', // Zero Gas - MD
                    '9d66cfdf-b398-4c02-abd9-9a567f5303ba', // Zero Gas 50 - PA
                    'cd2282e3-1672-463e-8b1f-87f804445ab4', // Zero Gas 50 - MD

                    // PRODUCTION
                    'a303fafd-e36a-4fa9-bc53-8751001de8eb', // Spring Green 50 - MD
                    '60c42cdc-ff64-4cbe-9516-e0373b4341e0', // Spring Green 50 - PA
                    'cd778efb-4813-4b4a-842b-1cc8abcebbb5', // Spring Green Electricity - MD
                    'aa4bdefd-b08d-4af7-af64-567db52e6c30', // Spring Guard Gas - MD
                    '50594bc7-a0ed-482f-9ab4-2f5c599b2423', // Zero Gas - MD
                    '49de1651-c02e-427d-9098-65e334f1f9a6', // Zero Gas - MD (AK - 2022-05-05 - Yet another version of this product we need to track...)
                    'd57f2d70-4a22-4a68-b5bf-2ac017604e34', // Zero Gas 50 - MD
                    'd7376765-aed6-4ad0-83ad-ecad033d7efb', // Zero Gas 50 - PA
                    
                    // TEMP
                    '5c11e603-d3a8-4ce9-a6ff-c951b59785f0', // Spring Zero Gas - MD
                    '6e5ec0cc-e775-4ada-9eae-cc3f8aa5c66f', // SPrint Green Electricity - MD
                ];

                if (in_array($product->rate->product->id, $specificProducts)) {
                    // select by products.id
                    $pdf_info = $pdf_info->where(
                        'product_id',
                        $product->rate->product->id
                    );
                } else {
                    // do not select by product_id
                    $pdf_info = $pdf_info->whereNull(
                        'product_id'
                    );
                }
                break;
        }

        switch ($mode) {
            case 'get':
                $this->logInfo('In get mode.', __METHOD__, __LINE__);
                $pdf_info = $pdf_info->get();

                // no break
            default:
                $this->logInfo('In default mode.', __METHOD__, __LINE__);
                // OLIVER DEBUG
                $this->info('PDF INFO QUERY 2');
                $this->info($pdf_info->toSql());
                $this->info('Bindings:');
                $this->info(print_r($pdf_info->getBindings(), true));
                $pdf_info = $pdf_info->first();
        }

        // store db Query for reporting
        $this->dbQuery = DB::getQueryLog();

        switch ($mode) {
            case 'get':
                if (
                    isset($pdf_info)
                    && count($pdf_info) > 0
                ) {
                    return $pdf_info;
                }
                break;

            case 'first':
                if (
                    isset($pdf_info)
                ) {
                    return $pdf_info;
                }
                break;
        }

        return null;
    }

    private function processProduct($product, $user_id, $email_address, $phone_number, $dob, $invoice_delivery)
    {
        $this->logInfo('Processing product with ' . print_r($product, true), __METHOD__, __LINE__);
        $this->info('In processProduct()');

        //preprocess data
        if ($this->option('debug')) {
            $this->info('processProduct with ' . print_r($product, true));
        }

        foreach ($product as $key => $prod) {
            if ($this->option('debug')) {
                $this->info('processing single product ' . print_r($prod, true));
            }
            $productData['address'] = $prod['service_address1'] . ' ' . $prod['service_address2'];
            $productData['address_service'] = $prod['service_address1'] . ' ' . $prod['service_address2'];
            if (isset($prod['billing_address1'])) {
                $productData['address_billing'] = $prod['billing_address1'] . ' ' . $prod['billing_address2'];
            } else {
                $productData['address_billing'] = $productData['address_service'];
            }
            $productData['service_address_same_as_billing_address'] = 'Off';
            if ($productData['address_service'] === $productData['address_billing']) {
                $productData['service_address_same_as_billing_address'] = 'Yes';
            }
            $productData['address_electric'] = '';
            $productData['address_gas'] = '';
            $agent_id_query = BrandUser::select('tsr_id')
                ->where('id', $user_id)
                ->first();
            if ($agent_id_query) {
                $productData['agent_id'] = $agent_id_query->tsr_id;
            } else {
                $productData['agent_id'] = '';
            }

            $agent_info = BrandUser::select(
                'users.first_name',
                'users.last_name',
                'brand_users.license_number'
            )->leftJoin(
                'users',
                'brand_users.user_id',
                'users.id'
            )->where('brand_users.id', $productData['agent_id'])
                ->first();

            if ($agent_info) {
                $productData['agent_fullname'] = $agent_info->first_name . ' ' . $agent_info->last_name;
            } else {
                $productData['agent_fullname'] = null;
            }

            if ($agent_info) {
                $productData['agent_license'] = $agent_info->license_number;
            } else {
                $productData['agent_license'] = null;
            }

            $productData['ah_date_of_birth'] = (isset($dob)) ? $dob : null;

            $relationships = BrandAuthRelationship::select(
                'brand_auth_relationships.auth_relationship_id',
                'auth_relationships.relationship'
            )->leftJoin(
                'auth_relationships',
                'brand_auth_relationships.auth_relationship_id',
                'auth_relationships.id'
            )->where(
                'brand_auth_relationships.brand_id',
                session('works_for_id')
            )->where(
                'brand_auth_relationships.state_id',
                $prod['service_state']
            )->get();
            foreach ($relationships as $relationship) {
                $relationship->relationship = str_replace(' ', '_', strtolower($relationship->relationship));
                $productData['auth_relationship_' . $relationship->relationship] = 'Off';
                if ($prod['auth_relationship'] === $relationship->auth_relationship_id) {
                    $productData['auth_relationship_' . $relationship->relationship] = 'Yes';
                } else {
                    $productData['auth_relationship_' . $relationship->relationship] = 'Off';
                }
            }
            if (1 != $prod['auth_relationship']) {
                $productData['auth_relationship_authorized_agent'] = 'Yes';
            } else {
                $productData['auth_relationship_authorized_agent'] = 'Off';
            }

            $productData['city'] = $prod['service_city'];
            $productData['city_service'] = $prod['service_city'];
            if (isset($prod['billing_city'])) {
                $productData['city_billing'] = $prod['billing_city'];
            } else {
                $productData['city_billing'] = $productData['city_service'];
            }

            $utility = UtilitySupportedFuel::select(
                'utilities.name'
            )->leftJoin(
                'utilities',
                'utility_supported_fuels.utility_id',
                'utilities.id'
            )
                ->where('utility_supported_fuels.id', $prod['utility_id'])
                ->first();

            $productData['company_name'] = $utility->name;
            $productData['date'] = date('m-d-Y');
            $productData['email_address'] = $email_address;
            $productData['event_type_electric'] = 'Off';
            $productData['event_type_gas'] = 'Off';
            $productData['event_type_electric_and_gas'] = 'Off';
            // $fullname = $data['bill_first_name'] . ', ' . $data['bill_last_name'];
            $productData['bill_fullname'] = $prod['bill_last_name'] . ', ' . $prod['bill_first_name'] . ' ' . $prod['bill_middle_name'];
            $productData['auth_fullname'] = $prod['auth_last_name'] . ', ' . $prod['auth_first_name'] . ' ' . $prod['auth_middle_name'];
            $productData['bill_fullname_same_as_auth_fullname'] = 'Off';
            if ($productData['bill_fullname'] === $productData['auth_fullname']) {
                $productData['bill_fullname_same_as_auth_fullname'] = 'Yes';
            }
            $productData['initials'] = strtoupper(substr($prod['bill_first_name'], 0, 1)) . strtoupper(substr($prod['bill_middle_name'], 0, 1)) . strtoupper(substr($prod['bill_last_name'], 0, 1));
            $productData['phone_number'] = $phone_number;
            $rate_info = Rate::select('products.term', 'rates.rate_amount', 'rates.cancellation_fee')
                ->join('products', 'rates.product_id', 'products.id')
                ->where('rates.id', $prod['rate_id'])
                ->first();
            $productData['rate_info_term'] = $rate_info->term;
            $productData['rate_info_rate_amount'] = $rate_info->rate_amount;
            if (isset($rate_info->cancellation_fee)) {
                $productData['rate_info_cancellation_fee'] = 'Yes';
            } else {
                $productData['rate_info_cancellation_fee'] = 'No';
            }
            // $productData['rate_info_electric'] = '';
            // $productData['rate_info_gas'] = '';
            // $productData['rate_info_gas_term'] = '';
            // $productData['rate_info_gas_rate_amount'] = '';
            // $productData['rate_info_electric_rate_amount'] = '';
            $productData['company_name_gas'] = '';
            $productData['state'] = $prod['service_state'];
            $productData['state_service'] = $productData['state'];
            if (isset($prod['billing_state'])) {
                $productData['state_billing'] = $prod['billing_state'];
            } else {
                $productData['state_billing'] = $productData['state_service'];
            }
            $productData['zip'] = $prod['service_zip'];
            $productData['zip_service'] = $prod['service_zip'];
            if (isset($prod['billing_zip'])) {
                $productData['zip_billing'] = $prod['billing_zip'];
            } else {
                $productData['zip_billing'] = $productData['zip_service'];
            }
            $productData['full_address'] = $productData['address'] . ', ' . $productData['city'] . ', ' . $productData['state'] . ' ' . $productData['zip'];
            $productData['full_address_service'] = $productData['address_service'] . ', ' . $productData['city_service'] . ', ' . $productData['state_service'] . ' ' . $productData['zip_service'];
            $productData['full_address_billing'] = $productData['address_billing'] . ', ' . $productData['city_billing'] . ', ' . $productData['state_billing'] . ' ' . $productData['zip_billing'];
            $productData['green_product'] = 'Off';
            $productData['green_percentage'] = 0;
            $productData['start_date'] = $productData['date'];
            // $productData['start_date_electric'] = '';
            // $productData['start_date_gas'] = '';

            $green = Product::select('green_percentage')
                ->leftJoin('rates', 'products.id', 'rates.product_id')
                ->where('rates.id', $prod['rate_id'])
                ->first();
            if (isset($green->green_percentage) && 0 != $green->green_percentage) {
                $productData['green_product'] = 'Yes';
                $productData['green_percentage'] = $green->green_percentage;
            }

            $productData['billing_invoice_paper'] = 'Off';
            $productData['billing_invoice_email'] = 'Off';
            $productData['service_invoice_paper'] = 'Off';
            $productData['service_invoice_email'] = 'Off';

            if (isset($invoice_delivery)) {
                switch ($invoice_delivery) {
                    case 'paper invoices':
                        $productData['billing_invoice_paper'] = 'On';
                        $productData['service_invoice_paper'] = 'On';
                        break;

                    case 'emailed invoices':
                        $productData['billing_invoice_email'] = 'On';
                        $productData['service_invoice_email'] = 'On';
                        break;
                }
            }

            // $productData['electric_fixed_rate_checkbox'] = 'Off';
            // $productData['electric_variable_rate_checkbox'] = 'Off';
            // $productData['gas_fixed_rate_checkbox'] = 'Off';
            // $productData['gas_variable_rate_checkbox'] = 'Off';

            if (1 == $prod['event_type_id']) {
                $this->info('In processProduct -- Electric');

                $productData['account_number_electric'] = $prod['account_number'];
                if (isset($productData['utility_account_number_all'])) {
                    $productData['utility_account_number_all'] .= '\r' . $productData['account_number_electric'];
                } else {
                    $productData['utility_account_number_all'] = $productData['account_number_electric'];
                }

                $productData['address_electric'] = $prod['service_address1'] . ' ' . $prod['service_address2'];
                $productData['city_electric'] = $prod['service_city'];
                $productData['electric_utility_account_type_account_number'] = 'Off';
                $productData['electric_utility_account_type_customer_number'] = 'Off';
                $productData['electric_utility_account_type_supplier_agreement_id'] = 'Off';
                $productData['gas_utility_account_type_account_number'] = 'Off';
                $productData['gas_utility_account_type_customer_number'] = 'Off';
                $productData['event_type_electric'] = 'Yes';
                $utility_electric = UtilitySupportedFuel::select(
                    'utilities.name',
                    'utility_account_types.account_type'
                )->leftJoin(
                    'utilities',
                    'utility_supported_fuels.utility_id',
                    'utilities.id'
                )->leftJoin(
                    'utility_account_identifiers',
                    'utilities.id',
                    'utility_account_identifiers.utility_id'
                )->leftJoin(
                    'utility_account_types',
                    'utility_account_identifiers.utility_account_type_id',
                    'utility_account_types.id'
                )->where(
                    'utility_supported_fuels.id',
                    $prod['utility_id']
                )->first();
                if (isset($utility_electric)) {
                    if (isset($productData['utility_name_all'])) {
                        $productData['utility_name_all'] .= ', ' . $utility_electric->name;
                    } else {
                        $productData['utility_name_all'] = $utility_electric->name;
                    }
                }
                $productData['company_name_electric'] = $utility_electric->name;
                $productData['utility_electric_primary_identifier'] = $utility_electric->account_type;
                switch ($utility_electric->account_type) {
                    case 'Account Number':
                        $productData['electric_utility_account_type_account_number'] = 'Yes';
                        break;

                    case 'Customer Number':
                        $productData['electric_utility_account_type_customer_number'] = 'Yes';
                        break;

                    case 'Supplier Agreement ID':
                        $productData['electric_utility_account_type_supplier_agreement_id'] = 'Yes';
                        break;
                }
                $productData['fullname_electric'] = $prod['bill_last_name']
                    . ', ' . $prod['bill_first_name'] . ' ' . $prod['bill_middle_name'];
                $rate_info_electric = Rate::select(
                    'products.id AS product_id',
                    'products.rate_type_id',
                    'products.term',
                    'rates.rate_amount',
                    'rates.intro_rate_amount',
                    'rates.program_code'
                )
                    ->leftJoin('products', 'rates.product_id', 'products.id')
                    ->where('rates.id', $prod['rate_id'])
                    ->withTrashed()
                    ->first();

                if ($this->option('debug')) {
                    $this->info('rate_info_electric assigned ' . print_r($rate_info_electric, true));
                }

                switch ($rate_info_electric->rate_type_id) {
                    case 1:
                        $productData['rate_info_electric_rate_amount']
                            = round($rate_info_electric->rate_amount, 2);
                        $productData['electric_fixed_rate_checkbox'] = 'Yes';
                        break;

                    case 2:
                    case 3:
                        $productData['rate_info_electric_rate_amount']
                            = round($rate_info_electric->intro_rate_amount, 2);
                        $productData['electric_variable_rate_checkbox'] = 'Yes';
                        break;
                }
                $productData['rate_info_electric_program_code'] = $rate_info_electric->program_code;
                $productData['rate_info_electric_term'] = $rate_info_electric->term;
                $productData['start_date_electric'] = $productData['date'];
                $productData['state_electric'] = $prod['service_state'];
                $productData['zip_electric'] = $prod['service_zip'];
            }

            if (2 == $prod['event_type_id']) {
                $this->info('In processProduct -- Gas');

                $productData['account_number_gas'] = $prod['account_number'];
                if (isset($productData['utility_account_number_all'])) {
                    $productData['utility_account_number_all']
                        .= '\r' . $productData['account_number_gas'];
                } else {
                    $productData['utility_account_number_all']
                        = $productData['account_number_gas'];
                }

                $productData['address_gas'] = $prod['service_address1']
                    . ' ' . $prod['service_address2'];
                $productData['city_gas'] = $prod['service_city'];
                $productData['event_type_gas'] = 'Yes';
                $utility_gas = Utility::select(
                    'utilities.name',
                    'utility_account_types.account_type'
                )->leftJoin(
                    'utility_supported_fuels',
                    'utility_supported_fuels.utility_id',
                    'utilities.id'
                )->leftJoin(
                    'utility_account_identifiers',
                    'utility_account_identifiers.utility_id',
                    'utility_supported_fuels.id'
                )->leftJoin(
                    'utility_account_types',
                    'utility_account_identifiers.utility_account_type_id',
                    'utility_account_types.id'
                )->where(
                    'utility_supported_fuels.id',
                    $prod['utility_id']
                )->where(
                    'utility_account_types.utility_account_number_type_id', // only match primary uan types
                    1
                )->first();
                if (isset($utility_gas)) {
                    if (isset($productData['utility_name_all'])) {
                        $productData['utility_name_all'] .= ', ' . $utility_gas->name;
                    } else {
                        $productData['utility_name_all'] = $utility_gas->name;
                    }
                }
                $productData['company_name_gas'] = $utility_gas->name;

                $productData['utility_gas_primary_identifier'] = $utility_gas->account_type;

                switch ($utility_gas->account_type) {
                    case 'Account Number':
                        $productData['gas_utility_account_type_account_number'] = 'Yes';
                        break;

                    case 'Customer Number':
                        $productData['gas_utility_account_type_customer_number'] = 'Yes';
                        break;
                }

                $productData['fullname_gas'] = $prod['bill_last_name']
                    . ', ' . $prod['bill_first_name'] . ' ' . $prod['bill_middle_name'];
                $rate_info_gas = Rate::select(
                    'products.id AS product_id',
                    'products.rate_type_id',
                    'products.term',
                    'rates.rate_amount',
                    'rates.intro_rate_amount',
                    'rates.program_code'
                )->leftJoin(
                    'products',
                    'rates.product_id',
                    'products.id'
                )->where(
                    'rates.id',
                    $prod['rate_id']
                )->withTrashed()->first();

                if ($this->option('debug')) {
                    $this->info('rate_info_gas assigned ' . print_r($rate_info_gas, true));
                }

                switch ($rate_info_gas->rate_type_id) {
                    case '1':
                    case 1:
                        $productData['rate_info_gas_rate_amount']
                            = round($rate_info_gas->rate_amount, 2);
                        $productData['gas_fixed_rate_checkbox'] = 'Yes';
                        break;

                    case '2':
                    case '3':
                    case 2:
                    case 3:
                        $productData['rate_info_gas_rate_amount']
                            = round($rate_info_gas->intro_rate_amount, 2);
                        $productData['gas_variable_rate_checkbox'] = 'Yes';
                        break;
                }
                $productData['rate_info_gas_program_code'] = $rate_info_gas->program_code;
                $productData['rate_info_gas_term'] = $rate_info_gas->term;
                $productData['state_gas'] = $prod['service_state'];
                $productData['zip_gas'] = $prod['service_zip'];
                $productData['start_date_gas'] = $productData['date'];
            }
        }

        if (
            'Yes' == $productData['event_type_electric']
            && 'Yes' == $productData['event_type_gas']
        ) {
            $productData['event_type_electric_and_gas'] = 'Yes';
            $productData['event_type_electric'] = 'Off';
            $productData['event_type_gas'] = 'Off';
        }

        if (
            @$productData['company_name_gas'] === @$productData['company_name_electric']
            && @$productData['fullname_gas'] === @$productData['fullname_electric']
        ) {
            $productData['gas_info_same_as_electric'] = 'Yes';
        } else {
            $productData['gas_info_same_as_electric'] = 'Off';
        }

        return $productData;
    }

    private function generateDocument(
        $eztpv,
        $vendor_id,
        $productData,
        $pdf_info,
        $filenames = null
    ) {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        extract($productData);
        $signature_date = (isset($eztpv->signature_customer->updated_at)) ? $eztpv->signature_customer->updated_at : null;

        $vars = [
            'account_number_electric',
            'account_number_gas',
            'address',
            'address_service',
            'address_billing',
            'address_electric',
            'address_gas',
            'agent_fullname',
            'agent_id',
            'agent_license',
            'ah_date_of_birth',
            'auth_fullname',
            'auth_relationship_self',
            'auth_relationship_spouse',
            'bill_fullname',
            'bill_fullname_same_as_auth_fullname',
            'billing_invoice_email',
            'billing_invoice_paper',
            'city',
            'city_service',
            'city_billing',
            'city_electric',
            'city_gas',
            'company_name',
            'company_name_electric',
            'company_name_gas',
            'confirmation_code',
            'date',
            'electric_fixed_rate_checkbox',
            'utility_electric_primary_identifier',
            'electric_utility_account_type_account_number',
            'electric_utility_account_type_customer_number',
            'electric_utility_account_type_supplier_agreement_id',
            'electric_variable_rate_checkbox',
            'email_address',
            'event_type_electric',
            'event_type_electric_and_gas',
            'event_type_gas',
            'full_address',
            'full_address_service',
            'full_address_billing',
            'fullname_electric',
            'fullname_gas',
            'gas_fixed_rate_checkbox',
            'gas_info_same_as_electric',
            'utility_gas_primary_identifier',
            'gas_utility_account_type_account_number',
            'gas_utility_account_type_customer_number',
            'gas_variable_rate_checkbox',
            'green_product',
            'green_percentage',
            'initials',
            'pages_current',
            'pages_total',
            'phone_number',
            'rate_info_cancellation_fee',
            'rate_info_electric_program_code',
            'rate_info_electric_rate_amount',
            'rate_info_electric_term',
            'rate_info_gas_program_code',
            'rate_info_gas_rate_amount',
            'rate_info_gas_term',
            'rate_info_rate_amount',
            'rate_info_term',
            'service_address_same_as_billing_address',
            'service_invoice_email',
            'service_invoice_paper',
            'signature_date',
            'site_1_billing_address_full',
            'site_1_event_type_electric',
            'site_1_event_type_gas',
            'site_1_service_address_full',
            'site_1_site_id',
            'site_2_billing_address_full',
            'site_2_event_type_electric',
            'site_2_event_type_gas',
            'site_2_service_address_full',
            'site_2_site_id',
            'site_3_billing_address_full',
            'site_3_event_type_electric',
            'site_3_event_type_gas',
            'site_3_service_address_full',
            'site_3_site_id',
            'site_4_billing_address_full',
            'site_4_event_type_electric',
            'site_4_event_type_gas',
            'site_4_service_address_full',
            'site_4_site_id',
            'start_date',
            'start_date_electric',
            'start_date_gas',
            'state',
            'state_service',
            'state_billing',
            'state_electric',
            'state_gas',
            'utility_name_all',
            'utility_account_number_all',
            'zip',
            'zip_service',
            'zip_billing',
            'zip_electric',
            'zip_gas',
        ];
        $fdf_data = $pdf_info->contract_fdf;
        foreach ($vars as $var) {
            if ($this->option('debug')) {
                $this->info($var . ': ' . @$$var . PHP_EOL);
            }

            $search = $var;
            $fdf_data = str_replace('[' . $search . ']', @$$var, $fdf_data);
        }

        // embed pdfdata
        $pdfdata = '%FDF-1.2
            %âãÏÓ
            1 0 obj
            <<
            /FDF
            <<
            /Fields [';
        $pdfdata .= $fdf_data;
        $pdfdata .= ']
            >>
            >>
            endobj
            trailer

            <<
            /Root 1 0 R
            >>
            %%EOF';

        //set filename for new files
        $filepath = public_path('uploads/eztpv/documents/');
        $filename = $filepath . 'eztpv' . '_' . $eztpv->id . '_' . date('d.m.Y.H.i.s') . '_' . mt_rand(1, 99999);
        //append fdf extension to filename for file ops
        $extension = '.fdf';
        $datafile = $filename . $extension;

        //open (create) fdf file
        $file = fopen($datafile, 'w');
        //write data to fdf file
        fwrite($file, $pdfdata);
        //close fdf file
        fclose($file);
        //change permissions on fdf file
        chmod($datafile, 0744);

        if (!file_exists($datafile)) {
            $this->logInfo('Link: no fdf file generated', __METHOD__, __LINE__);
            $this->info('Link: no fdf file generated' . PHP_EOL);

            return false;
        }
        // set exec string to create pdf file from fdf file
        $pdftk = (config('app.pdftk_path')) ? config('app.pdftk_path') : 'pdftk';
        $exec_string = $pdftk . ' '
            . resource_path('assets/documents/' . $pdf_info->contract_pdf)
            . ' fill_form '
            . $datafile
            . ' output '
            . $filename . '.pdf'
            . ' flatten'; //2>&1
        $this->logDebug('EXEC_STRING IS ' . $exec_string, __METHOD__, __LINE__);

        //execute exec string
        passthru($exec_string, $output);
        if ($output != 0) {
            return [
                'error',
                'PDF Generation Error (2), pdf failed to generate. Code: ' . $output,
            ];
        }
        $this->logDebug('EXEC RETURNED: ' . $output, __METHOD__, __LINE__);

        $dir = 'uploads/pdfs/' . $eztpv->brand_id . '/' . $vendor_id . '/' . date('Y-m-d') . '/tmp';
        $s3filename = md5($filename) . '.pdf';
        $keyname = $dir . '/' . $s3filename;
        try {
            $this->logInfo('Generate PDF: Upload of ' . $keyname . ' (temporary filled pdf) attempted.', __METHOD__, __LINE__);
            $s3 = Storage::disk('s3')->put(
                $keyname,
                file_get_contents($filename . '.pdf'),
                'public'
            );

            if ($s3) {
                $this->logInfo('Generate PDF: Upload of ' . $keyname . ' (temporary filled pdf) succeeded.', __METHOD__, __LINE__);
            }

            unlink($datafile);
            unlink($filename . '.pdf');

            if (Storage::disk('s3')->exists($keyname)) {
                $filenames[] = [
                    'file' => $keyname,
                    'pdf_info_id' => $pdf_info->id,
                ];
            } else {
                $this->logInfo('Generate PDF: ' . $keyname . ' does not exist.  Returning false.', __METHOD__, __LINE__);

                return false;
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            $this->logError('Exception: ' . $e . ' returned from file upload (Generate PDF)', __METHOD__, __LINE__);

            return false;
        }

        return $filenames;
    }

    private function processSiteScheduleProduct(
        $baseProduct,
        $products,
        $user_id,
        $confirmation_code,
        $currentPage,
        $totalPages
    ) {
        //preprocess data
        $this->logDebug('processSiteScheduleProduct with ' 
            . json_encode($products). ' and ' . json_encode($user_id), __METHOD__, __LINE__);

        $productData['auth_fullname'] = $baseProduct['auth_last_name']
            . ', ' . $baseProduct['auth_first_name'] . ' ' . $baseProduct['auth_middle_name'];
        $productData['initials'] = strtoupper(
            substr(
                $baseProduct['bill_first_name'],
                0,
                1
            )
        ) . strtoupper(
            substr(
                $baseProduct['bill_middle_name'],
                0,
                1
            )
        ) . strtoupper(substr($baseProduct['bill_last_name'], 0, 1));

        $productData['confirmation_code'] = $confirmation_code;
        $productData['pages_current'] = $currentPage;
        $productData['pages_total'] = $totalPages;

        $site = 1;
        foreach ($products as $prods) {
            $product = $prods[0];
            $productData['address_service'] = $product['service_address1']
                . ' ' . $product['service_address2'];
            if (isset($product['billing_address'])) {
                $productData['address_billing'] = $product['billing_address']
                    . ' ' . $product['billing_address2'];
            } else {
                $productData['address_billing'] = $productData['address_service'];
            }
            $productData['city_service'] = $product['service_city'];
            if (isset($product['billing_city'])) {
                $productData['city_billing'] = $product['billing_city'];
            } else {
                $productData['city_billing'] = $productData['city_service'];
            }
            $productData['state_service'] = $product['service_state'];
            if (isset($product['billing_state'])) {
                $productData['state_billing'] = $product['billing_state'];
            } else {
                $productData['state_billing'] = $productData['state_service'];
            }
            $productData['zip_service'] = $product['service_zip'];
            if (isset($product['billing_zip'])) {
                $productData['zip_billing'] = $product['billing_zip'];
            } else {
                $productData['zip_billing'] = $productData['zip_service'];
            }

            $productData['site_' . $site . '_service_address_full']
                = $productData['address_service'] . ', '
                . $productData['city_service'] . ', '
                . $productData['state_service'] . ' ' . $productData['zip_service'];
            $productData['site_' . $site . '_billing_address_full']
                = $productData['address_billing'] . ', '
                . $productData['city_billing'] . ', '
                . $productData['state_billing'] . ' ' . $productData['zip_billing'];

            $productData['site_' . $site . '_site_id'] = $product['account_number'];

            $productData['site_' . $site . '_event_type_electric'] = 'Off';
            $productData['site_' . $site . '_event_type_gas'] = 'Off';
            if (1 == $product['event_type_id']) {
                $productData['site_' . $site . '_event_type_electric'] = 'Yes';
                $productData['site_' . $site . '_event_type_gas'] = 'Off';
            }
            if (2 == $product['event_type_id']) {
                $productData['site_' . $site . '_event_type_electric'] = 'Off';
                $productData['site_' . $site . '_event_type_gas'] = 'Yes';
            }
            ++$site;
        }

        return $productData;
    }

    private function sendCustomerEmail($event_id, $data, $eztpv)
    {
        if (
            !$this->option('noDelivery')
            || !$this->option('preview')
        ) {
            if ($this->deliver_contracts) {
                info("##### DATA:", [$data]);

                $this->info('Sending Customer Email');
                // email customer with link to document
                $event = Event::find($event_id);
                $language = ($event && isset($event->language_id) && $event->language_id == 2)
                    ? 'spanish' : 'english';
                $customer_email = $this->getCustomerEmail($event_id, $data['email_address']);
                $service_addr_state = (
                    isset($data['finalized_products']) 
                    && isset($data['finalized_products'][0]) 
                    && isset($data['finalized_products'][0]->service_state)
                    ? $data['finalized_products'][0]->service_state
                    : ''
                );
                $brand_name = Brand::where('id', $this->eztpv->brand_id)->first();
                if (!$brand_name) {
                    $this->info('Invalid brand id ' . $this->eztpv->brand_id);
                    return;
                }

                $logo_info = Upload::where('id', $brand_name->logo_path)->first();
                $logo_path = (isset($logo_info)) ? $logo_info->filename : null;
                if (!isset($this->eztpv->contract_type)) {
                    $this->eztpv->contract_type = 2;
                }

                switch ($this->eztpv->contract_type) {
                    case 0:
                        if (isset($data['has_digital']) && $data['has_digital']) {
                            if ('spanish' === $language) {
                                $subject = 'Documentos de inscripción importantes de ' . $brand_name->name . ' de TPV.com';
                            } else {
                                $subject = 'Important ' . $brand_name->name . ' Enrollment Documents from TPV.com';
                            }

                            $customer_name = "";

                            $email_file = 'emails.eztpvSendContractSummaryToCustomer';

                            if ($brand_name->name === 'Greenlight Energy'){
                                $subject = 'Your Greenlight Energy Contract Terms and Agreements';
                                $customer_name = $data['finalized_products'][0][0]["auth_first_name"]." ". $data['finalized_products'][0][0]["auth_last_name"];
                                $email_file = 'emails.eztpvSendContractSummaryToCustomerGreenlight';
                            }

                            $email_data = [
                                'company' => $brand_name->name,
                                'service_state' => $service_addr_state,
                                'url' => config('app.urls.clients') . '/d/' . $eztpv->id,
                                'language' => $language,
                                'customer_name' => $customer_name
                            ];

                        } else {
                            return;
                        }

                        break;

                    case 1:
                        // summary contract
                        if ('spanish' === $language) {
                            $subject = 'Documentos de inscripción importantes de ' . $brand_name->name . ' de TPV.com';
                        } else {
                            $subject = 'Important ' . $brand_name->name . ' Enrollment Documents from TPV.com';
                        }

                        $customer_name = "";
                        $email_file = 'emails.eztpvSendContractSummaryToCustomer';

                        if ($brand_name->name === 'Greenlight Energy'){
                            $subject = 'Your Greenlight Energy Contract Terms and Agreements';
                            $customer_name = $data['finalized_products'][0][0]["auth_first_name"]." ". $data['finalized_products'][0][0]["auth_last_name"];
                            $email_file = 'emails.eztpvSendContractSummaryToCustomerGreenlight';
                        }

                        $email_data = [
                            'company' => $brand_name->name,
                            'service_state' => $service_addr_state,
                            'url' => config('app.urls.clients') . '/d/' . $eztpv->id,
                            'language' => $language,
                            'customer_name' => $customer_name
                        ];

                        break;

                    case 2:
                    case 3:
                    case 4:
                        // custom contract
                        $verbiage_query = BrandEztpvContract::select('email_verbiage_info')
                            // ->where('brand_id', $this->eztpv->brand_id)
                            // ->where('state_id', $this->eztpv->event->products[0]->serviceAddress->address->state->id)
                            ->where('id', $data['contracts'][0]['pdf_info_id'])
                            ->first();

                        if (isset($verbiage_query->email_verbiage_info)) {
                            $customer_name = "";
                            $message_body = "";

                            if ($brand_name->name === 'Greenlight Energy'){
                                $subject = 'Your Greenlight Energy Contract Terms and Agreements';
                                $customer_name = $data['finalized_products'][0][0]["auth_first_name"]." ". $data['finalized_products'][0][0]["auth_last_name"];
                                $email_file = 'emails.eztpvSendCustomContractToCustomerGreenlight';

                            }else {
                                $verbiage = json_decode($verbiage_query->email_verbiage_info, true);
                                $subject = $verbiage['subject'];
                                $email_file = 'emails.eztpvSendCustomContractToCustomer';
                                $message_body = $verbiage['message_body'];
                            }

                            $email_data = [
                                'company' => $brand_name->name,
                                'service_state' => $service_addr_state,
                                'message_body' => $message_body,
                                'customer_name' => $customer_name,
                                'url' => config('app.urls.clients') . '/d/' . $eztpv->id,
                                'language' => $language,
                                'logo' => $logo_path,
                            ];


                        } else {
                            if ('spanish' === $language) {
                                $subject = 'Documentos de inscripción importantes de ' . $brand_name->name . ' de TPV.com';
                            } else {
                                $subject = 'Important ' . $brand_name->name . ' Enrollment Documents from TPV.com';
                            }
                            $customer_name = "";
                            $email_file = 'emails.eztpvSendContractToCustomer';

                            if ($brand_name->name === 'Greenlight Energy'){
                                $subject = 'Your Greenlight Energy Contract Terms and Agreements';
                                $customer_name = $data['finalized_products'][0][0]["auth_first_name"]." ". $data['finalized_products'][0][0]["auth_last_name"];
                                $email_file = 'emails.eztpvSendContractToCustomerGreenlight';
                            }

                            $email_data = [
                                'company' => $brand_name->name,
                                'service_state' => $service_addr_state,
                                'url' => config('app.urls.clients') . '/d/' . $eztpv->id,
                                'language' => $language,
                                'customer_name' => $customer_name

                            ];

                        }

                        break;
                }

                if (
                    isset($customer_email)
                    && strlen(trim($customer_email)) > 0
                    && filter_var(trim($customer_email), FILTER_VALIDATE_EMAIL)
                    && !$this->option('noEmail')
                ) {
                    try {
                        $emailFromAddr = $this->getEmail($brand_name->name);

                        Mail::send(
                            $email_file,
                            $email_data,
                            function ($message) use ($subject, $customer_email, $emailFromAddr) {
                                $message->subject($subject);
                                $message->from($emailFromAddr);
                                $message->to(trim($customer_email));
                            }
                        );
                    } catch (\Exception $e) {
                        unset($contactError);
                        $this->logError(
                            'Could not send email notification.' .
                                ' error: ' . $e, 
                            __METHOD__, __LINE__
                        );

                        if (isset($customer_email)) {
                            $contactError = EztpvContactError::updateOrCreate(
                                [
                                    'eztpv_id' => $eztpv->id,
                                    'vector' => 'email',
                                    'destination' => $customer_email,
                                ],
                                ['error_info' => $e]
                            );
                            $contactError->occurrences = $contactError->occurrences + 1;
                            $contactError->save();
                        }

                        return ['error_contacting_customer', $e];
                    }
                } else {
                    unset($contactError);
                    $this->logError(
                        'Could not send email notification.' .
                            ' error: customer email is null', 
                        __METHOD__, __LINE__
                    );

                    if (isset($customer_email)) {
                        $contactError = EztpvContactError::updateOrCreate(
                            [
                                'eztpv_id' => $eztpv->id,
                                'vector' => 'email',
                                'destination' => $customer_email,
                            ],
                            ['error_info' => 'customer email is null']
                        );
                        $contactError->occurrences = $contactError->occurrences + 1;
                        $contactError->save();
                    }

                    return ['error_contacting_customer', 'customer email is null'];
                }
            }
        } else {
            $this->logInfo('Skipped delivery due to --noDelivery flag.', __METHOD__, __LINE__);
        }

        return;
    }

    private function sendCustomerText($data, $eztpv)
    {
        if (
            !$this->option('noDelivery')
            || !$this->option('preview')
        ) {
            $client = new TwilioClient(
                config('services.twilio.account'),
                config('services.twilio.auth_token')
            );

            if ($this->deliver_contracts) {
                if (
                    isset($data['phone_number'])
                    && strlen(trim($data['phone_number'])) > 0
                ) {
                    $this->info('Sending Customer Text');
                    $to = (0 !== strpos($data['phone_number'], '+1'))
                        ? '+1' . preg_replace('/\D/', '', $data['phone_number'])
                        : $data['phone_number'];

                    // twilio lookup to validate phone numbers before attempting to text them
                    // when we start doing more international business, this will need to check country
                    // currently works only for US and Canada
                    try {
                        $lookup = $client->lookups->v1->phoneNumbers($to)->fetch(
                            ['countryCode' => 'US']
                        );
                    } catch (RestException $e) {
                        $this->logDebug(
                            'Twilio hit a RestException ('
                                . $e . ') Attempting to skip it and send anyway.', 
                                __METHOD__, __LINE__
                        );

                        unset($e);
                    } catch (TwilioException $e) {
                        unset($contactError);

                        $this->logError(
                            'Could not send SMS notification.' .
                                ' error: ' . $e,
                            __METHOD__, __LINE__
                        );

                        $contactError = EztpvContactError::updateOrCreate(
                            [
                                'eztpv_id' => $eztpv->id,
                                'vector' => 'text',
                                'destination' => $to,
                            ],
                            [
                                'error_info' => $e,
                            ]
                        );

                        $contactError->occurrences = $contactError->occurrences + 1;
                        $contactError->save();

                        return ['error_contacting_customer', $e];
                    }

                    if (!isset($e)) {
                        $brand_name = Brand::where('id', $this->eztpv->brand_id)->first();
                        if (!$brand_name) {
                            return ['error_looking_up_brand_name', $this->eztpv->brand_id];
                        }

                        switch ($this->eztpv->contract_type) {
                            case 0:
                                if (isset($data['has_digital']) && $data['has_digital']) {
                                    $url = CreateShortURI(config('app.urls.clients') . '/d/' . $eztpv->id, 3);
                                }
                                break;

                            case 1:
                                // summary contract
                            case 2:
                            case 3:
                            case 4:
                                // custom contract
                                $url = CreateShortURI(config('app.urls.clients') . '/d/' . $eztpv->id, 3);
                                break;
                        }

                        if (12 == strlen(trim($to)) && strlen(trim($url)) > 0) {
                            $message = $url . ' Click the link above to download your attachments from '
                                . $brand_name->name . ' and TPV.com. Reply STOP to unsubscribe.';
                            try {
                                $ret = SendSMS($to, config('services.twilio.default_number'), $message, null, $this->eztpv->brand_id, 5);
                                if (strstr($ret, 'ERROR') !== false) {
                                    $this->logError('Could not send SMS notification. ' . $ret, __METHOD__, __LINE__);
                                }
                                /*$client->messages->create(
                                    $to,
                                    [
                                        'body' => $message,
                                        'from' => config('services.twilio.default_number'),
                                    ]
                                );*/
                            } catch (TwilioException $e2) {
                                unset($contactError);

                                $this->logError(
                                    'Could not send SMS notification.' .
                                        ' error: ' . $e2,
                                    __METHOD__, __LINE__
                                );

                                $contactError = EztpvContactError::updateOrCreate(
                                    [
                                        'eztpv_id' => $eztpv->id,
                                        'vector' => 'text',
                                        'destination' => $to,
                                    ],
                                    [
                                        'error_info' => $e2,
                                    ]
                                );

                                $contactError->occurrences
                                    = $contactError->occurrences + 1;
                                $contactError->save();

                                return ['error_contacting_customer', $e2];
                            }
                        }
                    }
                }
            }
        } else {
            $this->logInfo('Skipped delivery due to --noDelivery flag.', __METHOD__, __LINE__);
        }
    }

    private function getCustomerEmail($event_id, $email_address)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        $eal = EmailAddress::select(
            'email_addresses.email_address'
        )->leftJoin(
            'email_address_lookup',
            'email_addresses.id',
            'email_address_lookup.email_address_id'
        )->where(
            'type_id',
            $event_id
        )->where(
            'email_address_type_id',
            3
        )->whereNull(
            'email_addresses.deleted_at'
        )->whereNull(
            'email_address_lookup.deleted_at'
        )->first();

        $customer_email = ($eal)
            ? $eal->email_address
            : $email_address;

        return $customer_email;
    }

    private function sendErrorEmail($step = null, $eztpv_id, $confirmation_code, $data, $additional = null)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        $brand = Brand::select(
            'name'
        )->where(
            'id',
            $this->eztpv->brand_id
        )->first();

        $products = Product::select(
            'products.id',
            'products.name',
            'products.rate_type_id'
        )->join(
            'rates',
            'rates.product_id',
            'products.id'
        )->join(
            'event_product',
            'event_product.rate_id',
            'rates.id'
        )->join(
            'events',
            'event_product.event_id',
            'events.id'
        )->where(
            'events.eztpv_id',
            $eztpv_id
        )->get();

        $state = State::find($this->eztpv->event->products[0]->serviceAddress->address->state->id);

        $channel = Channel::find($this->eztpv->event->channel_id);

        $sendSlackMsg = false;
        $eeeeeMsg = 'Unknown Error';

        if (\Illuminate\Support\Str::startsWith($step, 'generateSignaturePageContract contract selection failed to select contract')) {
            $sendSlackMsg = true;
            $eeeeeCode = substr($step, -2, 1);
            if ($eeeeeCode === false) {
                $eeeeeCode = '0';
            }

            switch ($eeeeeCode) {
                case '1':
                    $eeeeeMsg = '1 - hybrid multiple (same r/p), contract not found';
                    break;
                case '2':
                    $eeeeeMsg = '2 - multiple individual, one or more contracts not found';
                    break;
                case '3':
                    $eeeeeMsg = '3 - contract not found for single product';
                    break;
                case '4':
                    $eeeeeMsg = '4 - dual as single commodity contracts, one or more not found';
                    break;

                default:
                    break;
            }
        }

        $contractType = $this->eztpv->contract_type;
        if (is_numeric($contractType)) {
            $contractType = intval($contractType);
        }
        switch ($contractType) {
            default:
                $contractTypeDesc = 'none';
                break;
            case 1:
                $contractTypeDesc = 'Summary Page';
                break;
            case 2:
                $contractTypeDesc = 'Custom Contract';
                break;
            case 3:
                $contractTypeDesc = 'Signature Page';
                break;
            case 4:
                $contractTypeDesc = 'Signature Page with Custom Contract(s)';
                break;
        }

        $lang = 'English';
        if ($this->eztpv->event->language_id == 2) {
            $lang = 'Spanish';
        }

        $market = 'Unknown Market';
        $this->line(print_r($products, true));
        if (!empty($this->eztpv->event->products)) {
            switch ($this->eztpv->event->products[0]->market_id) {
                case 1:
                    $market = 'Residential';
                    break;

                case 2:
                    $market = 'Commercial';
                    break;

                default:
                    break;
            }
        }

        $product_names = implode('|', $this->eztpv->event->products->map(function ($item) {
            return $item->rate->product->name;
        })->toArray());

        $commodities = implode('|', $this->eztpv->event->products->map(function ($item) {
            switch ($item->event_type_id) {
                case 1:
                    return 'Electric';
                case 2:
                    return 'Gas';
                default:
                    return 'Unknown';
            }
        })->toArray());

        $rateTypes = implode('|', $this->eztpv->event->products->map(function ($item) {
            $mainTypeId = $item->rate->product->rate_type_id;
            switch ($mainTypeId) {
                default:
                case 1:
                    $type = 'Fixed';
                    break;

                case 2:
                    $type = 'Variable';
                    break;

                case 3:
                    if ($item->rate->rate_amount > 0) {
                        $type = 'Tiered-Fixed';
                    } else {
                        $type = 'Tiered-Variable';
                    }
                    break;
            }
            return $type;
        })->toArray());

        $isGreen = implode('|', $this->eztpv->event->products->map(function ($item) {
            if ($item->rate->product->green_percentage > 0) {
                return 'Yes';
            }
            return 'No';
        })->toArray());

        if ($step === 'Contract Generation Failed: no matching brand_eztpv_contract record found') {
            $sendSlackMsg = true;
            $eeeeeMsg = '5 - contract not found';
        }

        if ($sendSlackMsg && !$this->option('noSlack')) {
            $preview = ($this->option('preview')) ? 'Yes' : 'No';

            $slackMsg = 'Failed to select a contract.' . "\n";
            $slackMsg .= "\t" . 'Error: ' . $eeeeeMsg . "\n";
            $slackMsg .= "\t" . 'Contract Type: ' . $contractTypeDesc . "\n";
            $slackMsg .= "\t" . 'Confirmation Code: ' . $confirmation_code . "\n";
            $slackMsg .= "\t" . 'Is Preview: ' . $preview . "\n";
            $slackMsg .= "\t" . 'Brand: ' . optional($brand)->name . "\n";
            $slackMsg .= "\t" . 'Language: ' . $lang . "\n";
            $slackMsg .= "\t" . 'Market: ' . $market . "\n";
            $slackMsg .= "\t" . 'State: ' . optional($state)->state_abbrev . "\n";
            $slackMsg .= "\t" . 'Channel: ' . optional($channel)->channel . "\n";
            $slackMsg .= "\t" . 'Product(s): ' . $product_names . "\n";
            $slackMsg .= "\t" . 'Commodity(ies): ' . $commodities . "\n";
            $slackMsg .= "\t" . 'Rate Type(s): ' . $rateTypes . "\n";
            $slackMsg .= "\t" . 'Is Green: ' . $isGreen . "\n";
            $slackMsg .= "\t" . 'EZTPV ID: ' . $eztpv_id . "\n";
            SendTeamMessage(
                'triage',
                '```' . $slackMsg . '```'
            );
        }

        //Card - Stop mailing error messages from contract generator
       $email_data = [
           'step' => $step,
           'additional' => @$additional,
           'command_options' => $this->options(),
           'eztpv_id' => $eztpv_id,
           'event_confirmation_code' => $confirmation_code,
           'data' => $data,

           'company' => @$brand->name,
           'products' => @$products,
           'state' => @$state->state_abbrev,
           'channel' => @$channel->channel,
           'query' => @$this->dbQuery,
       ];
       $email_subject = 'Error during MGMT (' . config('app.env') . ') eztpv:generateContracts job';
       //$email_sendTo = 'engineering@tpv.com';
       $email_sendTo = 'damian.mcqueen@answernet.com';
       try {
           Mail::send(
               'emails.errorEztpvGenerateContractsJob',
               $email_data,
               function ($message) use ($email_subject, $email_sendTo) {
                   $message->subject($email_subject);
                   $message->from('no-reply@tpvhub.com');
                   $message->to(trim($email_sendTo));
               }
           );
       } catch (\Exception $e) {
           $this->info('Unable to send error email: ' . $e->getMessage());
           $this->info('Error: ' . ($step === null ? 'null step' : $step));
       }
    }

    private function hydrateVar($text, $vars = null)
    {
        // $this->logInfo($text);
        // $this->logInfo(print_r($vars, true));

        if ($vars !== null && count($vars) > 0) {
            $matches = [];
            preg_match_all(
                "/\{\{(.*?)\}\}/",
                $text,
                $matches
            );

            // $this->logInfo(print_r($matches, true));

            if (count($matches) > 0) {
                $values = array_unique($matches[1]);

                foreach ($values as $value) {
                    // $this->logInfo('value = ' . $value);
                    if (array_key_exists($value, $vars)) {
                        $text = str_replace(
                            '{{' . $value . '}}',
                            $vars[$value],
                            $text
                        );
                    }
                }
            }
        }

        return $text;
    }

    private function remoteImageToBase64($logo_url) {
        try {
            // May or may not be set.  Return empty string if null
            if (!$logo_url) { return ''; }

            // Get the file extension, expecting .png or .jpg as the last part of the string
            $explode_path = explode('.', $logo_url);
            // uses the path exploded 0 => domain, 1 => net 2 => jpg to get the "jpg" part, since domains are .com .net etc so more than 2 array elements
            $type = $explode_path[array_key_last($explode_path)];
            // file_get_contents can read remote files.  Read the file contents from the remote file
            $data = file_get_contents($logo_url);
            // return a base64 encoded value to use as a "src: data:image/jpg;base64, h408c3j29x"
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        catch(\Exception $e) {
            $this->logInfo("GenerateEzTpvContracts.remoteImageToBase64 Error: {$e->getMessage()} for $logo_url", __METHOD__, __LINE__);
            return '';
        }
    }

    private function generateSignaturePageTemplate($event, $eztpv, $products, $formData, $lang)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);
        
        $summary = [];

        // $mapImage = null;
        if (isset($formData['gps_coords']) && null !== $formData['gps_coords']) {
            $coords = explode(',', $formData['gps_coords']);
            // $markers = [
            //     [
            //         'color' => 'red',
            //         'latitude' => $coords[0],
            //         'longitude' => $coords[1],
            //     ],
            // ];
            // $mapImage = $this->getGoogleMapsImage($markers[0], $markers, $lang);

            // $this->info('MapImage: ' . $mapImage);
            // if (null !== $mapImage) {
            //     $iContents = base64_encode(file_get_contents($mapImage));
            //     $mapImage = 'data:image/png;base64,' . $iContents;
            // }
        }

        foreach ($products as $key => $ep) {
            if (!isset($summary['brand_logo_id'])) {
                if (isset($ep->event->brand->logo_path)) {
                    $summary['brand_logo_id'] = $ep->event->brand->logo_path;
                }
            }
        }

        // Scoped Variable to handle when $summary['brand_logo_id'] is not set, such as our Test Brands (IE if its never set then variable is OUT OF SCOPE)
        $logoPath = null;

        if (isset($summary['brand_logo_id'])) {
            $logoPath = Upload::select(
                'filename'
            )->where(
                'uploads.id',
                $summary['brand_logo_id']
            )->first();
        }

        // The @ symbol is an Error Supressor.  IE $foo = null, then access a property on null, supress the error: @$foo->my_prop throws a Warning
        $this->info('Logo: ' . config('services.aws.cloudfront.domain') . '/' . @$logoPath->filename);

        $brand = '<h2>' . $event->brand->name . '</h2>';
        // if (
        //     isset($logoPath)
        //     && $logoPath
        // ) {
        //     $logoFile = $this->s3Download($logoPath->filename);
        //     if (
        //         !is_array($logoFile)
        //         && 'error' !== $logoFile[0]
        //     ) {
        //         $logoEmbed = 'data:image/png;base64,' . base64_encode($logoFile);
        //         $brand = '<img width="100" src="' . $logoEmbed . '" />';
        //     }
        // }

        $logo_url = null;
        if ($logoPath) {
            $logo_url = config('services.aws.cloudfront.domain');
            // if domain does not have a / character at end... http://domain.com, then we need to add a slash as url separtor
            if ($logo_url[strlen($logo_url) - 1] != '/') { $logo_url .= '/'; }
            $logo_url .= $logoPath->filename;
        }
        
        $logo_base64 = $this->remoteImageToBase64($logo_url);
        $blank_base64 = $this->remoteImageToBase64('https://tpv-assets.s3.amazonaws.com/blank.jpg');

        $brand = (isset($logoPath) && $logoPath)
            ? "<img width=\"100\" src=\"$logo_base64\" />"
            : '<h2>' . $event->brand->name . '</h2>';

        // Removed as this was changed to <img width="100" src="data:image/jpg;base64,/ where because its base64 data, not a file, its a LOT of data to show
        //$this->info('brand = ' . $brand);
        $gps = (isset($formData['gps_coords']))
            ? explode(',', $formData['gps_coords'])
            : null;

        $english = [
            'billing_account_number' => 'Billing Account Number',
            'choice_id' => 'Choice ID',
            'customer_number' => 'Customer Number',
            'device_id' => 'Device ID',
            'esi_id' => 'ESI ID',
            'meter_number' => 'Meter Number',
            'name_key' => 'Name Key',
            'pod_id' => 'POD ID',
            'service_agreement_id' => 'Service Agreement ID',
            'service_agreement_number' => 'Service Agreement Number',
            'service_delivery_identifier' => 'Service Delivery Identifier',
            'service_number' => 'Service Number',
            'service_point_id' => 'Service Point ID',
            'service_reference_number' => 'Service Reference Number',
            'site_id' => 'Site ID',
            'supplier_agreement_id' => 'Supplier Agreement ID',
            'account_number' => 'Account Number',
            'service_number' => 'Service Number',
            'address' => 'Address',
            'agent' => 'Agent',
            'agent_id' => 'Agent ID',
            'agent_sig' => 'Agent',
            'auth_name' => 'Auth Name',
            'authorized_by' => 'Authorized By',
            'billing_address' => 'Billing',
            'billing_name' => 'Billing Name',
            'cancellation' => 'Cancellation',
            'channel' => 'Sales Channel',
            'confirmation_code' => 'Confirmation Code',
            'created' => 'Created',
            'customer_sig' => 'Customer',
            'daily_fee' => 'Daily Fee',
            'email' => 'Email',
            'electric' => 'Electric',
            'enrollment_processing' => 'We are currently processing your enrollment.',
            'enrollment_utility' => 'Your enrollment has been sent to your utility. Your utility will send you a confirmation notice confirming your selection of {{brand}} as your supplier.',
            'gas' => 'Natural Gas',
            'gps_lat' => 'GPS Lat',
            'gps_lon' => 'GPS Lon',
            'green' => 'Green',
            'identifier' => 'Identifier',
            'information' => 'Information',
            'intro_cancellation' => 'Intro Cancellation',
            'intro_rate' => 'Initial Rate',
            'intro_term' => 'Intro Term',
            'ip_addr' => 'IP Address',
            'monthly_fee' => 'Monthly Fee',
            'per' => 'per',
            'phone' => 'Phone',
            'product' => 'Product',
            'program_code' => 'Program Code',
            'rate_amount' => 'Rate Amount',
            'service_address' => 'Service',
            'service_meter' => 'Your service will begin with your first meter read by your utility after your enrollment is accepted, which may take up to 1-2 billing cycles.',
            'service_summary' => 'Below is a summary of your service account with <b>{{brand}}</b>:',
            'sig_page_confirms' => 'This signature page confirms your choice to enroll with <b>{{brand}}</b> and provides a summary of your new service account. The terms and conditions are attached for your reference.',
            'signature_page' => 'SIGNATURE PAGE TO SERVICES AGREEMENT',
            'term' => 'Term',
            'thank_you' => 'Thank you for choosing {{brand}}!',
            'type' => 'Type',
            'utility' => 'Utility',
            'variable' => 'Variable',
            'contract_summary' => 'CONTRACT SUMMARY',
            'month' => 'month',
            'onetime' => 'one time fee',
            'month_remaining' => 'per month remaining on the contract',
        ];
        $spanish = [
            'billing_account_number' => 'Número de Cuenta de Facturación',
            'choice_id' => 'ID de Elección',
            'customer_number' => 'Número de Cliente',
            'device_id' => 'ID del Dispositivo',
            'esi_id' => 'ID ESI',
            'meter_number' => 'Número de Medidor',
            'name_key' => 'Clave de Nombre',
            'pod_id' => 'ID del POD',
            'service_agreement_id' => 'ID de Acuerdo de Servicio',
            'service_agreement_number' => 'Número de Acuerdo de Servicio',
            'service_delivery_identifier' => 'Identificador de Prestación de Servicios',
            'service_number' => 'Número de Servicio',
            'service_point_id' => 'ID del Punto de Servicio',
            'service_reference_number' => 'Número de Referencia del Servicio',
            'site_id' => 'Identificación del Sitio',
            'supplier_agreement_id' => 'ID del Acuerdo del Proveedor',
            'account_number' => 'Número de cuenta',
            'service_number' => 'Número del servicio',
            'address' => 'Dirección',
            'agent' => 'Agente',
            'agent_id' => 'ID del Agente ',
            'agent_sig' => 'Agente',
            'auth_name' => 'Nombre de Autenticación',
            'authorized_by' => 'Autorizado por',
            'billing_address' => 'Envio',
            'billing_name' => 'Nombre de Facturación',
            'cancellation' => 'Cancelación',
            'channel' => 'Canal de Ventas',
            'confirmation_code' => 'Código de Confirmación',
            'created' => 'Creado',
            'customer_sig' => 'Cliente',
            'daily_fee' => 'Tarifa Diaria',
            'email' => 'Correo Electrónico',
            'electric' => 'Electricidad',
            'enrollment_processing' => 'Actualmente estamos procesando su inscripción.',
            'enrollment_utility' => 'Su inscripción ha sido enviada a su empresa de servicios públicos. Su utilidad le enviará un aviso de confirmación confirmando su selección de {{brand}} como su proveedor.',
            'gas' => 'Gas Natural ',
            'gps_lat' => 'GPS Lat',
            'gps_lon' => 'GPS Lon',
            'green' => 'Verde',
            'identifier' => 'Identificador',
            'information' => 'Información',
            'intro_cancellation' => 'Cancelación de Introducción',
            'intro_rate' => 'Tasa inicial',
            'intro_term' => 'Término de Introducción',
            'ip_addr' => 'Dirección IP',
            'monthly_fee' => 'Tarifa mensual',
            'per' => 'por',
            'phone' => 'Teléfono',
            'product' => 'Producto',
            'program_code' => 'Código de Programa',
            'rate_amount' => 'Cantidad de Tarifa',
            'service_address' => 'Servicio',
            'service_meter' => 'Su servicio comenzará con su primer medidor leído por su empresa de servicios públicos después de que se acepte su inscripción, lo que puede demorar hasta 1-2 ciclos de facturación.',
            'service_summary' => 'A continuación se muestra un resumen de su cuenta de servicio con <b>{{brand}}</b>:',
            'sig_page_confirms' => 'Esta página de firma confirma su elección de inscribirse con <b>{{brand}}</b> y proporciona un resumen de su nueva cuenta de servicio. Los términos y condiciones se adjuntan para su referencia.',
            'signature_page' => 'PÁGINA DE FIRMA DEL ACUERDO DE SERVICIOS',
            'term' => 'Término',
            'thank_you' => 'Gracias por elegir {{brand}}!',
            'type' => 'Tipo',
            'utility' => 'Utilidad',
            'variable' => 'Variable',
            'contract_summary' => 'RESUMEN DEL CONTRATO',
            'month' => 'mes',
            'onetime' => 'tarifa única',
            'month_remaining' => 'por mes restante en el contrato',
        ];

        if ($lang == 'es') {
            $language = $spanish;
        } else {
            $language = $english;
        }

        $template = '<html>
                <head>
                <style>
                body {font-size: 10pt; font-family: serif;}
                p {	margin: 0pt; }
                table.items {
                    border: 0.1mm solid #000000;
                }
                td { vertical-align: top; }
                .items td {
                    border-left: 0.1mm solid #000000;
                    border-right: 0.1mm solid #000000;
                }
                table thead td { background-color: #EEEEEE;
                    text-align: center;
                    border: 0.1mm solid #000000;
                    font-variant: small-caps;
                }
                .items td.blanktotal {
                    background-color: #EEEEEE;
                    border: 0.1mm solid #000000;
                    background-color: #FFFFFF;
                    border: 0mm none #000000;
                    border-top: 0.1mm solid #000000;
                    border-right: 0.1mm solid #000000;
                }
                .items td.totals {
                    text-align: right;
                    border: 0.1mm solid #000000;
                }
                .items td.cost {
                    text-align: "." center;
                }
                .page_break { page-break-before: always; }
                </style>
                </head>
                <body>
                <table width="100%">
                    <tr>
                        <td width="33%">' . $brand . '</td>
                        <td width="33%" align="center">
                            <br /><br /><h3>' . $this->hydrateVar($language['signature_page']) . '</h3>
                        </td>
                        <td width="33%" align="right">
                            <br />
                            <b>' . $event->brand->name . '</b><br />
                            ' . $event->brand->address . '<br />
                            ' . $event->brand->city . ', ' . $event->brand->brandState->state_abbrev . ' ' . $event->brand->zip . '<br />';

        if ($event->brand->email_address) {
            $template .= $event->brand->email_address . '<br />';
        }

        if ($event->brand->service_number) {
            $template .= substr_replace(substr_replace(str_replace('+1', '', $event->brand->service_number), '-', 3, 0), '-', 7, 0) . '<br />';
        }

        $the_channel = null;
        switch ($event->channel_id) {
            default:
            case 1:
                $the_channel = 'D2D';
                break;
            case 2:
                $the_channel = 'TM';
                break;
            case 3:
                $the_channel = 'Retail';
                break;
        }

        $template .= '
                        </td>
                    </tr>
                </table>

                <hr />

                <table width="100%">
                    <tr>
                        <td width="60%">
                            <strong>' . $this->hydrateVar($language['thank_you'], ['brand' => $event->brand->name]) . '</strong><br /><br />

                        <p>' . $this->hydrateVar($language['sig_page_confirms'], ['brand' => $event->brand->name]) . '</p><br />
                        <p><b>' . $this->hydrateVar($language['enrollment_processing']) . '</b></p><br />
                        <p>' . $this->hydrateVar($language['enrollment_utility'], ['brand' => $event->brand->name]) . '</p><br />
                        <p>' . $this->hydrateVar($language['service_summary'], ['brand' => $event->brand->name]) . '</p><br /><br />
                    </td>
                    <td width="40%">
                    <table width="100%" style="font-family: serif;" cellpadding="10">
                        <tr>
                            <td width="45%" style="border: 0.1mm solid #888888;">
                                <span style="font-size: 7pt; color: #555555;">Information</span><br /><br />
                                <strong>' . $this->hydrateVar($language['created']) . ':</strong> ' . $event->created_at . '<br/>
                                <strong>' . $this->hydrateVar($language['confirmation_code']) . ':</strong> ' . $event->confirmation_code . '<br/>
                                <strong>' . $this->hydrateVar($language['agent']) . ':</strong> ' . $event->sales_agent->user->first_name . ' ' . $event->sales_agent->user->last_name . '<br/>
                                <strong>' . $this->hydrateVar($language['agent_id']) . ':</strong> ' . $event->sales_agent->tsr_id . '<br/>
                                <strong>' . $this->hydrateVar($language['channel']) . ':</strong> ' . $the_channel . '<br/>
                                <strong>' . $this->hydrateVar($language['auth_name']) . ':</strong> ' . $products[0]->auth_first_name . ' ' . $products[0]->auth_last_name . '<br/>
                                <strong>' . $this->hydrateVar($language['phone']) . ':</strong> ' . substr_replace(substr_replace(str_replace('+1', '', $event->phone->phone_number->phone_number), '-', 3, 0), '-', 7, 0) . '<br/>';

        if (isset($event->email->email_address->email_address)) {
            $template .= '
               <strong>' . $this->hydrateVar($language['email']) . ':</strong> ' . $event->email->email_address->email_address . '<br/>';
        }

        if (
            isset($event->ip_addr)
            && $event->ip_addr !== '0.0.0.0'
        ) {
            $template .= '
            <strong>' . $this->hydrateVar($language['ip_addr']) . ':</strong> ' . $event->ip_addr . '<br/>';
        }

        if ($gps) {
            $template .= '
                    <strong>' . $this->hydrateVar($language['gps_lat']) . ':</strong> ' . $gps[0] . '<br/>
                    <strong>' . $this->hydrateVar($language['gps_lon']) . ':</strong> ' . $gps[1] . '<br/>';
        }

        $template .= '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table width="100%" border="1" cellpadding="8" cellspacing="0">
                <tr>
                    <th>' . $this->hydrateVar($language['identifier']) . '</th>
                    <th>' . $this->hydrateVar($language['billing_name']) . '</th>
                    <th>' . $this->hydrateVar($language['address']) . '</th>
                    <th>' . $this->hydrateVar($language['product']) . '</th>
                </tr>';

        foreach ($products->toArray() as $product) {
            $bbbb = true;

            if(!$bbbb) {
                switch ($product['utility_supported_fuel']['utility_fuel_type']['utility_type']) {
                    case 'Natural Gas':
                        $utility_type = $this->hydrateVar($language['gas']);
                        break;
                    case 'Electric':
                        $utility_type = $this->hydrateVar($language['electric']);
                        break;
                }

                $template .= '<tr><td>';
                $template .= $this->hydrateVar($language['type']) . ': ' . $utility_type . '<br/>';
            }

            foreach ($product['identifiers'] as $ident) {
                switch ($ident['utility_account_type']['account_type']) {
                    case 'Account Number':
                        $account_type = $this->hydrateVar($language['account_number']);
                        break;
                    case 'Service Number':
                        $account_type = $this->hydrateVar($language['service_number']);
                        break;
                    case 'Billing Account Number':
                        $account_type = $this->hydrateVar($language['billing_account_number']);
                        break;
                    case 'Choice ID':
                        $account_type = $this->hydrateVar($language['choice_id']);
                        break;
                    case 'Customer Number':
                        $account_type = $this->hydrateVar($language['customer_number']);
                        break;
                    case 'Device ID':
                        $account_type = $this->hydrateVar($language['device_id']);
                        break;
                    case 'ESI ID':
                        $account_type = $this->hydrateVar($language['esi_id']);
                        break;
                    case 'Meter Number':
                        $account_type = $this->hydrateVar($language['meter_number']);
                        break;
                    case 'Name Key':
                        $account_type = $this->hydrateVar($language['name_key']);
                        break;
                    case 'POD ID':
                        $account_type = $this->hydrateVar($language['pod_id']);
                        break;
                    case 'Service Agreement ID':
                        $account_type = $this->hydrateVar($language['service_agreement_id']);
                        break;
                    case 'Service Agreement Number':
                        $account_type = $this->hydrateVar($language['service_agreement_number']);
                        break;
                    case 'Service Delivery Identifier':
                        $account_type = $this->hydrateVar($language['service_delivery_identifier']);
                        break;
                    case 'Service Number':
                        $account_type = $this->hydrateVar($language['service_number']);
                        break;
                    case 'Service Point ID':
                        $account_type = $this->hydrateVar($language['service_point_id']);
                        break;
                    case 'Service Reference Number':
                        $account_type = $this->hydrateVar($language['service_reference_number']);
                        break;
                    case 'Site ID':
                        $account_type = $this->hydrateVar($language['site_id']);
                        break;
                    case 'Supplier Agreement ID':
                        $account_type = $this->hydrateVar($language['supplier_agreement_id']);
                        break;
                    default:
                        $account_type = $ident['utility_account_type']['account_type'];
                        break;
                }

                $template .= $ident['identifier'] . '<br/> (' . $account_type . ')<br/>';
            }

            $template .= '</td>';

            if ($product['market_id'] === 1) {
                $template .= '<td>' . $product['bill_first_name'] . ' ' . $product['bill_middle_name'] . ' ' . $product['bill_last_name'] . '</td>';
            } else {
                $template .= '<td>' . $product['company_name'] . '</td>';
            }

            $template .= '<td>
                            <strong>' . $this->hydrateVar($language['service_address']) . ':</strong> '
                . $product['service_address']['address']['line_1']
                . ' ' . $product['service_address']['address']['line_2']
                . '<br />' . $product['service_address']['address']['city']
                . ', ' . $product['service_address']['address']['state_province']
                . ' ' . $product['service_address']['address']['zip'] . '<br />';

            if (
                isset($product['billing_address']['address']['line_1'])
                && strlen(trim($product['billing_address']['address']['line_1'])) > 0
            ) {
                $template .= '
                            <strong>' . $this->hydrateVar($language['billing_address']) . ':</strong> '
                    . $product['billing_address']['address']['line_1']
                    . ' ' . $product['billing_address']['address']['line_2']
                    . '<br />' . $product['billing_address']['address']['city']
                    . ', ' . $product['billing_address']['address']['state_province']
                    . ' ' . $product['billing_address']['address']['zip'];
            }

            $template .= '
                        </td>
                        <td>
                            <strong>' . $this->hydrateVar($language['utility']) . ':</strong> ' . $product['utility_supported_fuel']['utility']['name'] . '<br/>
                            <strong>' . $this->hydrateVar($language['product']) . ':</strong> ' . $product['rate']['product']['name'] . '<br/>
                            <strong>' . $this->hydrateVar($language['program_code']) . ':</strong> ' . $product['rate']['program_code'] . '<br/>';


            $localized_currency = $product['rate']['rate_currency']['currency'];

            if (2 == $event->language_id) {
                switch ($product['rate']['rate_currency']['currency']) {

                    case 'dollars':
                        $localized_currency = 'dólares';
                        break;

                    case 'cents' :
                        $localized_currency = 'centavos';
                        break;

                    default:
                        $localized_currency = 'centavos';
                        break;
                }
            }

            $localized_uom =  $product['rate']['rate_uom']['uom'];

            if (2 == $event->language_id) {
                switch ($product['rate']['rate_uom']['uom']) {

                    case 'kwh':
                        $localized_uom = 'Kilovatio-hora';
                        break;

                    case 'therm':
                        $localized_uom = 'Termia';
                        break;


                }
            }



            if (
                isset($product['rate']['intro_rate_amount'])
                && null !== $product['rate']['intro_rate_amount']
                && 0 !== $product['rate']['intro_rate_amount']
                && '0' !== $product['rate']['intro_rate_amount']
            ) {
                $template .= '
                        <strong>' . $this->hydrateVar($language['intro_rate']) . ':</strong> ' . $product['rate']['intro_rate_amount'] . ' ' . $localized_currency . ' ' . $this->hydrateVar($language['per']) . ' ' . $localized_uom . '<br/>';
            }
            $localized_term_type = "";
            if (isset($product['rate']['product']['term_type']['term_type']) && null !== $product['rate']['product']['term_type']['term_type']) {
                if (2 == $event->language_id) {
                    switch ($product['rate']['product']['term_type']['term_type']) {
                        case 'day':
                            $localized_term_type = 'dia(s)';
                            break;
                        case 'week':
                            $localized_term_type = 'semana(s)';
                            break;
                        case 'year':
                            $localized_term_type = 'año(s)';
                            break;
                        default:
                            $localized_term_type = 'mes(es)';
                            break;
                    }
                }
            }

            if (isset($product['rate']['product']['intro_term']) && null !== $product['rate']['product']['intro_term']) {
                if (2 == $event->language_id) {
                    switch ($product['rate']['product']['intro_term_type']['term_type']) {
                        case 'day':
                            $localized_intro_term_type = 'dias';
                            break;
                        case 'week':
                            $localized_intro_term_type = 'semanas';
                            break;
                        case 'year':
                            $localized_intro_term_type = 'años';
                            break;
                        default:
                            $localized_intro_term_type = 'mes';
                            break;
                    }
                    if (
                        $product['rate']['product']['intro_term'] > 1
                    ) {
                        if (
                            $product['rate']['product']['intro_term_type']['term_type'] != 'month'
                        ) {
                            $localized_intro_term_type = $localized_intro_term_type . 's';
                        } else {
                            $localized_intro_term_type = $localized_intro_term_type . 'es';
                        }
                    }
                } else {
                    $localized_intro_term_type = $product['rate']['product']['intro_term_type']['term_type'];
                    if (
                        $product['rate']['product']['intro_term'] > 1
                    ) {
                        $localized_intro_term_type = $localized_intro_term_type . 's';
                    }
                }
                $template .= '
                        <strong>' . $this->hydrateVar($language['intro_term']) . ':</strong> ' . $product['rate']['product']['intro_term'] . ' ' . $localized_intro_term_type . '<br/>';
            }

            if (isset($product['rate']['intro_cancellation_fee']) && $product['rate']['intro_cancellation_fee'] !== null) {
                $template .= '<strong>' . $this->hydrateVar($language['intro_cancellation']) . ':</strong> $' . $product['rate']['intro_cancellation_fee'] . '<br />';
            }

            $template .= '<strong>' . $this->hydrateVar($language['rate_amount']) . ':</strong> ';

            if (null == $product['rate']['rate_amount']) {
                $template .= $this->hydrateVar($language['variable']) . '<br/>';
            } else {
                $template .= $product['rate']['rate_amount'] . ' ' . $localized_currency . ' ' . $this->hydrateVar($language['per']) . ' ' . $localized_uom . '<br/>';
            }

            if (isset($product['rate']['product']['term']) && null !== $product['rate']['product']['term'] && $product['rate']['product']['term'] > 0) {
                $template .= '<strong>' . $this->hydrateVar($language['term']) . ':</strong> ' . $product['rate']['product']['term'] . ' ' . ($event->language_id == 1 ? $product['rate']['product']['term_type']['term_type'] : $localized_term_type) . '<br/>'; //Translate month to Spanish
            } else {
                $template .= '<strong>' . $this->hydrateVar($language['term']) . ':</strong> ' . ($event->language_id == 1 ? 'Month to Month' : 'Mes a Mes') . '<br/>';
            }

            if (isset($product['rate']['cancellation_fee']) && null !== $product['rate']['cancellation_fee']) {
                $template .= '<strong>' . $this->hydrateVar($language['cancellation']) . ':</strong> $'
                    . $product['rate']['cancellation_fee'] . ' ';

                switch (@$product['rate']['cancellation_fee_term_type']['term_type']) {
                    case 'month':
                        $template .= $this->hydrateVar($language['month_remaining']);
                        break;
                    default:
                        $template .= $this->hydrateVar($language['onetime']);
                }

                $template .= '<br />';
            }

            if (isset($product['rate']['product']['daily_fee']) && null !== $product['rate']['product']['daily_fee']) {
                $template .= '<strong>' . $this->hydrateVar($language['daily_fee']) . ':</strong> ' . $product['rate']['product']['daily_fee'] . ' cents<br/>';
            }

            if (isset($product['rate']['product']['monthly_fee']) && $product['rate']['product']['monthly_fee'] > 0) {
                $template .= '<strong>' . $this->hydrateVar($language['monthly_fee']) . ':</strong> $' . $product['rate']['product']['monthly_fee'] . ' ' . $this->hydrateVar($language['per']) . ' ' . $this->hydrateVar($language['month']) . '<br/>';
            }

            if (isset($product['rate']['rate_monthly_fee']) && $product['rate']['rate_monthly_fee'] > 0) {
                $template .= '<strong>' . $this->hydrateVar($language['monthly_fee']) . ':</strong> $' . $product['rate']['rate_monthly_fee'] . ' ' . $this->hydrateVar($language['per']) . ' ' . $this->hydrateVar($language['month']) . '<br/>';
            }

            // if (null !== $product['rate']['product']['monthly_fee']) {
            //     $template .= '<strong>' . $this->hydrateVar($language['monthly_fee']) . ':</strong> $' . $product['rate']['product']['monthly_fee'] . '<br/>';
            // }

            if (null !== $product['rate']['product']['green_percentage']) {
                $template .= '<strong>' . $this->hydrateVar($language['green']) . ' %:</strong> ' . $product['rate']['product']['green_percentage'] . '<br/>';
            }

            $template .= '
                    </td>
                </tr>';
        }

        $api_submission = false;
        foreach ($event->interactions as $interaction) {
            if ($interaction->interaction_type_id === 11) {
                $api_submission = true;
            }
        }
        $customer_signature_embed = 'img';
        if (isset($eztpv->signature_customer)) {
            $customer_signature = $eztpv->signature_customer->signature;
            $customer_signature_updated = $eztpv->signature_customer->updated_at;
        } elseif (isset($eztpv->signature)) {
            $customer_signature = $eztpv->signature;
            if (isset($eztpv->signature_date)) {
                $customer_signature_updated = $eztpv->signature_date;
            }
        } elseif ($api_submission === true) {
            $customer_signature_embed = 'text';
            $customer_signature = 'Inbound customer call.  Voice authorization saved to file. ' . $event->confirmation_code;
            $customer_signature_updated = null;
        } else {
            $customer_signature = 'https://tpv-assets.s3.amazonaws.com/blank.jpg';
            $customer_signature_updated = null;
        }

        $agent_signature_embed = 'img';
        if (isset($eztpv->signature_agent)) {
            $agent_signature = $eztpv->signature_agent->signature;
            $agent_signature_updated = $eztpv->signature_agent->updated_at;
        } elseif (isset($eztpv->signature2)) {
            $agent_signature = $eztpv->signature2;
            if (isset($eztpv->signature2_date)) {
                $agent_signature_updated = $eztpv->signature2_date;
            }
        } elseif ($api_submission === true) {
            // commented out for now. all api submitted agent sigs will be blank.
            // $agent_signature_embed = 'text';
            // $agent_signature = 'Inbound customer call.  Voice authorization saved to file. ' . $event->confirmation_code;
            $agent_signature = 'https://tpv-assets.s3.amazonaws.com/blank.jpg';
            $agent_signature_updated = null;
        } else {
            $agent_signature = 'https://tpv-assets.s3.amazonaws.com/blank.jpg';
            $agent_signature_updated = null;
        }

        $template .= '
            </table>

            <br />

            <b>' . $this->hydrateVar($language['authorized_by']) . ':</b>
            <table width="100%" border="0">
                <tr>
                    <td width="50%" align="center">
                        <table width="100%">
                            <tr>
                                <td>';

        // Always remember these values, which may be overridden due to a Preview Option
        $last_customer_signature_embed = $customer_signature_embed;
        $last_customer_signature = $customer_signature;
        $last_agent_signature = $agent_signature;
        $last_agent_signature_embed = $agent_signature_embed;
        // If contract is being Previewed, the Signature data needs to be null
        if ($this->option('preview')){
            $customer_signature_embed = null; 
            $customer_signature = $blank_base64;
            $agent_signature_embed = null;
            $agent_signature = $blank_base64;
        }

        switch ($customer_signature_embed) {
            case 'text':
                $template .= $customer_signature;
                break;

            case 'img':
            default:
                $template .= '<img style="height: 100px;" src="' . $customer_signature . '" />';
                break;
        }
        $template .= '
                                    <hr />
                                    <table width="100%" style="font-size: 10pt;">
                                        <tr>
                                            <td align="center">' . $this->hydrateVar($language['customer_sig']) . '</td>
                                            <td align="center">' . $products[0]['auth_first_name'] . ' ' . $products[0]['auth_middle_name'] . ' ' . $products[0]['auth_last_name'] . '</td>
                                            <td align="center">' . $customer_signature_updated . '</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td width="50%" align="center">
                        <table width="100%">
                            <tr>
                                <td>';
        switch ($agent_signature_embed) {
            case 'text':
                $template .= $agent_signature;
                break;

            case 'img':
            default:
                $template .= '<img style="height: 100px;" src="' . $agent_signature . '" />';
                break;
        }
        $template .= '
                                    <hr />
                                    <table width="100%" style="font-size: 10pt;">
                                        <tr>
                                            <td align="center">' . $this->hydrateVar($language['agent_sig']) . '</td>
                                            <td align="center">' . $event->sales_agent->user->first_name . ' ' . $event->sales_agent->user->last_name . '</td>
                                            <td align="center">' . $agent_signature_updated . '</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>';

        $customer_signature_embed = $last_customer_signature_embed;
        $customer_signature = $last_customer_signature;            
        $agent_signature = $last_agent_signature;
        $agent_signature_embed = $last_agent_signature_embed;            

        if ($this->option('debug')) {
            $this->logInfo(print_r($products->toArray(), true), __METHOD__, __LINE__);
        }

        $template .= '
                </body>
                </html>
                ';

        // Enable this line to send the HTML to the Laravel log file for easier manipulation
        $this->logInfo("Contract Generator using HTML Template\n\n$template\n\n", __METHOD__, __LINE__);                

        return $template;
    }

    /**
     * getGoogleMapsImage.
     * Parameter values set from https://developers.google.com/maps/documentation/maps-static/dev-guide#URL_Parameters.
     *
     * @param $center Array
     * @param $markers Array
     * @param $language String
     * @param $mapType String
     * @param $scale Integer
     * @param $size String
     * @param $zoom Integer
     * @param $format String
     *
     * @return string The URI of the maps image
     */
    private function getGoogleMapsImage(array $center, array $markers, string $language = 'en', string $mapType = 'roadmap', int $scale = 2, string $size = '300x300', int $zoom = 13, string $format = 'png'): string
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        $key = config('services.google.maps.key'); // Google Maps API Key
        $secret = config('services.google.maps.secret'); // Google Maps API Signing Secret
        if (null == $key) {
            return null;
        }

        $domain = 'https://maps.googleapis.com';
        $baseUrl = '/maps/api/staticmap';

        $ret = "{$baseUrl}?center={$center['latitude']},{$center['longitude']}&language={$language}&scale={$scale}&size={$size}&format={$format}&maptype={$mapType}&zoom={$zoom}";

        foreach ($markers as $marker) {
            $ret .= "&markers=color:{$marker['color']}%7C{$marker['latitude']},{$marker['longitude']}";
        }

        $ret .= '&key=' . $key;

        $signature = '';
        if (is_string($secret) && strlen($secret) > 0) {
            $signature = '&signature=' . base64_encode(hash_hmac('sha1', $ret, $secret, true));
        }

        return $domain . $ret . $signature;
    }

    private function generateSignaturePageContract($eztpv, $event, $data, $commodity)
    {
        $this->logInfo('Entered function.', __METHOD__, __LINE__);

        if ($this->option('contract') != null) {
            $pdf_info[] = BrandEztpvContract::find($this->options('contract'));
        }

        $mpdf = new \Mpdf\Mpdf(
            [
                'tempDir' => public_path('tmp'),
                'mode' => 'utf-8',
                'format' => [216, 279],
                'margin_left' => 5,
                'margin_right' => 8,
                'margin_top' => 0,
                'margin_bottom' => 5,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]
        );

        $mpdf->pdf_version = '1.4';
        $mpdf->SetAuthor('TPV.com');
        // $mpdf->SetDisplayMode('fullpage');

        // $this->pdfOptions = new Options();
        // $this->pdfOptions->set('defaultFont', 'DejaVue Serif'); // for international characters
        // $this->pdfOptions->setIsRemoteEnabled(true);

        // $this->pdfGen = new Dompdf($this->pdfOptions);
        // $this->pdfGen->setPaper('Letter', 'portrait');

        $this->clearEDS($eztpv->id);
        $lang = 'en';
        switch ($event->language_id) {
            case 2:
                $lang = 'es';
                break;

            default:
                $lang = 'en';
        }

        if ($this->option('override-lang')) {
            $lang = $this->option('override-lang');
            $this->info('Overriding Language to ' . $lang);
        }

        App::setLocale($lang);

        $products = EventProduct::where(
            'event_id',
            $event->id
        )->with(
            [
                'rate',
                'rate.product' => function ($query) {
                    $query->withTrashed();
                },
                'rate.product.intro_term_type',
                'rate.product.term_type',
                'rate.product.rate_type',
                'rate.rate_uom',
                'rate.term_type',
                'rate.rate_currency',
                'rate.cancellation_fee_term_type',
                'serviceAddress',
                'billingAddress',
                'identifiers',
                'identifiers.utility_account_type',
                'market',
                'utility_supported_fuel',
                'home_type',
                'customFields',
                'utility_supported_fuel.utility',
                'utility_supported_fuel.utility_fuel_type',
                'promotion',
            ]
        );
        
        // Special logic for IDT IL DTD and Residents IL DTD.
        // Exclude gas accounts collected so they don't show up on the signature page.
        // In IL IDTE/Residents agents use paper contracts for natural gas, so we don't need to generate a PDF contract for those accounts.
        // We're using this hard-coded logic as Genie still wants the IL DTD contract setting in EZTPV config to be honored for electric accounts.
        // In the case of dual fuel, we'll end up creating a contract only for the electric account.
        if(
            ($eztpv->brand_id == self::BRAND_IDS['idt_energy']['production']
            || $eztpv->brand_id == self::BRAND_IDS['idt_energy']['staging']
            || $eztpv->brand_id == self::BRAND_IDS['residents_energy']['production']
            || $eztpv->brand_id == self::BRAND_IDS['residents_energy']['staging'])
            && isset($data['finalized_products'])
            && isset($data['finalized_products'][0])
            && isset($data['finalized_products'][0][0])
            && isset($data['finalized_products'][0][0]['service_state'])
            && strtolower($data['finalized_products'][0][0]['service_state']) == 'il'
            && $event['channel_id']
        ) {
            $products = $products->where('event_type_id', 1);
        }
        
        $products = $products->get();

        $sigPage = $this->generateSignaturePageTemplate($event, $eztpv, $products, $data, $lang);

        // $this->pdfGen->loadHtml($sigPage);
        // $this->pdfGen->render();

        $mpdf->WriteHTML($sigPage);

        App::setLocale('en');
        //$sigPagePdfContent = $this->pdfGen->output();
        $sigPagePdfContent = $mpdf->Output(null, \Mpdf\Output\Destination::STRING_RETURN);
        $sigPageFile = public_path('/tmp/' . $eztpv->id . '.pdf');
        try {
            file_put_contents($sigPageFile, $sigPagePdfContent);
        } catch (\Exception $e) {
            return ['error', 'Writing sigpage: ' . $e->getMessage()];
        }
        $this->info('Output PDF: ' . $sigPageFile);
        $delete_contract = false;
        copy($sigPageFile, $sigPageFile . '-output.pdf');
        $outputFile = $sigPageFile . '-output.pdf';

        $hasAdditionalContract = false;

        if ($this->options('contract') != null) {
            $hasAdditionalContract = true;
        }

        if ($this->option('debug')) {
            $this->info('Commodity is ' . $commodity . "\n");
        }

        switch ($commodity) {
            case 'dual':
                foreach ($products as $prod) {
                    $prod->rate->rate_subtype = 'N/A';
                    if (
                        $prod->rate->product->rate_type->rate_type === 'tiered'
                        && $prod->rate->rate_amount > 0
                    ) {
                        $prod->rate->rate_subtype = 'tiered';
                    } else {
                        $prod->rate->rate_subtype = 'variable';
                    }
                }

                if (
                    isset($products[1])
                    && $products[0]->rate->product->rate_type_id === $products[1]->rate->product->rate_type_id
                    && $products[0]->rate->rate_subtype === $products[1]->rate->rate_subtype
                ) {
                    // Same Rate Type
                    // send dual contract id
                    $contractsNeeded = 'single';
                    if ($this->option('debug')) {
                        $this->info('contractsNeeded is ' . $contractsNeeded);
                    }
                    $contractRecord = $this->getPdfInfo(
                        'first',
                        $event,
                        $eztpv->brand_id,
                        $this->eztpv->event->products[0]->serviceAddress->address->state->id,
                        $this->eztpv->event->channel_id,
                        $this->eztpv->event->products[0]->market_id,
                        $commodity,
                        $this->eztpv->event->language_id,
                        3,
                        $products[0],
                        $products[0]->rate->product->rate_type_id,
                        $products[0]->rate->rate_amount,
                        $products[0]->rate->intro_rate_amount
                    );

                    if (
                        $this->option('debug')
                        && isset($contractRecord)
                    ) {
                        $this->info('contractRecord (dual+single) is ');
                        $this->line(print_r($contractRecord->toArray(), true));
                    } else {
                        $this->info('contractRecord (dual+single) is NULL' . PHP_EOL);
                    }

                    if (
                        $contractRecord !== null
                    ) {
                        $products[0]->contract_record = $contractRecord;
                        $hasAdditionalContract = true;
                    } else {
                        return [
                            'error',
                            'generateSignaturePageContract contract selection failed to select contract [1]',
                        ];
                    }
                } else {
                    // Multiple Rate Types
                    // send contract_id for each subCommodity
                    $contractsNeeded = 'multi';
                    if ($this->option('debug')) {
                        $this->info('contractsNeeded is ' . $contractsNeeded);
                    }
                    foreach ($products as $product) {
                        switch ($product->event_type_id) {
                            case 1:
                                $subCommodity = 'electric';
                                break;

                            case 2:
                                $subCommodity = 'gas';
                                break;
                        }

                        $contractRecord = $this->getPdfInfo(
                            'first',
                            $event,
                            $eztpv->brand_id,
                            $this->eztpv->event->products[0]->serviceAddress->address->state->id,
                            $this->eztpv->event->channel_id,
                            $this->eztpv->event->products[0]->market_id,
                            $subCommodity,
                            $this->eztpv->event->language_id,
                            3,
                            $product,
                            $product->rate->product->rate_type_id,
                            $product->rate->rate_amount,
                            $product->rate->intro_rate_amount
                        );

                        if (
                            $this->option('debug')
                            && isset($contractRecord)
                        ) {
                            $this->info('contractRecord (multi) is ');
                            $this->line(print_r($contractRecord, true));
                        } else {
                            $this->info('contractRecord (multi) is NULL' . PHP_EOL);
                        }

                        if (
                            $contractRecord !== null
                        ) {
                            $hasAdditionalContract = true;
                            $product->contract_record = $contractRecord;
                        } else {
                            return [
                                'error',
                                'generateSignaturePageContract contract selection failed to select contract [2]',
                            ];
                        }
                    }
                }
                break;

            case 'dualsingles':

                $contractsNeeded = 'multi';
                if ($this->option('debug')) {
                    $this->info('contractsNeeded is ' . $contractsNeeded);
                }
                foreach ($products as $product) {
                    switch ($product->event_type_id) {
                        case 1:
                            $subCommodity = 'electric';
                            break;

                        case 2:
                            $subCommodity = 'gas';
                            break;
                    }

                    $contractRecord = $this->getPdfInfo(
                        'first',
                        $event,
                        $eztpv->brand_id,
                        $this->eztpv->event->products[0]->serviceAddress->address->state->id,
                        $this->eztpv->event->channel_id,
                        $this->eztpv->event->products[0]->market_id,
                        $subCommodity,
                        $this->eztpv->event->language_id,
                        3,
                        $product,
                        $product->rate->product->rate_type_id,
                        $product->rate->rate_amount,
                        $product->rate->intro_rate_amount
                    );

                    if (
                        $this->option('debug')
                        && isset($contractRecord)
                    ) {
                        $this->info('contractRecord (dual singles) is ');
                        $this->line(print_r($contractRecord, true));
                    } else {
                        $this->info('contractRecord (dual singles) is NULL' . PHP_EOL);
                    }

                    if (
                        $contractRecord !== null
                    ) {
                        $hasAdditionalContract = true;
                        $product->contract_record = $contractRecord;
                    } else {
                        return [
                            'error',
                            'generateSignaturePageContract contract selection failed to select contract [4]',
                        ];
                    }
                }
                break;

            default:
                $cccc = true;

                $contractsNeeded = 'single';
                $contractRecord = $this->getPdfInfo(
                    'first',
                    $event,
                    $eztpv->brand_id,
                    $this->eztpv->event->products[0]->serviceAddress->address->state->id,
                    $this->eztpv->event->channel_id,
                    $this->eztpv->event->products[0]->market_id,
                    $commodity,
                    $this->eztpv->event->language_id,
                    3,
                    $products[0],
                    ($cccc ? 1 : $products[0]->rate->product->rate_type_id),
                    ($cccc ? 0 : $products[0]->rate->rate_amount),
                    ($cccc ? 0 : $products[0]->rate->intro_rate_amount)
                );

                if (
                    $this->option('debug')
                    && isset($contractRecord)
                ) {
                    $this->info('contractRecord (single) is ');
                    $this->line(print_r($contractRecord, true));
                } else {
                    $this->info('contractRecord (single) is NULL' . PHP_EOL);
                }

                if (
                    $contractRecord !== null
                ) {
                    $hasAdditionalContract = true;
                    $products[0]->contract_record = $contractRecord;
                } else {
                    return [
                        'error',
                        'generateSignaturePageContract contract selection failed to select contract [3]',
                    ];
                }
                break;
        }

        if ($this->option('debug')) {
            $this->line(print_r(DB::getQueryLog(), true));
        }

        switch ($contractsNeeded) {
            case 'single':
                if (
                    isset($products[0])
                    && isset($products[0]->contract_record)
                    && $products[0]->contract_record->contract_fdf
                ) {
                    if ($this->option('debug')) {
                        $this->info('before generation and filter, data is ' . print_r($data, true));
                    }

                    if (
                        '363ef739-3f2c-4a18-9221-46d46c869eb9' == $event->brand_id
                        || '293c51ca-87de-41c6-bb98-948c7537bc11' == $event->brand_id
                        // atlantic energy
                    ) {
                        // this is required so that dual fuel on a single contract gets both electric and gas products
                        foreach ($data['finalized_products'] as $pro) {
                            foreach ($pro as $pr) {
                                $newProducts[0][] = $pr;
                            }
                        }
                    } else {

                        // 2023-01-11 - Alex K:
                        // Previously $newProducts ended up with one index containing all accounts (products):
                        //     $newProducts[0][0] => 1st account's details.
                        //     $newProducts[0][1] => 2nd account's details.
                        //     $newProducts[0][2] => ... etc.
                        // This would cause the first and second account to be included one contract document,
                        // but all other accounts to be dropped. 
                        // Updated logic so that each dual fuel pair is on it's own index:
                        //     $newProducts[0][0] => 1st account's details.
                        //     $newProducts[0][1] => 2nd account's details.
                        //     $newProducts[1][0] => 3rd account's details.
                        //     $newProducts[1][1] => 4th account's details.
                        //     $newProducts[2][0] => ... etc.
                        // This allows multiple dual-fuel contracts to be create and merged, showing
                        // all accounts.
                        $newProducts = [];

                        // First insert into $newProducts records where 'linked_to' is null
                        // These will be the primary accounts for each dual fuel pair
                        foreach ($data['finalized_products'] as $pro) {
                            if(!$pro[0]['linked_to']) {
                                $newProducts[][0] = $pro[0];
                            }
                        }

                        // Now check the records where 'linked_to' is NOT null, and assign them to the same array as the primary account
                        // by matching the 'linked_to' values against the exist records' 'id' values
                        foreach ($data['finalized_products'] as $pro) {
                            if(!$pro[0]['linked_to']) {
                                continue;
                            }

                            for($i = 0; $i < count($newProducts); $i++) {
                                for($j = 0; $j < count($newProducts[$i]); $j++) {
                                    if($pro[0]['linked_to'] === $newProducts[$i][$j]['id']) {
                                        $newProducts[$i][] = $pro[0];
                                    }
                                }
                            }
                        }
                    }
                    $data['finalized_products'] = $newProducts;

                    if ($this->option('debug')) {
                        $this->info('(3) calling generate_contract_document with ' . print_r(['eztpv' => $eztpv, 'data' => $data, 'contract_id' => $contractRecord->id], true));
                    }

                    $generator = $this->generate_contract_document($eztpv, $data, $contractRecord->id);
                    if (is_array($generator) && $generator[0] === 'error') {
                        return $generator;
                    } else {
                        $filledContracts = $generator;
                    }
                } else {
                    $contract = resource_path('assets/documents/' . $contractRecord->contract_pdf);

                    $this->info('Additional contract 1: ' . $contract);

                    Artisan::call('pdf:merge', [
                        '--output' => $outputFile,
                        'inputFiles' => [$sigPageFile, $contract],
                    ]);
                }
                break;

            case 'multi':
                $tempArray = $data['finalized_products'];
                foreach ($products as $product) {
                    if ($this->option('debug')) {
                        $this->info($product->id . "\n");
                    }

                    if (isset($product->contract_record) && isset($product->contract_record->contract_fdf)) {
                        unset($data['finalized_products']);
                        foreach ($tempArray as $value) {
                            if ($value[0]['id'] === $product->id) {
                                if ($this->option('debug')) {
                                    $this->info('temparr = ' . $value[0]['id'] . "\n");
                                }

                                $subProd = $value[0];
                            }
                        }
                        $data['finalized_products'][0][] = $subProd;

                        if ($this->option('debug')) {
                            $this->info('(2) calling generate_contract_document with ' . print_r(['eztpv' => $eztpv, 'data' => $data, 'contract_id' => $product->contract_record->id], true));
                        }

                        $generator = $this->generate_contract_document($eztpv, $data, $product->contract_record->id);
                        if (is_array($generator) && $generator[0] === 'error') {
                            return $generator;
                        } else {
                            foreach ($generator as $gen) {
                                $filledContracts[] = $gen;
                            }
                        }
                    } else {
                        $contract = resource_path('assets/documents/' . $product->contract_record->contract_pdf);

                        $this->info('Additional contract 2: ' . $contract);

                        Artisan::call('pdf:merge', [
                            '--output' => $outputFile,
                            'inputFiles' => [$sigPageFile, $contract],
                        ]);
                    }
                }

                $data['finalized_products'] = $tempArray;
                break;
        }

        if (isset($filledContracts) && count($filledContracts) > 0) {
            for ($i = 0; $i < count($filledContracts); ++$i) {
                $fileString = $this->s3Download($filledContracts[$i]['file']);
                if (is_array($fileString) && $fileString[0] === 'error') {
                    return $fileString;
                }
                $localFilledFilename = md5($filledContracts[$i]['file']) . '.pdf';
                $contract = public_path('/tmp/' . $localFilledFilename);
                try {
                    file_put_contents($contract, $fileString, 0777);
                } catch (\Exception $e) {
                    return ['error', 'writing contract: ' . $e->getMessage()];
                }
                $this->s3Delete($filledContracts[$i]['file']);
                $delete_contract = true;
                $this->info('Additional contract 3: ' . $contract);

                Artisan::call('pdf:merge', [
                    '--output' => $outputFile,
                    'inputFiles' => [$outputFile, $contract],
                ]);

                $pdf_info[] = $filledContracts[$i]['pdf_info_id'];
            }
        } else {
            $hasAdditionalContract = false;
        }

        $this->info('Output PDF (Merged): ' . $outputFile);

        if ($hasAdditionalContract == false) {
            $pdf_info[] = 'none';
            rename($sigPageFile, $outputFile);
        }

        $dir = 'uploads/pdfs/' . $eztpv->brand_id . '/'
            . $this->eztpv->event->vendor_id . '/' . date('Y-m-d');
        $s3filename = md5($sigPageFile . mt_rand(1, 99999) . '-output.pdf') . '.pdf';
        $keyname = $dir . '/' . $s3filename;

        $s3 = $this->s3Upload($keyname, $outputFile);

        // Store the last contract
        $this->contract_data = ['file' => $keyname, 'content' => file_get_contents($outputFile)];

        if (
            is_array($s3)
            && 'error' == $s3[0]
        ) {
            $this->error('Error uploading file to s3: ' . $s3[1]);

            return $s3;
        } elseif (
            is_array($s3)
            && 'success' == $s3[0]
        ) {
            $this->info('Deleting temp sigpage');
            $this->unlinkFile(@$sigPageFile);
            $this->info('Deleting temp sigpage-output');
            $this->unlinkFile(@$sigPageFile . '-output.pdf');

            if ($delete_contract) {
                $this->info('Deleting temp contract');
                $this->unlinkFile(@$contract);
            }

            $filenames[] = [
                'file' => $keyname,
                'pdf_info_id' => $pdf_info,
            ];

            $this->info('Success! ' . config('services.aws.cloudfront.domain') . '/' . $keyname);

            return $filenames;
        } else {
            return [
                'error',
                's3 returned unexpected result',
            ];
        }
    }

    private function clearEDS($eztpv_id)
    {
        $this->logInfo('Retrieving eztpv_documents...', __METHOD__, __LINE__);

        $eds = EztpvDocument::where(
            'eztpv_id',
            $eztpv_id
        )->join(
            'uploads',
            'eztpv_documents.uploads_id',
            'uploads.id'
        )->where(
            'uploads.upload_type_id',
            3
        )->get();

        if (isset($eds) && count($eds) > 0) {
            $this->logInfo('Documents found, deleting from S3...', __METHOD__, __LINE__);

            foreach ($eds as $doc) {
                $upload = Upload::find($doc->uploads_id);
                if ($upload) {
                    // delete from s3
                    $this->s3Delete($upload->filename);

                    $upload->delete();
                }

                if ($doc) {
                    $doc->delete();
                }
            }
        }

        return;
    }

    private function setGlobal($ezptv_id)
    {
        unset($this->eztpv);
        $this->eztpv = Eztpv::where(
            'id',
            $ezptv_id
        )
            ->with([
                'event.products.serviceAddress',
                'event.vendor',
                'signature_customer',
                'signature_agent',
            ])
            ->first();

        return;
    }

    private function unlinkFile($file)
    {
        if (
            isset($file)
            && file_exists($file)
        ) {
            $ret = unlink($file);
            if ($ret === false) {
                $this->info('[UNLINK] cannot remove ' . $file);
            }
        } else {
            if (isset($file)) {
                $this->info('[UNLINK] ' . $file . ' does not exist');
            } else {
                $this->info('[UNLINK] not given file');
            }
        }
    }

    private function getSignatureDimensions($file)
    {
        $this->logInfo('Getting signature dimensions', __METHOD__, __LINE__);

        if (
            isset($file)
            && is_file($file)
        ) {
            try {
                $dims = getimagesize($file);
            } catch (\Exception $e) {
                $this->logError('Exception: ' . $e->getMessage(), __METHOD__, __LINE__);
                return [
                    'error',
                    'getSignatureDimensions threw an exception getting image dimensions',
                ];
            }

            if (is_array($dims)) {
                if ($dims[1] > $dims[0]) {
                    $measure = 'tall';
                } else {
                    $measure = 'wide';
                }
                $this->logInfo('$measure: ' . $measure, __METHOD__, __LINE__);

                return $measure;
            }
        }

        $this->logInfo('unable to retrieve dimensions for a signature file.', __METHOD__, __LINE__);

        return [
            'error',
            'getSignatureDimensions was unable to retrieve dimensions for a signature file',
        ];
    }

    private function resizeImageByHeight($file, $targetHeightInPts)
    {
        if (
            isset($file)
            && is_file($file)
        ) {
            $targetHeightInPx = intval($targetHeightInPts * 1.33);
            try {
                $image = Image::make($file)->heighten($targetHeightInPx, function ($constraint) {
                    $constraint->upsize();
                });
                $image->save();
            } catch (\Exception $e) {
                return [
                    'error',
                    'resizeImageByHeight general error',
                ];
            }
            // }
        } else {
            return [
                'error',
                'resizeImageByHeight: invalid file passed',
            ];
        }

        return;
    }

    private function resizeImageByWidth($file, $targetWidthInPts)
    {
        if (
            isset($file)
            && is_file($file)
        ) {
            $targetWidthInPx = intval($targetWidthInPts * 1.33);
            try {
                $image = Image::make($file)->heighten($targetWidthInPx, function ($constraint) {
                    $constraint->upsize();
                });
            } catch (\Exception $e) {
                return [
                    'error',
                    'resizeImageByWidth general error',
                ];
            }
        } else {
            return [
                'error',
                'resizeImageByWidth: invalid file passed',
            ];
        }

        return;
    }

    /**
     * Retrieves SQL string from Eloquent ORM with tokens replaced by the bound values, and logs it to the Laravel log file.
     */
    private function logSqlStr($message, $ormQuery, $method, $line) {        

        if(!$message || !$ormQuery) {
            return;
        }

        // Get the Sql str from ORM
        $queryStr = str_replace(array('?'), array('\'%s\''), $ormQuery->toSql());

        // Replace tokens with the bound values.
        $queryStr = vsprintf($queryStr, $ormQuery->getBindings());

        $this->info($queryStr);
        $this->logInfo($message, $method, $line, ['query' => $queryStr]);
    }

    /**
     * Writes Info message to Laravel log, in a formatted matter
     */
    private function logInfo($message, $method, $line, $data = null) {
        // Create confirmation code string to append to log, if one was provided
        $confCodeStr = ($this->option('confirmation_code') ? ($this->option('confirmation_code') . ' -- ') : '');

        if($data) {
            Log::info($method . '(), Line: ' . $line . ' -- ' . $confCodeStr . $message, [$data]);
        } else {
            Log::info($method . '(), Line: ' . $line . ' -- ' . $confCodeStr . $message);
        }
    }

    /**
     * Writes Debug message to Laravel log, in a formatted matter
     */
    private function logDebug($message, $method, $line, $data = null) {
        // Create confirmation code string to append to log, if one was provided
        $confCodeStr = ($this->option('confirmation_code') ? ($this->option('confirmation_code') . ' -- ') : '');

        if($data) {
            Log::debug($method . '(), Line: ' . $line . ' -- ' . $confCodeStr . $message, [$data]);
        } else {
            Log::debug($method . '(), Line: ' . $line . ' -- ' . $confCodeStr . $message);
        }
    }
    
    /**
     * Writes Error message to Laravel log, in a formatted matter
     */
    private function logError($message, $method, $line, $data = null) {
        // Create confirmation code string to append to log, if one was provided
        $confCodeStr = ($this->option('confirmation_code') ? ($this->option('confirmation_code') . ' -- ') : '');

        if($data) {
            Log::error($method . '(), Line: ' . $line . ' -- ' . $confCodeStr . $message, [$data]);
        } else {
            Log::error($method . '(), Line: ' . $line . ' -- ' . $confCodeStr . $message);
        }
    }

    /**
     * Retrieves and email address by brand name from the brand emails constant.
     * Returns the default email address if the key does not exist.
     */
    private function getEmail($brand_name) {

        // Return default address if brand name is not is a keey in the emails array
        if(!in_array($brand_name, array_keys(self::BRAND_EMAILS))) {
            return 'no-reply@tpvhub.com';
        }
        
        return self::BRAND_EMAILS[$brand_name];
    }


    // Refers to Brand Service 'copy_email_contracts_to_acct_manager', BRAND_COPY_EMAILS array in this file are Distribution Email Lists that forward to Acct Managers
    private function getEmailCopyDistroByBrand($brand_id) {
        // If Email override is used in command line argument, --override-brand-copy-email=email@domain.com
        if ($this->option('override-brand-copy-email')) {
            $this->info("using value from --override-brand-copy-email " . $this->option('override-brand-copy-email'));
            return $this->option('override-brand-copy-email');
        }

        $brand_key = null;

        foreach (self::BRAND_IDS as $k => $v) {
            if (in_array($brand_id, self::BRAND_IDS[$k])) {
                $brand_key = $k;
                break;
            }
        }

        if ($brand_key && array_key_exists($brand_key, self::BRAND_COPY_EMAILS)) {
            return self::BRAND_COPY_EMAILS[$brand_key];
        }

        return null;
    }

    /**
     *  Brand Services Has Service
     * - returns true if brand_service_types matches column and expected value
     * Example $column 'slug' and $value 'copy_email_contracts_to_acct_manager'
     */
    private function brandServicesHasService($brand_id, string $column, string $value) : bool {
        // Try is used to check if column does not exist in the result set
        try {
            if ($this->brand_service_types === null) {
                $brand_services = BrandService::select('brand_service_types.*')
                    // Join on Brand Service Types for the slug column, use function to join on ID and joined table is not null to query
                    ->join('brand_service_types', function($jsq) {
                        // This adds "JOIN t1 ON t1.id = t2.t1_id AND t2.deleted_at IS NULL
                        $jsq->on('brand_services.brand_service_type_id','brand_service_types.id')
                            ->whereNull('brand_service_types.deleted_at');
                        })
                    ->where('brand_services.brand_id', $brand_id);

                //echo "\n\n" . $brand_services->toSql() . "\n\n";

                // Store results on Instance so we only have to load the data one time for performance
                $this->brand_service_types = $brand_services->get();
            }

            foreach($this->brand_service_types as $bs) {
                if ($bs->$column == $value) { return true; }
            }
        }
        catch(\Exception $e) {
            $this->info("GenerateEzTpvContracts->brandServicesHasService() Error: Line: " . $e->getLine() . ' ' . $e->getMessage());
            $this->logInfo('GenerateEzTpvContracts->brandServicesHasService() Error', $e->getLine(), $e->getMessage());
        }

        return false;
    }

    // Enable this by Brand Service AND BRAND_COPY_EMAILS Array at top of this file
    private function sendEmailCopyToAccountManagers($event, $eztpv, $file_names) {
        try {
            // If Brand does not have 'Copy Email Contracts to Account Manager' Brand Service, exit this function
            if (!$this->brandServicesHasService($eztpv->brand_id, 'slug', 'copy_email_contracts_to_acct_manager')) { 
                $this->logInfo('Generate Contracts brandServicesHasService copy_email_contracts_to_acct_manager Service Not Enabled, not sending copy', null, null);
                return;
            }

            $email_to = $this->getEmailCopyDistroByBrand($eztpv->brand_id);

            // This should go on the instance of this class (IE when we run it from the command line)
            $brand = Brand::where('id', $this->eztpv->brand_id)->first();            

            if (!$email_to) {
                $this->error('sendEmailCopyToAccountManagers ERROR: Email not defined for Brand ' . $brand->name);
                $this->logInfo('GenerateEzTpvContracts->sendEmailCopyToAccountManagers ERROR: Email not defined for Brand ' . $brand->name, null, null);
                return;
            }

            // Using this for testing since I cant get passed Contract Generation DocX to PFF
            $sp = StatsProduct::select('*')
                ->where('confirmation_code', $event->confirmation_code)
                ->where('eztpv_id', $eztpv->id)
                ->first();

            // Need customer email, confirmation code, name, BTN $products[0]['auth_first_name']
            $subject = "Contract for " .
                $brand->name . " " .
                $sp->bill_first_name . " " . $sp->bill_last_name . " " .
                $sp->confirmation_code . " " .
                $sp->btn . " " .
                $sp->email_address;

            // Check if we have the file contents of the most recently generated contract on the instance.  Necessary as files are immediately deleted, and iterated
            if (!$this->contract_data) {
                $this->error('GenerateEzTpvContracts ERROR in getEmailCopyDistroByBrand, NO CONTRACT DATA');
                $this->logInfo('GenerateEzTpvContracts ERROR in getEmailCopyDistroByBrand, NO CONTRACT DATA', null, null);
                return;
            }

            // Using path of tmp/<someguid>.pdf
            $parts = pathinfo($this->contract_data['file']);

            // $temp_file = public_path('tmp/' . $this->contract_data['file']);
            $temp_file = public_path('tmp/' . $parts['basename']);

            $contract_has_data = ($this->contract_data['content']) ? 'true' : 'false';
            $this->logInfo("GenerateEzTpvContracts->sendEmailCopyToAccountManagers() \$temp_file: '$temp_file', \$contract_has_data: '$contract_has_data' ", null, null);

            // Write a Temporary File with the same Key
            file_put_contents($temp_file, $this->contract_data['content']);

            $email_config = [
                'from' => 'no-reply@tpvhub.com',
                'to' => [$email_to],
                'subject' => $subject,
                'body' => "Copy of Email to Customer with Contract Attachment",
                'attachments' => [$temp_file]
            ];
            
            $this->sendGenericEmail($email_config);

            // Remove the Temp File after the Contract has been emailed and remove the data from instance of this class 
            // Removing data from $this->contract_data is intentional, prevents emailing wrong contracts where 2 contracts
            // are generated but only one contract is created. IE dual fuel where only Electric contract is created
            unlink($temp_file);
            $this->contract_data = null;

            $this->info("Email Copy sent to $email_to");

            $new_interaction = new Interaction();
            $new_interaction->created_at = Carbon::now('America/Chicago');
            $new_interaction->event_id = $event->id;
            $new_interaction->interaction_type_id = 29;
            $new_interaction->save();
        }
        catch(\Exception $e) {
            $this->error("sendEmailCopyToAccountManagers() Error: Line: " . $e->getLine() . ' ' . $e->getMessage());
            $this->logInfo('GenerateEzTpvContracts->sendEmailCopyToAccountManagers() Error: Line: ', $e->getLine(), $e->getMessage());
        }
    }

}
