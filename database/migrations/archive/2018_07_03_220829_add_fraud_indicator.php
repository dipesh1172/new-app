<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFraudIndicator extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'dispositions', function (Blueprint $table) {
                $table->boolean('fraud_indicator')->default(false);
            }
        );

        DB::table('dispositions')->whereIn(
            'reason', [
                'Agent Acted as Customer',
                'Misrepresentation of Utility',
                'Language Barrier',
                'Not Authorized Decision Maker',
                'Sales Rep Did Not Leave Premises'
            ]
        )->update(
            [
                'fraud_indicator' => true
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'dispositions', function (Blueprint $table) {
                $table->dropColumn('fraud_indicator');
            }
        );
    }
}
