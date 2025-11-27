<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateReportResultsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('report_results', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->string('brand_id', 36)->nullable();
            $table->bigInteger('report_id');
            $table->date('for_date')->nullable();
            $table->text('parameters')->nullable();
            $table->string('parameters_hash')->nullable();
            $table->text('results')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('report_results');
    }
}
