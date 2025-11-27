<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandsRecordingTransfer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brands',
            function (Blueprint $table) {
                $table->tinyInteger('recording_transfer')->default(0);
                $table->text('recording_transfer_config')->nullable();
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
        Schema::table(
            'brands',
            function (Blueprint $table) {
                $table->dropColumn('recording_transfer');
                $table->dropColumn('recording_transfer_config');
            }
        );
    }
}
