<?php

namespace App\Traits;

use App\Models\Brand;
use App\Models\BrandState;
use App\Models\Channel;
use App\Models\State;
use App\Models\Language;
use App\Models\EventType;
use App\Models\UserRole;

use Illuminate\Support\Facades\Cache;

trait SearchFormTrait
{
    private function get_commodities()
    {
        return Cache::remember('search_form_commodities', 3600, function () {
            return EventType::select('id', 'event_type AS name')
                ->whereNull('deleted_at')
                ->whereIn('id', [1, 2])
                ->get();
        });
    }

    private function get_vendors()
    {
        return Cache::remember('search_form_vendors', 1800, function () {
            return Brand::select('brands.id', 'brands.name')
                ->whereNull('client_id')
                ->get();
        });
    }

    private function get_states()
    {
        return Cache::remember('search_form_states', 3600, function () {
            return State::select('id', 'name')->get();
        });
    }

    private function get_brand_states($brand_id)
    {
        return ($brand_id)
            ? Cache::remember('brand_states_' . $brand_id, 3600, function () use ($brand_id) {
                return BrandState::select(
                    'states.name',
                    'states.id'
                )->join(
                    'states',
                    'brand_states.state_id',
                    'states.id'
                )->where(
                    'brand_states.brand_id',
                    $brand_id
                )->get();
            })
            : null;
    }

    private function get_languages()
    {
        return Cache::remember('search_form_languages', 3600, function () {
            return Language::select('id', 'language AS name')->whereIn('id', [1, 2])->get();
        });
    }

    private function get_brands()
    {
        return Cache::remember('search_form_brands', 1800, function () {
            return Brand::select('id', 'name')
                ->whereNotNull('client_id')
                ->orderBy('name')
                ->get();
        });
    }

    private function get_channels()
    {
        return Cache::remember('search_form_channels', 3600, function () {
            return Channel::select('id', 'channel AS name')
                ->orderBy('channel')
                ->get();
        });
    }

    private function get_roles()
    {
        return Cache::remember('search_form_roles', 3600, function () {
            return UserRole::select('id', 'name')
                ->orderBy('name')
                ->get();
        });
    }

    private function get_offices()
    {
        return Cache::remember('search_form_offices', 1800, function () {
            return Brand::select('offices.id', 'offices.name')
                ->whereNull('name')
                ->get();
        });
    }
}
