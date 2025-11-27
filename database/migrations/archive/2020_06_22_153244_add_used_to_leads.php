<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUsedToLeads extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->tinyInteger('used')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
