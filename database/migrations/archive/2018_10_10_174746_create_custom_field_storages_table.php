<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomFieldStoragesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'custom_field_storages', function (Blueprint $table) {
                $table->string('id', 36);
                $table->timestamps();
                $table->softDeletes();
                $table->string('custom_field_id', 36);
                $table->text('value');
                $table->string('event_id', 36);

                $table->primary('id');
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
        Schema::dropIfExists('custom_field_storages');
    }
}
