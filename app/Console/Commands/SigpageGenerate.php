<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Traits\ContractTrait;
use App\Models\Upload;
use App\Models\PhoneNumber;

use App\Models\EztpvDocument;
use App\Models\Eztpv;
use App\Models\Event;
use App\Models\EmailAddress;
use App\Models\BrandUser;
use App\Models\Brand;

class SigpageGenerate extends Command
{
    use ContractTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sigpage:generate
                                {--debug}
                                {--hoursAgo=}
                                {--confirmationCode=}
                                {--noDelivery}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Signature Page Contracts';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function getCustomerEmail($event_id)
    {
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
        if ($eal) {
            return $eal->email_address;
        }

        return null;
    }

    private function getCustomerPhone($event_id)
    {
        $pnl = PhoneNumber::select(
            'phone_numbers.phone_number'
        )->leftJoin(
            'phone_number_lookup',
            'phone_numbers.id',
            'phone_number_lookup.phone_number_id'
        )->where(
            'type_id',
            $event_id
        )->where(
            'phone_number_type_id',
            3
        )->whereNull(
            'phone_numbers.deleted_at'
        )->whereNull(
            'phone_number_lookup.deleted_at'
        )->first();
        if ($pnl) {
            return $pnl->phone_number;
        }

        return null;
    }

    private function sendText($event_id)
    {
        if (!$this->option('noDelivery')) {
            $event = Event::find($event_id);
            if ($event) {
                $language = ($event->language_id === 2)
                    ? 'spanish'
                    : 'english';
                $brand_name = Brand::find($event->brand_id);
                if (!$brand_name) {
                    return false;
                }

                $client = new TwilioClient(
                    config('services.twilio.account'),
                    config('services.twilio.auth_token')
                );

                $phone_number = $this->getCustomerPhone($event_id);
                if ($phone_number) {
                    $to = (0 !== strpos($phone_number, '+1'))
                        ? '+1' . preg_replace('/\D/', '', $phone_number)
                        : $phone_number;

                    // twilio lookup to validate phone numbers before attempting to text them
                    // when we start doing more international business, this will need to check country
                    // currently works only for US and Canada
                    try {
                        $lookup = $client->lookups->v1->phoneNumbers($to)->fetch(
                            array('countryCode' => 'US')
                        );
                    } catch (RestException $e) {
                        Log::debug(
                            'Twilio hit a RestException ('
                                . $e . ') Attempting to skip it and send anyway.'
                        );
                    } catch (TwilioException $e) {
                        Log::error(
                            'Could not send SMS notification.' .
                                ' error: ' . $e
                        );

                        return false;
                    }

                    $url = CreateShortURI(config('app.urls.clients') . '/d/' . $event->eztpv_id, 3);
                    $message = $url . ' Click the link above to download your attachments from '
                        . $brand_name->name . ' and TPV.com.  Reply STOP to unsubscribe.';

                    try {
                        $ret = SendSMS($to, config('services.twilio.default_number'), $message, null, $event->brand_id, 5);
                        if (strpos($ret, 'ERROR') !== false) {
                            Log::error('Could not send SMS notification. ' . $ret);
                        }
                    } catch (TwilioException $e2) {
                        Log::error(
                            'Could not send SMS notification.' .
                                ' error: ' . $e2
                        );

                        return false;
                    }

                    return true;
                }
            }
        } else {
            info('-- Skipped delivery due to --noDelivery flag.');
            return true;
        }

        return false;
    }

