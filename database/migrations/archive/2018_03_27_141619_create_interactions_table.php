<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInteractionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->string('id', 36)->default('')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('event_id', 36)->nullable();
            $table->string('interaction_type_id', 36)->foreign()->references('id')->on('interaction_types');
            $table->string('session_id', 50);
            $table->double('interaction_time')->default(0);
            $table->boolean('billable')->default(true);
            $table->text('notes')->nullable();
        });

        Schema::create('interaction_types', function (Blueprint $table) {
            $table->string('id', 36)->defeault('')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('interactions');
        Schema::dropIfExists('interaction_types');
    }
}
