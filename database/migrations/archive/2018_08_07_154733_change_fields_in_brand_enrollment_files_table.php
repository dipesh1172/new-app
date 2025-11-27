<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeFieldsInBrandEnrollmentFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_enrollment_files', function (Blueprint $table) {
            $table->dateTime('next_run')->nullable()->change();
            $table->dateTime('last_run')->nullable()->change();
            $table->dropColumn('delivery_method');
            $table->dropColumn('delivery_target');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table->string('delivery_method', 255);
        $table->text('delivery_target');
    }
}
