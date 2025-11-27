<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\RuntimeSetting;

class AddEmailAllowlistRuntimeSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $rt = new RuntimeSetting();
        $rt->namespace = 'email';
        $rt->name = 'allowlist';
        $rt->description = 'Allowed domain list that will not mark delivery failures as undeliverable';
        $rt->value = 'tpv.com,indraenergy.com';
        $rt->save();
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
