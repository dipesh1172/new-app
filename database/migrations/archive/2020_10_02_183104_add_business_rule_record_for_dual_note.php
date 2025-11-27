<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;

class AddBusinessRuleRecordForDualNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = new BusinessRule();
        $br->slug = 'dual_product_note';
        $br->business_rule = 'Show note about dual products in EzTPV';
        $br->answers = '{"type":"textbox","text":""}';
        $br->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $br->id;
        $brd->default_answer = null;
        $brd->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $br = BusinessRule::where(
            'slug',
            'dual_product_note'
        )
            ->first();

        BusinessRuleDefault::where(
            'business_rule_id',
            $br->id
        )
            ->delete();

        BusinessRule::destroy($br->id);
    }
}
