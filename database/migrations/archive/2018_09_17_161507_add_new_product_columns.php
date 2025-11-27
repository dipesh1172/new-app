<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewProductColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'products',
            function (Blueprint $table) {
                $table->string('source_code', 128)
                    ->nullable()->after('promo_code');
                $table->string('renewal_plan', 128)
                    ->nullable()->after('source_code');
                $table->string('channel_source', 24)
                    ->nullable()->after('renewal_plan');
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
            'products',
            function (Blueprint $table) {
                $table->removeColumn('source_code');
                $table->removeColumn('renewal_plan');
                $table->removeColumn('channel_source');
            }
        );
    }
}
