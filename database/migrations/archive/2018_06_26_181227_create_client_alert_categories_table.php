<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientAlertCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'client_alert_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->string('name');
                $table->string('display_name');
                $table->text('description');
            }
        );

        DB::table('client_alert_categories')->insert(
            [
                [
                    'name' => 'CALL-START', 
                    'display_name' => 'Call Start', 
                    'description' => 'Alerts in this category can be detected at the beginning of the call.'
                ],
                [
                    'name' => 'CUST-INFO-PROVIDED', 
                    'display_name' => 'Customer Information Provided', 
                    'description' => 'Alerts in this category can be detected once the agent has provided customer contact information.'
                ],
                [
                    'name' => 'ACCT-INFO-PROVIDED', 
                    'display_name' => 'Account Information Provided', 
                    'description' => 'Alerts in this category can be detected when the agent provides account information.'
                ],
                [
                    'name' => 'DISPOSITIONED', 
                    'display_name' => 'Call Start', 
                    'description' => 'Alerts in this category can be detected at the end of the call.'
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
        Schema::dropIfExists('client_alert_categories');
    }
}
