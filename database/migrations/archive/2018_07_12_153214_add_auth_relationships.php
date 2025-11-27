<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAuthRelationships extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'auth_relationships', 
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('relationship', 128);
            }
        );
        $when = now();
        DB::table('auth_relationships')->insert(
            [
                ['created_at' => $when, 'updated_at' => $when, 'relationship' => 'Account Holder'],
                ['created_at' => $when, 'updated_at' => $when, 'relationship' => 'Spouse'],
                ['created_at' => $when, 'updated_at' => $when, 'relationship' => 'Power of Attorney'],
                ['created_at' => $when, 'updated_at' => $when, 'relationship' => 'Guardian'],
                ['created_at' => $when, 'updated_at' => $when, 'relationship' => 'Conservator'],
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auth_relationships');
    }
}
