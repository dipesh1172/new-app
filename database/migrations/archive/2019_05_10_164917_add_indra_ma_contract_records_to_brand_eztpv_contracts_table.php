<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndraMaContractRecordsToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $fixedContracts = [
            'english' => 'indra_ma_dtd_res_english_fixed_electric_contract_20190509.pdf',
            'spanish' => 'indra_ma_dtd_res_spanish_fixed_electric_contract_20190509.pdf'
        ];

        $tieredContracts = [
            'english' => 'indra_ma_dtd_res_english_tiered_electric_contract_20190509.pdf',
            'spanish' => 'indra_ma_dtd_res_spanish_tiered_electric_contract_20190509.pdf'
        ];
        
        foreach ($fixedContracts as $language => $contract)
        {
            $this->addRecordToDb(
                $contract,
                'fixed_fdf',
                $language
            );
        }

        foreach ($tieredContracts as $language => $contract)
        {
            $this->addRecordToDb(
                $contract,
                'tiered_fdf',
                $language
            );
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

    private function addRecordToDb($contract, $fdf, $language)
    {
        $bec = new BrandEztpvContract;
        $bec->brand_id = '4e65aab8-4dae-48ef-98ee-dd97e16cbce6';
        $bec->document_type_id = 1;
        $bec->contract_pdf = $contract;
        $bec->contract_fdf = $this->{$fdf}();
        $bec->page_size = 'Letter';
        switch ($language)
        {
            case 'english':
                $bec->number_of_pages = 7;
                break;

            case 'spanish':
                $bec->number_of_pages = 8;
                break;
        }
        $bec->signature_required = 0;
        $bec->signature_info = 'none';
        $bec->state_id = 22;
        $bec->channel_id = 1;
        $bec->language_id = 1;
        $bec->commodity = 'electric';
        $bec->save();
    }

    private function fixed_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee_short])
        /T (text_computed_electric_cancellation_fee_short)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage_2)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name_all)
        >>';
    }

    private function tiered_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([text_computed_electric_cancellation_fee_short])
        /T (text_computed_electric_cancellation_fee_short)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage_2)
        >> 
        <<
        /V ([rate_info_electric_tiered_rate_amount])
        /T (rate_info_electric_tiered_rate_amount)
        >> 
        <<
        /V ([green_percentage])
        /T (green_percentage)
        >> 
        <<
        /V ([computed_multiline_auth_fullname_fl_plus_service_address])
        /T (computed_multiline_auth_fullname_fl_plus_service_address)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name_all)
        >>';
    }
}
