<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddInvoiceTracking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'invoice_tracking_types',
            function (Blueprint $table) {
                $table->integer('id')->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('tracking_type');
            }
        );

        DB::table('invoice_tracking_types')
            ->insert(
                [
                    [
                        'id' => 1, 
                        'created_at' => Carbon::now(), 
                        'updated_at' => Carbon::now(),
                        'tracking_type' => 'email',
                    ],
                    [
                        'id' => 2,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'tracking_type' => 'url',
                    ],
                ]
            );

        Schema::create(
            'invoice_tracking',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('invoice_id', 36);
                $table->integer('invoice_tracking_type_id');
                $table->integer('ip_addr')->unsigned()->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoice_tracking_types');
        Schema::dropIfExists('invoice_tracking');
    }
}
