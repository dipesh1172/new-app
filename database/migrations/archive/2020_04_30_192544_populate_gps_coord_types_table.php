<?php

use App\Models\GpsCoordType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PopulateGpsCoordTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $types = [
            'customer_device',
            'sales_agent_device',
            'tpv_staff_device',
            'client_device',
            'user_device',
            'attorney_device'
        ];

        foreach ($types as $type) {
            $gct = new GpsCoordType();
            $gct->coord_type = $type;
            $gct->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
