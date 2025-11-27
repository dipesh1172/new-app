<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Database\Migrations\Migration;

class IndraIlTieredContractRecords extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $brand = Brand::where(
            'name',
            'Indra Energy'
        )
        ->first();

        $contracts = [
            'indra_il_res_dtd_electric_english_tiered_sigpage_20190829.pdf' => 1,
            'indra_il_res_dtd_electric_spanish_tiered_sigpage_20190829.pdf' => 2,
        ];

        foreach ($contracts as $contract => $language) {
            $bec = new BrandEztpvContract();
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 3;
            $bec->contract_pdf = $contract;
            $bec->contract_fdf = $this->il_fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = 5;
            $bec->signature_required = 0;
            $bec->signature_required_customer = 0;
            $bec->signature_info = 'none';
            $bec->signature_info_customer = 'none';
            $bec->signature_required_agent = 0;
            $bec->signature_info_agent = 'none';
            $bec->rate_type_id = 3;
            $bec->state_id = 14;
            $bec->channel_id = 1;
            $bec->language_id = $language;
            $bec->commodity = 'electric';
            $bec->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // unnecessary
    }

    private function il_fdf()
    {
        return '<<
        /V ([rate_info_electric_intro_term])
        /T (rate_info_electric_intro_term)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_term_remaining])
        /T (rate_info_electric_term_remaining)
        >> 
        <<
        /V ([rate_info_electric_custom_data_1])
        /T (rate_info_electric_custom_data_1)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_calculated_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >>';
    }
}
