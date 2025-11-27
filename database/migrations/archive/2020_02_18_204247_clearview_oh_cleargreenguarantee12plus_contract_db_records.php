<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ClearviewOhCleargreenguarantee12plusContractDbRecords extends Migration
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
            'Clearview Energy'
        )
            ->first();

        $channels = [
            1,
            3
        ];

        foreach ($channels as $channel) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 3;
            $bec->document_file_type_id = 2;
            $bec->contract_pdf = 'clearview_oh_ClearGreenGuarantee12Plus_sigpage_20200218.docx';
            $bec->contract_fdf = 'None';
            $bec->signature_required = 1;
            $bec->signature_required_customer = 1;
            $bec->signature_info = 'None';
            $bec->signature_info_customer = 'None';
            $bec->signature_required_agent = 1;
            $bec->signature_info_agent = 'None';
            $bec->rate_type_id = 1;
            $bec->state_id = 36;
            $bec->channel_id = $channel;
            $bec->market_id = 1;
            $bec->language_id = 1;
            $bec->commodity = 'electric';
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
