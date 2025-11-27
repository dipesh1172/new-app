<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOptionalProductToStorage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'custom_field_storages', function (Blueprint $table) {
                $table->string('product_id', 36)->nullable()->default(null);
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
            'custom_field_storages', function (Blueprint $table) {
                $table->dropColumn('product_id');
            }
        );
    }
}
