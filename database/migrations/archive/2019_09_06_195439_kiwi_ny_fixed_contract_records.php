<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class KiwiNyFixedContractRecords extends Migration
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
            'Kiwi Energy%'
        )
            ->first();

        $contracts = [
            'kiwi_ny_dtd_residential_fixed_electric_english_20190906.pdf' => [
                'language_id' => 1,
                'signature_info_customer' => '{"6":{"0":"152,328,180,44"}}'
            ],
            'kiwi_ny_dtd_residential_fixed_electric_spanish_20190906.pdf' => [
                'language_id' => 2,
                'signature_info_customer' => '{"6":{"0":"136,560,180,44"}}'
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = $contract;
            $bec->page_size = 'Letter';
            $bec->number_of_pages = 12;
            $bec->signature_required = 1;
            $bec->signature_required_customer = 1;
            $bec->signature_info = $data['signature_info_customer'];
            $bec->signature_info_customer = $data['signature_info_customer'];
            $bec->signature_required_agent = 0;
            $bec->rate_type_id = 1;
            $bec->state_id = 33;
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

    private function fdf()
    {
        return '<<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_calculated_rate_amount])
        /T (rate_info_electric_calculated_rate_amount)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([rate_info_electric_term_uom])
        /T (rate_info_electric_term_uom)
        >>';
    }
}
