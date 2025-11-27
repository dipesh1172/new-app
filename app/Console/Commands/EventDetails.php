<?php

namespace App\Console\Commands;

use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Exception;
use App\Models\UtilityAccountType;
use App\Models\Script;
use App\Models\Office;
use App\Models\EztpvDocument;
use App\Models\Eztpv;
use App\Models\EventNote;
use App\Models\EventAlert;
use App\Models\Event;
use App\Models\Disposition;
use App\Models\CustomFieldStorage;
use App\Models\BrandUser;
use App\Models\AuthRelationship;

class EventDetails extends Command
{
    private $_client;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:info {confirmation_code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gather and present information on the requested event';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_client = new TwilioClient(
            config('services.twilio.account'),
            config('services.twilio.auth_token')
        );
    }

    private function getIdents($idents)
    {
        $out = [];
        foreach ($idents as $ident) {
            $identifier = $ident->identifier;
            $actype = UtilityAccountType::find($ident->utility_account_type_id);
            $type = '?';
            if ($actype) {
                $type = $actype->account_type;
            }
            $out[] = $type . ': ' . $identifier;
        }

        return implode("\n", $out);
    }

    private function getCustomQForProduct($event_id, $product_id)
    {
        $out = [];
        $answers = CustomFieldStorage::select('custom_fields.output_name', 'custom_field_storages.value')
            ->join('custom_fields', 'custom_fields.id', 'custom_field_storages.custom_field_id')
            ->join('events', 'events.id', 'custom_field_storages.event_id')
            ->where('events.id', $event_id)
            ->where('custom_field_storages.product_id', $product_id)
            ->get();
        foreach ($answers as $answer) {
            $out[] = '[' . $answer->output_name . '] ' . ($answer->value != null ? $answer->value : '[null]');
        }

        return implode("\n", $out);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $confCode = $this->argument('confirmation_code');
        $event = Event::where('confirmation_code', $confCode)->withTrashed()->first();
        if (null === $event) {
            $this->error('The Event for the Confirmation Code (' . $confCode . ') was not found!');

            return;
        }

        $salesAgent = BrandUser::where('id', $event->sales_agent_id)->withTrashed()->first();
        $script = Script::where('id', $event->script_id)->withTrashed()->first();
        $digitalScript = Script::where('id', $event->digital_script_id)->withTrashed()->first();
        $isEztpv = null !== $event->eztpv_id ? 'Yes' : 'No';
        $eztpv = null;
        if ($isEztpv === 'Yes') {
            $eztpv = Eztpv::where('id', $event->eztpv_id)->withTrashed()->first();
        }
        if ($event->trashed()) {
            $this->warn('Event was deleted ' . $event->deleted_at);
        }
        $this->info('Event ID: ' . $event->id);
        $this->info('Event Date: ' . $event->created_at);
        $this->info('External ID: ' . $event->external_id);
        $this->info('EZTPV: ' . $isEztpv);
        $this->info(
            'Brand: ' . $event->brand->name . ' ' . ($event->brand->trashed()
                ? '(Inactive)'
                : '(Active)')
        );
        $this->info('Channel: ' . $event->channel->channel);

        $this->info('Lead Used: ' . ($event->lead_id != null ? 'Yes (' . $event->lead_id . ')' : 'No'));
        if (!empty($script)) {
            $scriptActive = $script->trashed() ? ' (Inactive)' : ' (Active)';
            $this->info('Script: ' . $script->title . $scriptActive . ' ' . $script->id);
        }
        if (!empty($digitalScript)) {
            $scriptActive = $digitalScript->trashed() ? ' (Inactive)' : ' (Active)';
            $this->info('Digital Script: ' . $digitalScript->title . $scriptActive . ' ' . $digitalScript->id);
        }

        if ($event->vendor_id !== null) {
            $this->info('Vendor: ' . $event->vendor->name . ' ' . ($event->vendor->trashed() ? '(Inactive)' : '(Active)'));
        }
        if ($event->office_id !== null) {
            $office = Office::where('id', $event->office_id)->withTrashed()->first();
            if ($office) {
                $this->info('Office: ' . $office->name . ' ' . ($office->trashed() ? '(Inactive)' : '(Active)'));
            } else {
                $this->warn('Unable to determine office name: ' . $event->office_id);
            }
        }

        switch ($event->language_id) {
            case 1:
                $this->info('Language: English');
                break;
            case 2:
                $this->info('Language: Spanish');
                break;
            default:
                $this->info('Language: <Not Set>');
                break;
        }


        if (null !== $salesAgent) {
            $this->info(
                'Sales Agent: [' . $salesAgent->tsr_id . '] '
                    . $salesAgent->user->first_name . ' ' . $salesAgent->user->last_name . ($salesAgent->trashed() ? ' (Inactive)' : ' (Active)')
            );
        } else {
            $this->error('No Sales Agent found');
        }

        if (isset($event->phone) && isset($event->phone->phone_number->phone_number)) {
            $this->info('BTN: ' . $event->phone->phone_number->phone_number);
        }

        if (!empty($event->gps_coords)) {
            $this->info('GPS: ' . $event->gps_coords);
        }

        if (!empty($event->ip_addr) && $event->ip_addr !== '0.0.0.0') {
            $this->info('IP Address: ' . ($event->ip_addr));
        } else {
            if (!empty($eztpv) && !empty($eztpv->ip_addr) && $eztpv->ip_addr !== '0.0.0.0') {
                $this->info('IP Address: ' . $eztpv->ip_addr);
            }
        }

        if (!empty($event->hrtpv)) {
            $this->info('Event Type: HRTPV');
        }

        if (!empty($event->agent_confirmation)) {
            $this->info('Event Type: Agent Confirmation');
        }

        if (!empty($event->sa_distance)) {
            $this->info('Sales Agent Distance: ' . $event->sa_distance . ' ft');
        }

        $this->line('');

        if (!empty($event->distances)) {
            $distances = [];
            $distanceHeaders = [
                'Created',
                'Type',
                'PointA',
                'PointB',
                'Distance (ft)',
            ];
            $dtypeLookup = function ($type) {
                $d = DB::table('gps_distance_types')->where('id', $type)->first();
                if ($d) {
                    return $d->distance_type;
                }
                return 'Unknown type';
            };
            foreach ($event->distances as $distance) {
                if (
                    isset($distance->pointA)
                    && isset($distance->pointA->coords)
                    && isset($distance->pointB)
                    && isset($distance->pointB->coords)
                ) {
                    $distances[] = [
                        $distance->created_at,
                        $dtypeLookup($distance->distance_type_id),
                        $distance->pointA->coords,
                        $distance->pointB->coords,
                        $distance->distance
                    ];
                }
            }
            if (!empty($distances)) {
                $this->info('Recorded Distances:');
                $this->table($distanceHeaders, $distances);
                $this->line('');
            }
        }

        $raw_products = $event->products;

        $products = [];
        $product_headers = [
            'Fuel Type',
            'Market',
            'Bill Name',
            'Auth Name',
            'Relationship',
            'State',
            'Utility',
            'Product',
            'Program Code',
            'Rate ID',
            'Product ID',
            'Identifiers',
            'Custom Questions'
        ];
        foreach ($raw_products as $rp) {
            $service_address = $rp->serviceAddress;
            $products[] = [
                $rp->event_type->event_type,
                1 === $rp->market_id ? 'Residential' : 'Commercial',
                2 === $rp->market_id
                    ? $rp->company_name
                    : $rp->bill_first_name . ' ' . $rp->bill_middle_name . ' ' . $rp->bill_last_name,
                $rp->auth_first_name . ' ' . $rp->auth_middle_name . ' ' . $rp->auth_last_name,
                is_numeric($rp->auth_relationship)
                    ? AuthRelationship::find($rp->auth_relationship)->relationship
                    : $rp->auth_relationship,
                @$service_address->address->state_province,
                $rp->rate->utility->utility->name,
                $rp->rate->product->name,
                $rp->rate->program_code,
                $rp->rate->id,
                $rp->rate->product->id,
                $this->getIdents($rp->identifiers),
                $this->getCustomQForProduct($event->id, $rp->id),
            ];
        }

        if (!empty($products)) {
            $this->info('Products:');
            $this->table($product_headers, $products);
        } else {
            $this->error('No Products Found');
        }

        $this->line('');

        $customQuestionAnswers = CustomFieldStorage::select(
            'custom_fields.output_name',
            'custom_field_storages.value'
        )->join(
            'custom_fields',
            'custom_fields.id',
            'custom_field_storages.custom_field_id'
        )->join(
            'events',
            'events.id',
            'custom_field_storages.event_id'
        )->whereNull(
            'custom_field_storages.product_id'
        )->where(
            'events.id',
            $event->id
        )->get()->map(function ($item) {
            if ($item->value == null) {
                $item->value = '[null]';
            }
            return $item;
        });

        if ($customQuestionAnswers->count() > 0) {
            $this->info('Custom Questions (Event):');
            $this->table(['Question Name', 'Value'], $customQuestionAnswers);
        }

        $this->line('');

        $raw_interactions = $event->interactions;
        $interactions = [];
        $interaction_headers = [
            'Created At',
            'Duration',
            'Agent',
            'Type',
            'Source',
            'Result',
            'Disposition',
            'Recordings'
        ];
        foreach ($raw_interactions as $ri) {
            $recordings = 'unknown recording(s) present';
            if (null !== $ri->session_call_id && !starts_with($ri->session_call_id, 'DEV')) {
                try {
                    $call = $this->_client->calls($ri->session_call_id)->fetch();
                    $rrecordings = $call->recordings->read();
                    $recordings = count($rrecordings) . ' recording(s) present';
                } catch (Exception $e) {
                    info('Error: ' . $e);
                }
            } else {
                $call = null;
            }

            switch ($ri->event_result_id) {
                case 1:
                    $event_result = 'Good Sale';
                    break;
                case 2:
                    $event_result = 'No Sale';
                    break;
                default:
                    $event_result = 'Closed';
                    break;
            }

            $disposition = null;
            if (null !== $ri->disposition_id) {
                $d = Disposition::find($ri->disposition_id);
                if ($d) {
                    $disposition = $d->reason;
                }
            }
            if (empty($disposition)) {
                $disposition = '[null]';
            }

            $tpv_agent = $ri->tpv_agent;
            $agent_name = null;
            if ($tpv_agent) {
                $agent_name = $tpv_agent->first_name . ' ' . $tpv_agent->last_name;
            }

            $interactions[] = [
                $ri->created_at,
                $ri->interaction_time,
                $agent_name,
                $ri->interaction_type->name,
                $ri->event_source_id != null ? $ri->source->source : '[null]',
                $event_result,
                $disposition,
                null !== $call ? $recordings : 'No Recordings',
            ];
        }

        if (!empty($interactions)) {
            $this->info('Interactions:');
            $this->table($interaction_headers, $interactions);
        } else {
            $this->warn('No Interactions Found');
        }

        $this->line('');

        $rawalerts = EventAlert::where('event_id', $event->id)->with(['client_alert'])->orderBy('created_at', 'ASC')->get();
        $alerts = [];
        $alertHeaders = [
            'Created',
            'Category',
            'Alert',
            'Data',
        ];
        if (!empty($rawalerts)) {
            foreach ($rawalerts as $alert) {
                $count = 0;
                if (isset($alert->data['conflicts'])) {
                    $count = count($alert->data['conflicts']);
                }
                $cat = null;
                switch ($alert->client_alert->category_id) {
                    case 1:
                        $cat = 'Call Start';
                        break;
                    case 2:
                        $cat = 'Cust Info';
                        break;
                    case 3:
                        $cat = 'Acct Info';
                        break;
                    case 4:
                        $cat = 'Call End';
                        break;
                    case 5:
                        $cat = 'Standalone';
                        break;
                    default:
                        $cat = 'Unknown';
                }
                $alerts[] = [
                    $alert->created_at,
                    $cat,
                    $alert->client_alert->title,
                    'Conflicts: ' . $count
                ];
            }
            if (!empty($alerts)) {
                $this->info('Event Alerts:');
                $this->table($alertHeaders, $alerts);
            } else {
                $this->info('Event Alerts: none');
            }
        } else {
            $this->info('Event Alerts: none');
        }

        $this->line('');
        $rawEventNotes = EventNote::where('event_id', $event->id)->with(['tpvStaff'])->orderBy('created_at', 'ASC')->get();
        $notes = [];
        $noteHeaders = [
            'Created',
            'Note',
            'Staff',
            'Internal Only?',
        ];
        if (!empty($rawEventNotes)) {
            foreach ($rawEventNotes as $note) {
                $notes[] = [$note->created_at, $note->notes, $note->tpvStaff->first_name . ' ' . $note->tpvStaff->last_name . ' (' . $note->tpvStaff->username . ')', $note->internal_only ? 'Yes' : 'No'];
            }
        }
        if (!empty($notes)) {
            $this->info('Event Notes:');
            $this->table($noteHeaders, $notes);
        } else {
            $this->info('Event Notes: none');
        }

        $this->line('');

        $rawAttachments = EztpvDocument::where('event_id', $event->id)->with(['uploads', 'uploads.type'])->orderBy('created_at', 'ASC')->get();
        $attachments = [];
        $attHeaders = [
            'Created',
            'Type',
            'URI',
        ];
        if (!empty($rawAttachments)) {
            foreach ($rawAttachments as $att) {
                $attachments[] = [$att->created_at, (!empty($att->uploads) ? ($att->uploads->type->upload_type . ($att->uploads->trashed() ? ' (deleted)' : '')) : 'Unknown'), (!empty($att->uploads) ? config('services.aws.cloudfront.domain') . '/' . $att->uploads->filename : '[null]')];
            }
        }
        if (!empty($attachments)) {
            $this->info('Attachments:');
            $this->table($attHeaders, $attachments);
        } else {
            $this->info('Attachments: none');
        }
    }
}
