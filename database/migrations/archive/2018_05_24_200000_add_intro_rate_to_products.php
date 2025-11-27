<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIntroRateToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'products', function (Blueprint $table) {
                $table->double('intro_daily_fee', 10, 4)
                    ->nullable()->default(null);

                $table->double('intro_service_fee', 10, 4)
                    ->nullable()->default(null);

                $table->integer('intro_term')
                    ->nullable()->default(null);

                $table->integer('intro_term_type_id')
                    ->nullable()->default(null);
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
        Schema::table(
            'products', function (Blueprint $table) {
                $table->dropColumn(
                    [
                        'intro_daily_fee', 
                        'intro_service_fee', 
                        'intro_term', 
                        'intro_term_type_id',
                    ]
                );
            }
        );
    }
}
