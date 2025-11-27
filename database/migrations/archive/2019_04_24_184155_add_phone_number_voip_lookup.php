<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPhoneNumberVoipLookup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'phone_number_voip_lookup',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('phone_number_id', 36)->nullable();
                $table->text('response')->nullable();
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
        // There is no down, only Zuul!
    }
}
