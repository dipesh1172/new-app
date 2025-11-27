<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'custom_fields', function (Blueprint $table) {
                $table->string('id', 36);
                $table->timestamps();
                $table->softDeletes();
                $table->string('name', 50);
                $table->string('output_name', 100);
                $table->string('description', 100);
                $table->text('question');
                $table->integer('custom_field_type_id');
                $table->string('validation_regex', 100);                

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
        Schema::dropIfExists('custom_fields');
    }
}
