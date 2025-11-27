<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDigitalSubmission extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'digital_submission',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('event_id', 36)->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
