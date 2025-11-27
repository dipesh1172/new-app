<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BusinessRule;

class FixMisspelledVendorManageSalesAgentBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = BusinessRule::where('slug', 'vendor_manage_sales_agent')->first();
        $br->business_rule = 'Allow the Vendor to manage (add, edit, enable/disable) the sales agents that they employ?';
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
