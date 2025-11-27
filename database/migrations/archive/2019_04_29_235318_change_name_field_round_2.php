<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeNameFieldRound2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = BrandEztpvContract::select('contract_fdf')
            ->where('brand_id', 'd758c445-6144-4b9c-b683-717aadec83aa')
            ->get();
dd($contracts);
        foreach ($contracts as $contract)
        {
            $contract->contract_fdf = str_replace('[auth_fullname]', '[auth_fullname_fl]', $contract->contract_fdf);
            $contract->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
