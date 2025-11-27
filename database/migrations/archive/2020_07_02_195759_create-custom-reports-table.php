<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36);
            $table->string('vendor_id', 36)->nullable();
            $table->string('report_name');
            $table->string('report_icon')->nullable();
            $table->text('report_description')->nullable();
            $table->text('columns')->nullable();
            $table->text('filters')->nullable();
            $table->boolean('vendors_can_access');
            $table->string('default_sort_field');
            $table->string('default_sort_direction')->default('ASC');
            $table->string('date_field');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_reports');
    }
}