    private function sendEmail($event_id)
    {
        if (!$this->option('noDelivery')) {
            $event = Event::find($event_id);
            if ($event) {
                $language = ($event->language_id === 2)
                    ? 'spanish'
                    : 'english';
                $brand_name = Brand::find($event->brand_id);
                if (!$brand_name) {
                    return;
                }

                $subject = ($language === 'spanish')
                    ? 'Documentos de inscripción importantes de ' . $brand_name->name . ' de TPV.com'
                    : 'Important ' . $brand_name->name . ' Enrollment Documents from TPV.com';

                $email_data = array(
                    'company' => $brand_name->name,
                    'url' => config('app.urls.clients') . '/d/' . $event->eztpv_id,
                    'language' => $language,
                );

                $customer_email = $this->getCustomerEmail($event->id);
                if (
                    isset($customer_email)
                    && strlen(trim($customer_email)) > 0
                    && filter_var(trim($customer_email), FILTER_VALIDATE_EMAIL)
                ) {
                    try {
                        $this->info(' -- Sending contract via Email to ' . $customer_email);
                        Mail::send(
                            'emails.eztpvSendContractToCustomer',
                            $email_data,
                            function ($message) use ($subject, $customer_email) {
                                $message->subject($subject);
                                $message->from('no-reply@tpvhub.com');
                                $message->to(trim($customer_email));
                            }
                        );
                    } catch (\Exception $e) {
                        unset($contactError);
                        Log::error(
                            'Could not send email notification.' .
                                ' error: ' . $e
                        );

                        return false;
                    }

                    return true;
                }
            }
        } else {
            return true;
        }

        return false;
    }

    public function getUserId($brand_user_id)
    {
        $bu = BrandUser::withTrashed()->find($brand_user_id);
        if ($bu) {
            return $bu->user_id;
        }

        return null;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $sales = Eztpv::select(
            'eztpvs.id',
            'events.id as event_id',
            'events.brand_id',
            'brands.name AS brand_name',
            'events.confirmation_code',
            'events.sales_agent_id',
            'eztpvs.eztpv_contract_delivery'
        )->join(
            'events',
            'eztpvs.id',
            'events.eztpv_id'
        )->join(
            'interactions',
            'events.id',
            'interactions.event_id'
        )->leftJoin(
            'brands',
            'events.brand_id',
            'brands.id'
        )->where(
            'interactions.event_result_id',
            1
        )->where(
            'eztpvs.contract_type',
            3
        );

        if ($this->option('confirmationCode')) {
            $this->info('Using Confirmation Code: ' . $this->option('confirmationCode'));
            $sales = $sales->where(
                'events.confirmation_code',
                $this->option('confirmationCode')
            );
        } else {
            if ($this->option('hoursAgo')) {
                $sales = $sales->where(
                    'eztpvs.processed',
                    0
                )->where(
                    'eztpvs.created_at',
                    '>=',
                    Carbon::now()->subHours($this->option('hoursAgo'))
                );
            } else {
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

        $sales = $sales->orderBy('eztpvs.created_at')->get();

        foreach ($sales as $sale) {
            $this->info("Starting contract " . $sale->confirmation_code
                . " (" . $sale->brand_name . ")");

            $task = $this->generateContract($sale->confirmation_code);
            if ($task['error']) {
                $this->info(' -- Error for ' . $sale->confirmation_code .
                    ' : ' . $task['message'] . "\n");
                $eztpv = Eztpv::find($sale->id);
                $eztpv->processed = 3;
                $eztpv->save();
            } else {
                $this->info(' -- starting delivery check...');

                switch ($sale->eztpv_contract_delivery) {
                    case 'email':
                        $this->sendEmail($sale->event_id);
                        break;
                    case 'text':
                        $this->sendText($sale->event_id);
                        break;
                }

                $upload = new Upload();
                $upload->brand_id = $sale->brand_id;
                $upload->user_id = $this->getUserId($sale->sales_agent_id);
                $upload->upload_type_id = 3;
                $upload->filename = $task['file'];
                $upload->save();

                if ($upload) {
                    EztpvDocument::where(
                        'eztpv_documents.eztpv_id',
                        $sale->id
                    )->join(
                        'uploads',
                        'eztpv_documents.uploads_id',
                        'uploads.id'
                    )->where(
                        'eztpv_documents.event_id',
                        $sale->event_id
                    )->where(
                        'uploads.upload_type_id',
                        3
                    )->delete();

                    $document = new EzTpvDocument();
                    $document->eztpv_id = $sale->id;
                    $document->event_id = $sale->event_id;
                    $document->sales_agent_id = $this->getUserId($sale->sales_agent_id);
                    $document->uploads_id = $upload->id;
                    $document->save();

                    if ($document) {
                        $eztpv = Eztpv::find($sale->id);
                        $eztpv->processed = 1;
                        $eztpv->save();
                    }
                }
            }
        }
    }
}
