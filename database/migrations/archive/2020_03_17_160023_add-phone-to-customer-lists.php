<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddPhoneToCustomerLists extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('customer_lists', function (Blueprint $table) {
            $table->string('phone_number_id', 36)->nullable();
        });

        $now = Carbon::now();

        DB::table('customer_list_type')->insert([
            [
                'id' => 3,
                'created_at' => $now,
                'updated_at' => $now,
                'customer_list_type' => 'Approved Customers',
            ],
            [
                'id' => 4,
                'created_at' => $now,
                'updated_at' => $now,
                'customer_list_type' => 'Do Not Call',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('customer_lists', function (Blueprint $table) {
            $table->dropColumn('phone_number_id');
        });

        DB::table('customer_list_type')->whereIn('id', [3, 4])->delete();
    }
}
