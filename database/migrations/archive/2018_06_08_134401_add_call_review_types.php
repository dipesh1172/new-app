<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCallReviewTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('call_review_types', function(Blueprint $table)
        {
            $table->increments('id');
            $table->timestamps();
            $table->integer('call_review_type_category_id');
            $table->text('call_review_type', 65535)->nullable();
        });

        Schema::create('call_review_type_categories', function(Blueprint $table)
        {
            $table->increments('id');
            $table->timestamps();
            $table->string('call_review_type_category', 128)->nullable();
        });  
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('call_review_types');
        Schema::drop('call_review_type_categories');
    }
}
