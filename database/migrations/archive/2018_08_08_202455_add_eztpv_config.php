<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'vendor_eztpv_config', 
            function (Blueprint $table) {
                $table->text('config');
            }
        );

        $when = now();
        DB::table('eztpv_sale_types')->insert(
            [
                [
                    'created_at' => $when,
                    'updated_at' => $when,
                    'slug' => 'doc_text_photo_live',
                    'sale_type' => 'Data Submission, Document Services by Text, Photo, and Live Call',
                ],
                [
                    'created_at' => $when,
                    'updated_at' => $when,
                    'slug' => 'doc_email_photo_live',
                    'sale_type' => 'Data Submission, Document Services by Email, Photo, and Live Call',
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
        Schema::table(
            'vendor_eztpv_config', 
            function (Blueprint $table) {
                $table->dropColumn('config');
            }
        );
    }
}
