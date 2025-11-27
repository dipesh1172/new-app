<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MakeRecordsForIndraMdElectricSigpageContracts extends Migration
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
            'LIKE',
            'Indra Energy'
        )
        ->first();

        $contracts = [
            'indra_md_sigpage_fixed_electric_english_20190814.pd' => [
                'language_id' => 1,
            ],
            'indra_md_sigpage_fixed_electric_spanish_20190814.pd' => [
                'language_id' => 2,
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 3;
            $bec->contract_pdf = $contract;
            $bec->contract_fdf = $this->md_electric_fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = 5;
            $bec->signature_info = 'none';
            $bec->signature_info_customer = 'none';
            $bec->signature_info_agent = 'none';
            $bec->state_id = 21;
            $bec->channel_id = 1;
            $bec->language_id = $data['language_id'];
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

    private function md_electric_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_calculated_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >>';
    }
}
