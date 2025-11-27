<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomDataFieldsToRates extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->text('custom_data_1')->nullable();
            $table->text('custom_data_2')->nullable();
            $table->text('custom_data_3')->nullable();
            $table->text('custom_data_4')->nullable();
            $table->text('custom_data_5')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('rates', function (Blueprint $table) {
            $table->dropColumn(
                [
                    'custom_data_1',
                    'custom_data_2',
                    'custom_data_3',
                    'custom_data_4',
                    'custom_data_5',
                ]
            );
        });
    }
}
