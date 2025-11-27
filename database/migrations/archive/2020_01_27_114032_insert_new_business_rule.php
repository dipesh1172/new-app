<?php

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use Illuminate\Database\Migrations\Migration;

class InsertNewBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = new BusinessRule();
        $br->slug = 'v_info_role';
        $br->business_rule = "Vendor's role of access to information";
        $br->answers = '{"type":"choice","choices":["No Data View","Partial Data View", "Full Data View"]}';
        $br->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $br->id;
        $brd->default_answer = 'Full Data View';
        $brd->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}