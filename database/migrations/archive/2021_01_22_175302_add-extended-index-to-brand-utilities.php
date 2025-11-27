<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExtendedIndexToBrandUtilities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_utilities', function (Blueprint $table) {
            $table->index([
                'utility_id',
                'utility_label',
                'brand_id',
            ], 'bu_idx_utility_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_utilities', function (Blueprint $table) {
            $table->dropIndex('bu_idx_utility_search');
        });
    }
}
