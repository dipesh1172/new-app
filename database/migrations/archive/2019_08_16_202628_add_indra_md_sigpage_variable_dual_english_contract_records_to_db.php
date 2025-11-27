<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndraMdSigpageVariableDualEnglishContractRecordsToDb extends Migration
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
            'indra_md_sigpage_variable_dual_english_20190816.pdf' => [
                'language_id' => 1,
            ],
            'indra_md_sigpage_variable_dual_spanish_20190816.pdf' => [
                'language_id' => 2,
            ]
        ];

        $commodities = [
            'dual',
            'electric',
            'gas'
        ];
        
        foreach ($commodities as $commodity)
        {
            foreach ($contracts as $contract => $data) {
                $bec = new BrandEztpvContract;
                $bec->brand_id = $brand->id;
                $bec->document_type_id = 3;
                $bec->contract_pdf = $contract;
                $bec->contract_fdf = $this->fdf();
                $bec->page_size = 'Letter';
                $bec->number_of_pages = 5;
                $bec->signature_info = 'none';
                $bec->signature_info_customer = 'none';
                $bec->signature_info_agent = 'none';
                $bec->state_id = 21;
                $bec->channel_id = 1;
                $bec->language_id = $data['language_id'];
                $bec->commodity = $commodity;
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

    private function fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_gas_calculated_intro_rate_amount])
        /T (rate_info_gas_calculated_intro_rate_amount)
        >> 
        <<
        /V ([rate_info_electric_calculated_intro_rate_amount])
        /T (rate_info_electric_calculated_intro_rate_amount)
        >>';
    }
}
