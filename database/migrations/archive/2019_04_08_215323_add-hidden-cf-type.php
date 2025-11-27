<?php

use App\Models\CustomFieldType;
use Illuminate\Database\Migrations\Migration;

class AddHiddenCfType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $x = new CustomFieldType();
        $x->name = 'Hidden';
        $x->description = 'This question is not shown to the agent but is available for scripting use.';
        $x->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $x = CustomFieldType::where('name', 'Hidden')->first();
        if ($x) {
            $x->delete();
        }
    }
}
