<?php

use App\Models\BrandServiceType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddContactlessPhotoCaptureRowToBrandServiceTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bst = new BrandServiceType();
        $bst->slug = 'customer_device_photo';
        $bst->name = 'Customer Device Photo';
        $bst->description = 'Allows the option of photo capture on the customer device instead of the sales agent device.';
        $bst->save();
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
