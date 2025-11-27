<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\Models\GpsCoordType;

class AddSuggestedAddressRecordToGpsCoordTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $newType = new GpsCoordType();

        $newType->coord_type = 'suggested_address';
        $newType->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $x = GpsCoordType::where('coord_type', 'suggested_address')->first();
        
        if($x) {
            $x->forceDelete();
        }
    }
}
