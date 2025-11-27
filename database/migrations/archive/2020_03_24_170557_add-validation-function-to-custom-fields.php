<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddValidationFunctionToCustomFields extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->text('validation_function')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropColumn('validation_function');
        });
    }
}
