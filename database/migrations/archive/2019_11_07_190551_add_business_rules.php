<?php

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddBusinessRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            $data = array(
            array('slug'=>'vendor_manager_can_export', 'business_rule'=> "Will Vendor Manager's be authorized to export data from the client portal?", 'answers' => '{"type":"switch","on":"Yes","off":"No","default":"Yes"}'),
            array('slug'=>'office_manager_can_export', 'business_rule'=> "Will Office Manager's be authorized to export data from the client portal?", 'answers' => '{"type":"switch","on":"Yes","off":"No","default":"Yes"}'),
        );
        
            foreach ($data as $br) {
                $business_r = new BusinessRule($br);
                $business_r->save();

                //Adding entry to BusinessRuleDefault
                BusinessRuleDefault::insert(['business_rule_id' => $business_r->id, 'default_answer' => 'Yes']);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
