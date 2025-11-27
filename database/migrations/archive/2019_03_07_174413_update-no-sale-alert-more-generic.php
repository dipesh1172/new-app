<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class UpdateNoSaleAlertMoreGeneric extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::table('client_alerts')->where('title', 'No Sale Alert')->update(
            [
                'title' => 'Dispositioned Alert',
                'description' => 'Triggers an alert if the TPV was dispositioned for any flagged dispositions. Defaults include fraud indicators: i.e. Agent Acted as Customer, Misrepresentation of Utility, Language Barrier, Not Authorized Decision Maker, Sales Rep Did Not Leave Premises',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // there is no down, only Zuul
    }
}
