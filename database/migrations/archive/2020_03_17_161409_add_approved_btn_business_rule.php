<?php

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;

use Illuminate\Database\Migrations\Migration;

class AddApprovedBtnBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = new BusinessRule();
        $br->slug = 'approved_btn';
        $br->business_rule = "Require that phone number must appear on the approved billing telephone number (btn) list?";
        $br->answers = '{"type":"switch","on":"Yes","off":"No","default":"No"}';
        $br->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $br->id;
        $brd->default_answer = 'No';
        $brd->save();
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
