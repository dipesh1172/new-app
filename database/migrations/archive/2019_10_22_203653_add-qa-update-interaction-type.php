<?php

use App\Models\InteractionType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQaUpdateInteractionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $i = new InteractionType();
        $i->name = 'qa_update';
        $i->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        InteractionType::where('name', 'qa_update')->delete();
    }
}
