<?php

use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;


class AddTxuApiServiceType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $st = new ServiceType();
        $st->name = 'TXU SOAP API';
        $st->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ServiceType::where('name', 'TXU SOAP API')->delete();
    }
}
