<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\ClientAlert;

class FixSpellingOfMultipleVoipAlertDescription extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $ca = ClientAlert::where('function', 'checkHasMultipleVoipUsagesToday')->first();
        $ca->title = 'VOIP Phone has been used by Sales Agent for multiple Good Sales';
        $ca->save();
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
