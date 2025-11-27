<?php

use App\Models\BrandContact;
use App\Models\PhoneNumber;
use App\Models\PhoneNumberLookup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MoveBrandContactPhoneToPhoneNumbers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $lookupType = DB::table('phone_number_types')->where('phone_number_type', 'Brand Contact')->first()->id;
        BrandContact::all()->each(function ($item) use ($lookupType) {
            DB::transaction(function () use ($item, $lookupType) {
                $oldPhone = $item->phone;
                $oldPhoneType = $item->phone_number_label_id;
                if ($oldPhone != null) {
                    if (strlen(trim($oldPhone)) == 10) {
                        $oldFormattedPhone = '+1' . trim($oldPhone);

                        $existing = PhoneNumber::where('phone_number', $oldFormattedPhone)->first();
                        if ($existing == null) {
                            $existing = new PhoneNumber();
                            $existing->phone_number = $oldFormattedPhone;
                            $existing->label = $oldPhoneType;
                            $existing->save();
                        }

                        $l = new PhoneNumberLookup();
                        $l->phone_number_type_id = $lookupType;
                        $l->type_id = $item->id;
                        $l->phone_number_id = $existing->id;
                        $l->save();

                        $item->phone = null;
                        $item->phone_number_label_id = null;
                        $item->save();
                    } else {
                        info('wrong number length: ' . $item->id);
                    }
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
