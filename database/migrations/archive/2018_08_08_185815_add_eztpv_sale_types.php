<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvSaleTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'eztpv_sale_types',
            function (Blueprint $table) {
                $table->increments('id');;
                $table->timestamps();
                $table->softDeletes();
                $table->string('slug', 36);
                $table->string('sale_type', 255)->nullable();
            }
        );

        $when = now();
        DB::table('eztpv_sale_types')->insert(
            [
                [
                    'created_at' => $when, 
                    'updated_at' => $when, 
                    'slug' => 'live',
                    'sale_type' => 'Data Submission with Live Call.',
                ],
                [
                    'created_at' => $when,
                    'updated_at' => $when,
                    'slug' => 'email',
                    'sale_type' => 'Data Submission with Document Services by Email with Live Call.',
                ],
                [
                    'created_at' => $when,
                    'updated_at' => $when,
                    'slug' => 'text',
                    'sale_type' => 'Data Submission with Document Services by Text with Live Call.',
                ],
                [
                    'created_at' => $when,
                    'updated_at' => $when,
                    'slug' => 'photo',
                    'sale_type' => 'Data Submission with Photo.',
                ],
                [
                    'created_at' => $when,
                    'updated_at' => $when,
                    'slug' => 'photo_live',
                    'sale_type' => 'Data Submission with Photo and Live Call.',
                ],
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
        Schema::dropIfExists('eztpv_sale_types');
    }
}
