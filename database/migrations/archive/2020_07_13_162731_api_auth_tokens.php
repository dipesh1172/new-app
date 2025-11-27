<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ApiAuthTokens extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_auth_tokens', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('api_token_id', 36)->nullable();
            $table->string('auth_token', 48);
            $table->dateTime('expires_at')->nullable();
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
    }
}
