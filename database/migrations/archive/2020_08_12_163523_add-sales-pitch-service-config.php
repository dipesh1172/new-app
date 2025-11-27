<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandServiceType;

class AddSalesPitchServiceConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bst = new BrandServiceType();
        $bst->slug = 'sales_pitch_capture';
        $bst->name = 'Capture Sales Pitch';
        $bst->description = 'Allows the option capturing the sales pitch through a callback to the agent\'s device.';
        $bst->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
