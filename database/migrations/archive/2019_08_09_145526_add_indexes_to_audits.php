<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToAudits extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('auditable_id');
            $table->index('auditable_type');
            $table->index('user_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
