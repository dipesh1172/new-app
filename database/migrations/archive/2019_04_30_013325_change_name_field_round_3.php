<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeNameFieldRound3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = BrandEztpvContract::select('id', 'contract_fdf')
            ->where('brand_id', 'd758c445-6144-4b9c-b683-717aadec83aa')
            ->get();

        foreach ($contracts as $contract)
        {
            $update = BrandEztpvContract::find($contract->id);
            $update->contract_fdf = str_replace('[auth_fullname]', '[auth_fullname_fl]', $contract->contract_fdf);
            $update->save();
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