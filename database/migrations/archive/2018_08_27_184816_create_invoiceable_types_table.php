<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class CreateInvoiceableTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'invoiceable_types', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('desc', 30);
                $table->string('resource', 20)->unique();
                $table->string('standard_rate')->nullable();
                $table->integer('currency_id')->nullable();
            }
        );

        $now = Carbon::now();
        DB::table('invoiceable_types')->insert(
            [
            'created_at' => $now,
            'updated_at' => $now,
            'desc' => 'Short Messaging Service',
            'resource' => 'Twilio::SMS',
            'standard_rate' => '0.8',
            'currency_id' => 1
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
        Schema::dropIfExists('invoiceable_types');
    }
}
