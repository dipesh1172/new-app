<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\UtilityAccountType;
use App\Models\UtilityAccountIdentifier;

class MoveUtilityAccountNumberTypeToUtilityIdentifiers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('utility_account_identifiers', function (Blueprint $table) {
            $table->integer('utility_account_number_type_id')->default(1);
        });

        $types = UtilityAccountType::where('utility_account_number_type_id', '>', 1)->get();
        foreach ($types as $uat) {
            UtilityAccountIdentifier::where('utility_account_type_id', $uat->id)
                ->update(['utility_account_number_type_id' => $uat->utility_account_number_type_id]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('utility_account_identifiers', function (Blueprint $table) {
            $table->dropColumn('utility_account_number_type_id');
        });
    }
}
