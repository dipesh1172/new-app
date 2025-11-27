<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PhoneNumberLookup;
use App\Models\PhoneNumberLabel;
use App\Models\PhoneNumber;
use App\Models\BrandContactType;
use App\Models\BrandContact;
use App\Models\Brand;

class BrandContactController extends Controller
{
    public function index($brandId)
    {
        return view('brands.contacts.index', [
            'brand' => Brand::find($brandId),
            'contact_types' => BrandContactType::select('contact_type AS name', 'id')->get(),
            'phone_types' => PhoneNumberLabel::select('label AS name', 'id')->get(),
        ]);
    }

    public function listContacts($brandId)
    {
        $contacts = BrandContact::select(
            'brand_contacts.id',
            'brand_contacts.name',
            'brand_contacts.title',
            'brand_contacts.email',
            'brand_contacts.brand_contact_type_id',
            'brand_contact_type.contact_type'
        )->with(
            [
                'phones',
                'phones.phone_number',
                'phones.phone_number.label',
            ]
        )->join(
            'brand_contact_type',
            'brand_contact_type.id',
            'brand_contacts.brand_contact_type_id'
        )

            ->where('brand_contacts.brand_id', $brandId)
            ->orderBy('brand_contacts.created_at', 'desc')
            ->paginate(30);

        return response()->json($contacts);
    }

    private function addOrUpdatePhone($contactId, $phone)
    {
        $lookup = null;
        $pn = null;
        $cleanedPhone = CleanPhoneNumber($phone['phone_number']['phone_number']);
        if ($cleanedPhone === null) {
            return;
        }
        if ($phone['id'] !== null) {
            $lookup = PhoneNumberLookup::find($phone['id']);
        }
        if ($phone['phone_number']['id'] !== null) {
            $pn = PhoneNumber::find($phone['phone_number']['id']);
            if ($pn->phone_number !== $cleanedPhone) {
                $pn = null;
            }
            if ($pn !== null && isset($phone['phone_number']['extension']) && $pn->extension !== $phone['phone_number']['extension']) {
                $pn = null;
            }
        }
        if ($pn == null) {
            if (!isset($phone['phone_number']['extension']) || $phone['phone_number']['extension'] == null) {
                $pn = PhoneNumber::where('phone_number', $cleanedPhone)->whereNull('extension')->first();
            } else {
                $pn = PhoneNumber::where('phone_number', $cleanedPhone)->where('extension', $phone['phone_number']['extension'])->first();
            }
        }
        if ($lookup == null) {
            $lookup = new PhoneNumberLookup();
        }
        if ($pn == null) {
            $pn = new PhoneNumber();
            $pn->phone_number = $cleanedPhone;
            $pn->extension = isset($phone['phone_number']['extension']) ? $phone['phone_number']['extension'] : null;
            $pn->label_id = isset($phone['phone_number']['label_id']) ? $phone['phone_number']['label_id'] : null;
            $pn->save();
        } else {
            if ($pn->label_id == null && isset($phone['phone_number']['label_id'])) {
                $pn->label_id = $phone['phone_number']['label_id'];
                $pn->save();
            }
        }
        if ($pn !== null) {
            $lookup->type_id = $contactId;
            $lookup->phone_number_type_id = 8;
            $lookup->phone_number_id = $pn->id;
            $lookup->save();
        }
    }

    public function store(Request $request, $brandId)
    {
        //dd($request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'title' => 'required|string|max:36',
            'email' => 'nullable|email|max:128',
            'phones' => 'nullable|array',
            'remove' => 'nullable|array',
            'brand_contact_type_id' => 'required|exists:brand_contact_type,id',
        ]);
        //dd($validated);
        $contact = BrandContact::create(array_merge($validated, ['brand_id' => $brandId]));
        if ($contact) {
            if (isset($validated['phones']) && is_array($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    $this->addOrUpdatePhone($contact->id, $phone);
                }
            }
            if (isset($validated['remove']) && is_array($validated['remove'])) {
                foreach ($validated['remove'] as $toRemove) {
                    if (isset($toRemove['id'])) {
                        $r = PhoneNumberLookup::find($toRemove['id']);
                        if (!empty($r)) {
                            $r->delete();
                        }
                    }
                }
            }

            return response()->json($contact, 201);
        }

        return response('Not Found', 404);
    }

    public function update(Request $request, $brandId, $contactId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'title' => 'required|string|max:36',
            'email' => 'nullable|email|max:128',
            'phones' => 'nullable|array',
            'remove' => 'nullable|array',
            'brand_contact_type_id' => 'required|exists:brand_contact_type,id',
        ]);
        $contact = BrandContact::find($contactId);
        if ($contact) {
            $contact->update($validated);
            //PhoneNumberLookup::where('type_id', $contact->id)->where('phone_number_type_id', 8)->delete();
            if (isset($validated['phones']) && is_array($validated['phones'])) {
                foreach ($validated['phones'] as $phone) {
                    $this->addOrUpdatePhone($contact->id, $phone);
                }
            }
            if (isset($validated['remove']) && is_array($validated['remove'])) {
                foreach ($validated['remove'] as $toRemove) {
                    if (isset($toRemove['id'])) {
                        PhoneNumberLookup::find($toRemove['id'])->delete();
                    }
                }
            }

            return response()->json($contact, 202);
        }

        return response('Not Found', 404);
    }

    public function destroy(Request $request, $brandId, $contactId)
    {
        $contact = BrandContact::find($contactId);
        $contact->delete();

        return response()->json(null, 204);
    }
}
