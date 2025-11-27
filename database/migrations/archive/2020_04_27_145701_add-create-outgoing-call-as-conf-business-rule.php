<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BusinessRuleDefault;
use App\Models\BusinessRule;

class AddCreateOutgoingCallAsConfBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $x = new BusinessRule();
        $x->slug = 'outgoing_as_conf';
        $x->business_rule = 'Create outgoing calls as conferences for warm transfer capability';
        $x->answers = '{"type":"switch","on":"Yes","off":"No","default":"No"}';
        $x->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $x->id;
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
        $x = BusinessRule::where('slug', 'outgoing_as_conf')->first();
        if ($x !== null) {
            $id = $x->id;
            $x->forceDelete();

            $brd = BusinessRuleDefault::where('business_rule_id', $id)->first();
            if ($brd !== null) {
                $brd->forceDelete();
            }
        }
    }
}
