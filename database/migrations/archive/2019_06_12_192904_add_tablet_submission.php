<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTabletSubmission extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(
            'tablet_submission',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->tinyInteger('tablet_provider_id')->nullable();
                $table->text('payload')->nullable();
                $table->string('confirmation_code', 32)->nullable();
                $table->text('response')->nullable();
            }
        );

        Schema::create(
            'tablet_providers',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('provider', 64)->nullable();
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
