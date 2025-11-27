<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRpaRetailContractsRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brand = Brand::where(
                'name',
                'RPA Energy'
            )
            ->first();

        $recs = BrandEztpvContract::where('brand_id', $brand->id)
            ->get();

        foreach ($recs as $rec) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $rec->brand_id;
            $bec->document_type_id = $rec->document_type_id;
            $bec->contract_pdf = $rec->contract_pdf;
            $bec->contract_fdf = $rec->contract_fdf;
            $bec->page_size = $rec->page_size;
            $bec->number_of_pages = $rec->number_of_pages;
            $bec->signature_required = $rec->signature_required;
            $bec->signature_info = $rec->signature_info;
            $bec->signature_required_customer = $rec->signature_required_customer;
            $bec->signature_info_customer = $rec->signature_info_customer;
            $bec->signature_required_agent = $rec->signature_required_agent;
            $bec->signature_info_agent = $rec->signature_info_agent;
            $bec->state_id = $rec->state_id;
            $bec->channel_id = 3;
            $bec->commodity = $rec->commodity;
            $bec->save();
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
