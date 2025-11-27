<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTextMessageTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('text_message_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
        });

        DB::table('text_message_types')->insert([
            [
                'id' => 1,
                'name' => 'General (Untyped)'
            ],
            [
                'id' => 2,
                'name' => 'Verification Notification'
            ],
            [
                'id' => 3,
                'name' => 'Request To Sign'
            ],
            [
                'id' => 4,
                'name' => 'Complete Digital'
            ],
            [
                'id' => 5,
                'name' => 'Document Delivery'
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('text_message_types');
    }
}
