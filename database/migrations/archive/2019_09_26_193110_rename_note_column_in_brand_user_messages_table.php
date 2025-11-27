<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameNoteColumnInBrandUserMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_user_messages', function (Blueprint $table) {
            $table->renameColumn('note', 'message');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_user_messages', function (Blueprint $table) {
            $table->renameColumn('message', 'note');
        });
    }
}
