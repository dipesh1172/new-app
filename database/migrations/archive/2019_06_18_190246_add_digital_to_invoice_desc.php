<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDigitalToInvoiceDesc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('invoice_desc')->insert(
            [
                [
                    'id' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'item_desc' => 'Digital TPV',
                ],
            ]
        );

        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->double('digital_transaction')->nullable();
        });

        Schema::table('daily_stats', function (Blueprint $table) {
            $table->integer('digital_transaction')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
