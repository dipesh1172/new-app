<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BusinessRule;

class AddLeadsUseZipRestrictionBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $exists = BusinessRule::where('slug', 'leads_use_zip_restrictions')->first();
        if (empty($exists)) {
            $br = new BusinessRule();
            $br->slug = 'leads_use_zip_restrictions';
            $br->business_rule = 'When a Lead is looked up, its zip code must match the Vendor Zip Restrictions configuration?';
            $br->answers = '{"type":"switch","on":"Yes","off":"No","default":"No"}';
            $br->save();
        }
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
