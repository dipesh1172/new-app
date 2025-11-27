<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeBrandCustomFieldFromEnum extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('brand_custom_fields', function (Blueprint $table) {
            $table->string('associated_with_type_new', 36);
        });

        DB::update('update brand_custom_fields set associated_with_type_new = associated_with_type');

        Schema::table('brand_custom_fields', function (Blueprint $table) {
            $table->dropColumn('associated_with_type');
        });

        Schema::table('brand_custom_fields', function (Blueprint $table) {
            $table->renameColumn('associated_with_type_new', 'associated_with_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('brand_custom_fields', function (Blueprint $table) {
            // There is no down, only Zuul!
        });
    }
}
