<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\ServiceType;

class AddGenericApiServiceType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $st = new ServiceType();
        $st->id = 99;
        $st->name = 'Generic API';
        $st->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ServiceType::where('name', 'Generic API')->delete();
    }
}
