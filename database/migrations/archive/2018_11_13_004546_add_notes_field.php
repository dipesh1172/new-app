<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNotesField extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->string('notes', 50);
            $table->string('restrict', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->string('restrict', 6)->change();
        });
    }
}
