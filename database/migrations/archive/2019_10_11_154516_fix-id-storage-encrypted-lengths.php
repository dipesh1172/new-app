<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixIdStorageEncryptedLengths extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('identification_storage', function (Blueprint $table) {
            $table->text('control_number')->change();
            $table->text('named_person')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('identification_storage', function (Blueprint $table) {
            // There is no down, only Zuul
        });
    }
}
