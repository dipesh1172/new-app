<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\InteractionType;

class AddSalesPitchInteractionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $it = new InteractionType();
        $it->id = 21;
        $it->name = 'sales_pitch';
        $it->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There  is no down, only Zuul
    }
}
