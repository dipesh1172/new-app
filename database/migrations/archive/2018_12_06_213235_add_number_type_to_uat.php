<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNumberTypeToUat extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'utility_account_types', function (Blueprint $table) {
                $table->integer('utility_account_number_type_id')->default(1);
            }
        );
        DB::table('utility_account_types')->whereIn('id', [7, 8, 11])->update(['utility_account_number_type_id' => 2]);
        DB::table('utility_account_types')->where('id', 9)->update(['utility_account_number_type_id' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'utility_account_types', function (Blueprint $table) {
                $table->dropColumn('utility_account_number_type_id');
            }
        );
    }
}
