<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRateMeta extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'rate_meta', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('event_product_id', 36)->nullable();
                $table->string('rate_id', 36)->nullable();
                $table->timestamp('start_date')->nullable();
                $table->decimal('rate_amount', 10, 4)->nullable();
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
        Schema::dropIfExists('rate_meta');
    }
}
