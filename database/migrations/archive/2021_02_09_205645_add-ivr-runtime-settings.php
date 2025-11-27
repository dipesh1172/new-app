<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\RuntimeSetting;

class AddIvrRuntimeSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $minAccept = new RuntimeSetting();
        $minAccept->namespace = 'system';
        $minAccept->name = 'ivr_minimum_accept';
        $minAccept->value = 75;
        $minAccept->save();

        $minCorrect = new RuntimeSetting();
        $minCorrect->namespace = 'system';
        $minCorrect->name = 'ivr_minimum_correct';
        $minCorrect->value = 95;
        $minCorrect->save();
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
