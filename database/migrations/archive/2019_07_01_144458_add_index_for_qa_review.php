<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexForQaReview extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('interactions', function(Blueprint $table)
        {
            $table->index(['id', 'interaction_type_id', 'event_result_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('interactions', function(Blueprint $table)
        {
            $table->dropIndex(['id', 'interaction_type_id', 'event_result_id']);
        });
    }
}
