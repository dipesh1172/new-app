<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class PopulateOriginalContractAndFileNameFieldsOnBrandEztpvContracts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $b_max = BrandEztpvContract::selectRaw('min(created_at) as min_created_at, id')->withTrashed()
            ->groupBy('contract_pdf');

        BrandEztpvContract::select(
            'brand_eztpv_contracts.id',
            'brand_eztpv_contracts.contract_pdf'
        )->joinSub($b_max, 'b_max', function ($join) {
            $join->on('b_max.id', '=', 'brand_eztpv_contracts.id');
        })->get()->each(function ($original_contract) {
            BrandEztpvContract::where(
                'contract_pdf',
                $original_contract->contract_pdf
            )->update([
                'original_contract' => $original_contract->id,
                'file_name' => DB::raw("`contract_pdf`")
            ]);
        })->withTrashed();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        BrandEztpvContract::update(['file_name' => null, 'original_contract' => null]);
    }
}