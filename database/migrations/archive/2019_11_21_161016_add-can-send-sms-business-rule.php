<?php

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use Illuminate\Database\Migrations\Migration;


class AddCanSendSmsBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = new BusinessRule();
        $br->slug = 'admin_can_send_sms';
        $br->business_rule = 'Can a Client Admin send text messages to sales agents?';
        $br->answers = '{"type":"switch","on":"","off":"","default":""}';
        $br->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $br->id;
        $brd->default_answer = 'false';
        $brd->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $br = BusinessRule::where('slug', 'admin_can_send_sms')->first();
        BusinessRuleDefault::where('business_rule_id', $br->id)->delete();
        $br->delete();
    }
}
