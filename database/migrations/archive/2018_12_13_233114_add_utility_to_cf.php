<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddUtilityToCf extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::unprepared("ALTER TABLE tpv.brand_custom_fields MODIFY COLUMN associated_with_type enum('Event','Product','Rate','State','Script','Vendor','Utility') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::unprepared("ALTER TABLE tpv.brand_custom_fields MODIFY COLUMN associated_with_type enum('Event','Product','Rate','State','Script','Vendor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;");
    }
}
