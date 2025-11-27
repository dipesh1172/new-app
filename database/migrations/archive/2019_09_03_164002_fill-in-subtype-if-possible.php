<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\PhoneNumberVoipLookup;
use Illuminate\Support\Facades\DB;

class FillInSubtypeIfPossible extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            PhoneNumberVoipLookup::where('phone_number_type', 'voip')->whereNull('phone_number_subtype')->get()
                ->each(function ($item) {
                    $data = $item->response;
                    if (is_string($data)) {
                        $data = json_decode($data, true);
                    }
                    $type = null;
                    if (isset($data['addOns'])) {
                        if (isset($data['addOns']['results'])) {
                            if (isset($data['addOns']['results']['whitepages_pro_phone_intel'])) {
                                if (isset($data['addOns']['results']['whitepages_pro_phone_intel']['result'])) {
                                    if (isset($data['addOns']['results']['whitepages_pro_phone_intel']['result']['line_type'])) {
                                        $type = $data['addOns']['results']['whitepages_pro_phone_intel']['result']['line_type'];
                                    }
                                }
                            }
                        }
                    }
                    if ($type !== null) {
                        if ($type == 'FixedVOIP') {
                            $type = 'fixed';
                        }
                        if ($type == 'NonFixedVOIP') {
                            $type = 'non-fixed';
                        }
                        $item->phone_number_subtype = $type;
                        $item->save();
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
