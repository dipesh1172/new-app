<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class KiwiOhSpanishContracts extends Migration
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
            'Kiwi%'
        )
        ->first();

        $commodities = [
            'electric' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_electric_spanish_20190703.pdf',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"10":{"0":"332,364,180,44"}}'
            ],
            'gas' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_gas_spanish_20190703.pdf',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"10":{"0":"332,364,180,44"}}'
            ],
            'dual' => [
                'contract_pdf' => 'kiwi_oh_dtd_residential_dual_spanish_20190703.pdf',
                'number_of_pages' => 16,
                'signature_info_customer' => '{"11":{"0":"332,364,180,44"}}'
            ]
        ];

        foreach ($commodities as $key => $commodity) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = $commodity['contract_pdf'];
            $bec->contract_fdf = $this->dual_fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = $commodity['number_of_pages'];
            $bec->signature_required = 1;
            $bec->signature_required_customer = 1;
            $bec->signature_info = $commodity['signature_info_customer'];
            $bec->signature_info_customer = $commodity['signature_info_customer'];
            $bec->signature_required_agent = 0;
            $bec->state_id = 36;
            $bec->channel_id = 1;
            $bec->language_id = 2;
            $bec->commodity = $key;
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

    private function dual_fdf()
    {
        return '<<
        /V ([rate_info_gas_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([commodity_type_all])
        /T (commodity_type_all)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([utility_account_number_all])
        /T (utility_account_number_all)
        >>';
    }
}
