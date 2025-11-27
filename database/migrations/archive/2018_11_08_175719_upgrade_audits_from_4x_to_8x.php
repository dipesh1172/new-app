<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpgradeAuditsFrom4xTo8x extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('user_type')->nullable();
            $table->string('tags')->nullable();
        });

        // Set the user_type value and keep the timestamp values.
        DB::table('audits')->update([
            'user_type' => \App\Models\User::class,
            'created_at' => DB::raw('created_at'),
            'updated_at' => DB::raw('updated_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
