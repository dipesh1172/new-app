<?php

use App\Models\InteractionType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddWarmTransferInteractionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $it = new InteractionType();
        $it->id = 10;
        $it->name = 'warm_transfer';
        $it->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        InteractionType::where('name', 'warm_transfer')->delete();
    }
}
