<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShortcutFieldToDispositions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table(
            'dispositions', function (Blueprint $table) {
                $table->boolean('is_shortcut')->default(false);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table(
            'dispositions', function (Blueprint $table) {
                $table->dropColumn('is_shortcut');
            }
        );
    }
}
