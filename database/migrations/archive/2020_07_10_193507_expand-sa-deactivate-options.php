<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BusinessRule;

class ExpandSaDeactivateOptions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = BusinessRule::where('slug', 'sales_agent_inactive_auto_disable')->first();
        $br->answers = '{"type":"choice","choices":["Off","1","3","7","10","14","21","30","60","90"]}';
        $br->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
