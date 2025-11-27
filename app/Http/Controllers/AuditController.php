<?php

namespace App\Http\Controllers;

set_time_limit(60);

use App\Http\Controllers\Controller;

use App\Models\Audit;
use App\Models\BrandUser;
use App\Models\Event;
use App\Models\EztpvConfig;
use App\Models\Interaction;
use App\Models\Office;
use App\Models\User;
use App\Traits\SearchFormTrait;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use SearchFormTrait;

    public static function index()
    { }

    public static function listAudits()
    {
        return Audit::paginate();
    }

    private function lookupAudit($auditable_id)
    {
        $audits = Audit::select(
            'audits.created_at',
            'audits.event',
            'audits.old_values',
            'audits.new_values',
            'users.first_name',
            'users.last_name'
        )->leftJoin(
            'users',
            'audits.user_id',
            'users.id'
        )->where(
            'audits.auditable_id',
            $auditable_id
        )->orderBy('audits.created_at')->get();

        return $audits;
    }

    public static function lookup(Request $request)
    {
        return view(
            'audits.lookup',
            [
                'confirmation_code' => $request->confirmation_code,
            ]
        );
    }

    public function lookupData($id)
    {
        $ezconfig_audits = null;
        $event = Event::where(
            'confirmation_code',
            $id
        )->with(
            [
                'brand',
                'eztpv',
                'script',
                'vendor',
                'channel',
                'office',
                'sales_agent',
                'language'
            ]
        )->first();
        if ($event) {
            $eaudits = $this->lookupAudit($event->id);
            if ($event->office_id) {
                $office = Office::find($event->office_id);
                if ($office) {
                    $ezconfig = EztpvConfig::where(
                        'office_id',
                        $office->id
                    )->first();
                    if ($ezconfig) {
                        $ezconfig_audits = $this->lookupAudit($ezconfig->id);
                    }
                }
            }

            $interaction_audits = [];
            $interactions = Interaction::where(
                'event_id',
                $event->id
            )->with(
                [
                    'interaction_type',
                    'service_types',
                    'tpv_agent',
                    'disposition',
                    'event_flags',
                    'result',
                ]
            )->get();
            if ($interactions) {
                foreach ($interactions as $interaction) {
                    $iaudits = $this->lookupAudit($interaction->id);
                    if ($iaudits) {
                        foreach ($iaudits as $ia) {
                            $interaction['audits'][] = $ia->toArray();
                        }
                    }
                }
            }

            return [
                'event' => $event,
                'eaudits' => $eaudits,
                'ezconfig_audits' => $ezconfig_audits,
                'interactions' => $interactions,
            ];
        }

        return [
            'eaudits' => null,
            'ezconfig_audits' => null,
            'interactions' => null,
            'event' => null,
            'error' => 'Unable to locate a record with that confirmation code.',
        ];
    }

    public function search_user_info(Request $request)
    {
        $user_id = $request->userId;

        $audits = Audit::select(
            'audits.id',
            'audits.user_id',
            'audits.event',
            'audits.auditable_type',
            'audits.auditable_id',
            'audits.old_values',
            'audits.new_values',
            'audits.created_at',
            'audits.ip_address',
            'users.first_name',
            'users.last_name'
        )->leftJoin(
            'users',
            'audits.user_id',
            'users.id'
        )->whereIn(
            'audits.auditable_type',
            ['App\\Models\\User', 'App\\Models\\BrandUser']
        )->whereNotNull('audits.user_id');

        if ($user_id) {
            $audits = $audits->where(
                'audits.auditable_id',
                $user_id
            );
        }

        $audits = $audits->orderBy(
            'audits.created_at',
            'desc'
        )->simplePaginate(25)->setPath('/reports/search_user_info_from_audits');

        $audits->getCollection()->map(function ($audit) {
            $user = null;
            if ($audit->auditable_type === 'App\\Models\\BrandUser') {
                $user = BrandUser::leftJoin(
                    'users',
                    'users.id',
                    'brand_users.user_id'
                )->withTrashed()->find($audit->auditable_id)->makeHidden(['password']);
                if ($this->array_has_any_keys($audit->new_values, ['office_id', 'role_id', 'channel_id', 'employee_of_id', 'works_for_id', 'language_id', 'state_id'])) {
                    $audit->__set('new_values', $this->friendly_format($audit->new_values));
                    $audit->__set('old_values', $this->friendly_format($audit->old_values));
                }
            } else {
                //If audit.user_id == User.id then no need to check for info on the user table
                if ($audit->auditable_id !== $audit->user_id) {
                    $user = User::withTrashed()->find($audit->auditable_id)->makeHidden(['password']);
                } else {
                    $user = app()->make('stdClass');
                    $user->first_name = $audit->first_name;
                    $user->last_name = $audit->last_name;
                }
            }
            $audit->user = $user;
            if (array_key_exists('password', $audit->old_values)) {
                $ov = $audit->old_values;
                $ov['password'] = 'For security this password is hidden.';
                $audit->__set('old_values', $ov);
            }
            if (array_key_exists('password', $audit->new_values)) {
                $ov = $audit->new_values;
                $ov['password'] = 'For security this password is hidden.';
                $audit->__set('new_values', $ov);
            }
            return $audit;
        });

        return $audits;
    }

    private function array_has_any_keys($arr, $keys): bool
    {
        if (!count($arr)) {
            return false;
        }
        foreach ($keys as $k) {
            if (!array_key_exists($k, $arr)) {
                return true;
            }
        }
        return false;
    }

    private function friendly_format($arr): array
    {
        foreach ($arr as $key => $value) {
            if($arr[$key]){
                switch ($key) {
                    case 'role_id':
                        $arr[$key] = optional($this->get_roles()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
                    case 'channel_id':
                        $arr[$key] = optional($this->get_channels()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
                    case 'works_for_id':
                        $arr[$key] = optional($this->get_brands()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
                    case 'employee_of_id':
                        $arr[$key] = optional($this->get_vendors()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
                    case 'language_id':
                        $arr[$key] = optional($this->get_languages()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
                    case 'state_id':
                        $arr[$key] = optional($this->get_states()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
                    case 'office_id':
                        $arr[$key] = optional($this->get_offices()->first(function ($i) use ($value) {
                            return $i->id == $value;
                        }))->name;
                        break;
    
                    default:
                        # code...
                        break;
                }
            }
        }
        return $arr;
    }
}
