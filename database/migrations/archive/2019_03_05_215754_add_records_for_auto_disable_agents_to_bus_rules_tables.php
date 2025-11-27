<?php

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRecordsForAutoDisableAgentsToBusRulesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = new BusinessRule;
        $br->slug = 'sales_agent_inactive_auto_disable';
        $br->business_rule = 'Disable sales agents without sales activity automatically (in x days)';
        $br->answers = '{"type":"choice","choices":["Off","10","14","21","30","60","90"]}';
        $br->save();

        $brd = new BusinessRuleDefault;
        $brd->business_rule_id = $br->id;
        $brd->default_answer = '10';
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
