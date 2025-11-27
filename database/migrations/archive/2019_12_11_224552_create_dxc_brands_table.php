<?php

use App\Models\Brand;
use App\Models\DxcBrand;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateDxcBrandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            Schema::create('dxc_brands', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamps();
                $table->string('dxc_brand_name', 64);
                $table->string('brands_id', 36);
                $table->softDeletes();
            });

            $focusXlegacy = [
                'Atlantic Energy' => ['z_DXC_Atlantic_Energy_LLC'],
                'Clearview' => ['z_DXC_Clearview_Energy'],
                'Direct Energy' => [
                    'z_DXC_Direct Energy - AB',
                    'z_DXC_Direct Energy - TX',
                    'z_DXC_Direct Energy - US - HS'
                ],
                'Energy Plus' => [
                    'z_DXC_Energy_Plus',
                    'z_DXC_Engie_Resource_LLC'
                ],
                'Frontier' => ['z_DXC_Frontier_Utilities_LLC'],
                'Frontier TX' => ['z_DXC_Frontier_Utilities_LLC'],
                'Gateway' => [
                    'z_DXC_Direct Energy - AB',
                    'z_DXC_Direct Energy - TX',
                    'z_DXC_Direct Energy - US - HS'
                ],
                'Green Mountain' => [
                    'z_DXC_Green Mountain - QC Callbacks',
                    'z_DXC_Green_Mountain_Energy'
                ],
                'IDT' => ['z_DXC_IDT_Corporation'],
                'IDT Energy' => ['z_DXC_IDT_Energy'],
                'Inspire Energy' => ['z_DXC_Inspire_Energy_Holdings_LLC'],
                'Just Energy' => [
                    'z_DXC_Just_Energy',
                    'z_DXC_Just Energy - South - Resi - CA',
                    'z_DXC_Just Energy - Midwest - Resi - OH',
                    'z_DXC_Just Energy - Midwest - Resi - MI',
                    'z_DXC_Just Energy - Midwest - Resi - IL',
                    'z_DXC_Just Energy - Midwest - Comm - IL',
                    'z_DXC_Just Energy - East - Resi - PA',
                    'z_DXC_Just Energy - East - Resi - NY',
                    'z_DXC_Just Energy - East - Resi - MD',
                    'z_DXC_Just Energy - East - Resi - MA',
                    'z_DXC_Inspire_Energy_Holdings_LLC'
                ],
                'My Choice Energy' => ['z_DXC_My_Choice_Energy'],
                'NextEra' => ['z_DXC_Nextera_Energy_Services_LLC'],
                'NRG' => [
                    'z_DXC_NRG - QC Callbacks',
                    'z_DXC_NRG_ENERGY_INC',
                    'z_DXC_NRG_QC_Callbacks'
                ],
                'Reliant Energy' => ['z_DXC_Reliant_Energy'],
                'Santanna Energy' => ['z_DXC_Santanna_Energy_Services'],
                'SouthStar' => ['z_DXC_Southstar_Energy_Services_LLC'],
                'Sperian' => ['z_DXC_Sperian_Energy_Corporation'],
                'Think Energy' => ['z_DXC_Think_Energy_LLC'],
                'TXU' => ['z_DXC_TXU_Energy'],
            ];

            foreach ($focusXlegacy as $dxc_brand_name => $fl) {
                $brands = $this->search_brands($fl);
                if ($brands) {
                    foreach ($brands as $b) {
                        $dxc_brand = new DxcBrand;
                        $dxc_brand->dxc_brand_name = $dxc_brand_name;
                        $dxc_brand->brands_id = $b->id;
                        $dxc_brand->save();
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dxc_brands');
    }

    private function search_brands(array $brands)
    {
        return Brand::select('id')->whereIn('name', $brands)->get();
    }
}