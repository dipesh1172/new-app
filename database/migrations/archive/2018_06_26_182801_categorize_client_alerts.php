<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class CategorizeClientAlerts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1 - Call Start, 2 - cust info, 3 - acct info, 4 - call end
        
        // Account Previously Enrolled 
        DB::table('client_alerts')->where('id', 1)->update(['category_id' => 3]);

        // Callback Number Previously Used 
        DB::table('client_alerts')->where('id', 2)->update(['category_id' => 2]);

        // BTN Matches Sales Rep Phone Number
        DB::table('client_alerts')->where('id', 3)->update(['category_id' => 2]);

        // Existing Service Address
        DB::table('client_alerts')->where('id', 4)->update(['category_id' => 3]);

        // BTN Previously Used for Multiple Customers
        DB::table('client_alerts')->where('id', 5)->update(['category_id' => 2]);

        // BTN and Authorizing Name Previously Good Saled 
        DB::table('client_alerts')->where('id', 6)->update(['category_id' => 2]);

        // Temporary or VOIP Phone Used by Sales Agent
        DB::table('client_alerts')->where('id', 7)->update(['category_id' => 1]);

        // No Sale Alert
        DB::table('client_alerts')->where('id', 8)->update(['category_id' => 4]);

        // Too Many Sales Alert
        DB::table('client_alerts')->where('id', 9)->update(['category_id' => 4]);

        // Sales Rep Selling After Local Curfew
        DB::table('client_alerts')->where('id', 10)->update(['category_id' => 1]);

        // Customer TPVed Multiple Times
        DB::table('client_alerts')->where('id', 11)->update(['category_id' => 2]);

        // BTN Used In Previous No Sales
        DB::table('client_alerts')->where('id', 12)->update(['category_id' => 2]);
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
