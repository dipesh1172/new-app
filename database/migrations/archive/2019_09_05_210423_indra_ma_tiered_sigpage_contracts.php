<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraMaTieredSigpageContracts extends Migration
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
            'Indra Energy'
        )
            ->first();

        $contracts = [
            'indra_ma_res_dtd_electric_english_tiered_sigpage_20190905.pdf' => [
                'language_id' => 1,
                'number_of_pages' => 6
            ],
            'indra_ma_res_dtd_electric_spanish_tiered_sigpage_20190905.pdf' => [
                'language_id' => 2,
                'number_of_pages' => 7
            ]
        ];

        $channels = [
            1,
            3
        ];

        foreach ($channels as $channel) {
            foreach ($contracts as $contract => $data) {
                $bec = new BrandEztpvContract;
                $bec->brand_id = $brand->id;
                $bec->document_type_id = 3;
                $bec->contract_pdf = $contract;
                $bec->page_size = 'Letter';
                $bec->number_of_pages = $data['number_of_pages'];
                $bec->signature_required = 1;
                $bec->signature_required_customer = 1;
                $bec->signature_info = 'none';
                $bec->signature_info_customer = 'none';
                $bec->signature_required_agent = 1;
                $bec->signature_info_agent = 'none';
                $bec->state_id = 22;
                $bec->channel_id = $channel;
                $bec->language_id = $data['language_id'];
                $bec->commodity = 'electric';
                $bec->save();
            }
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
