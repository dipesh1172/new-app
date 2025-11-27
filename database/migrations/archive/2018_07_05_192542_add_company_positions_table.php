<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCompanyPositionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'default_sc_company_positions', 
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('title');
            }
        );
        $when = now();
        DB::table('default_sc_company_positions')->insert(
            [
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Account Holder'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Accountant'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Chairman / CEO'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Chairman / CFO'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Controller'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Director'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Facilities Manager'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Facilities Administrator'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'General Manager'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Manager'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Office Manager'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Operations Manager'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Owner / Co-Owner'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Pastor / Rabbi / Imam / Priest / Bishop'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'President'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Property Manager'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Treasurer'],
                ['created_at' => $when, 'updated_at' => $when, 'title' => 'Vice President'],
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
        Schema::dropIfExists('default_company_positions');
    }
}
