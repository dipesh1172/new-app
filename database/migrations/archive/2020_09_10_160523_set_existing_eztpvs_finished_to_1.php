<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Eztpv;

class SetExistingEztpvsFinishedTo1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $eztpvs = Eztpv::where(
            'finished',
            0
        )
        ->update([
            'finished' => 1
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $eztpvs = Eztpv::where(
            'finished',
            1
        )
        ->update([
            'finished' => 0
        ]);
    }
}
