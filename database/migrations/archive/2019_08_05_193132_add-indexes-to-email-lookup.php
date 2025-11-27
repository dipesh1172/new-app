<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToEmailLookup extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('email_address_lookup', function (Blueprint $table) {
            $table->index(['type_id', 'email_address_type_id', 'email_address_id'], 'email_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('email_address_lookup', function (Blueprint $table) {
            $table->dropIndex('email_type_index');
        });
    }
}
