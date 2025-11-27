<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandCustomFieldsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'brand_custom_fields', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();

                $table->enum(
                    'associated_with_type', [
                    'Event', 
                    'Product', 
                    'Rate', 
                    'State',
                    'Script',
                    'Vendor'
                    ]
                );
                $table->string('associated_with_id', 36)->nullable();
                $table->string('custom_field_id', 36);
                $table->string('brand_id', 36);
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
        Schema::dropIfExists('brand_custom_fields');
    }
}
