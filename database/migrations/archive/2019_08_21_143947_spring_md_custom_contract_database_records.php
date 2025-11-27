<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SpringMdCustomContractDatabaseRecords extends Migration
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
            'Spring Power%Gas'
        )
        ->first();

        $contracts = [
            'spring_md_res_dual_english_20190820.pdf' => [
                'language_id' => 1,
                'commodity' => 'dual',
                'number_of_pages' => 22,
                'signature_info_customer' => '{"9":{"0":"176,514,180,44"},"17":{"0":"173,527,180,44"}}',
            ],
            'spring_md_res_dual_spanish_20190820.pdf' => [
                'language_id' => 2,
                'commodity' => 'dual',
                'number_of_pages' => 24,
                'signature_info_customer' => '{"10":{"0":"166,409,180,44"},"19":{"0":"166,409,180,44"}}',
            ],
            'spring_md_res_electric_english_20190820.pdf' => [
                'language_id' => 1,
                'commodity' => 'electric',
                'number_of_pages' => 13,
                'signature_info_customer' => '{"9":{"0":"176,514,180,44"}}',
            ],
            'spring_md_res_electric_spanish_20190820.pdf' => [
                'language_id' => 2,
                'commodity' => 'electric',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"10":{"0":"166,409,180,44"}}',
            ],
            'spring_md_res_gas_english_20190820.pdf' => [
                'language_id' => 1,
                'commodity' => 'gas',
                'number_of_pages' => 13,
                'signature_info_customer' => '{"9":{"0":"173,527,180,44"}}',
            ],
            'spring_md_res_gas_spanish_20190820.pdf' => [
                'language_id' => 2,
                'commodity' => 'gas',
                'number_of_pages' => 14,
                'signature_info_customer' => '{"10":{"0":"166,409,180,44"}}',
            ]
        ];
        
        foreach ($contracts as $contract => $data) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = $contract;
            $bec->contract_fdf = $this->fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = $data['number_of_pages'];
            $bec->signature_required = 1;
            $bec->signature_required_customer = 1;
            $bec->signature_info = $data['signature_info_customer'];
            $bec->signature_info_customer = $data['signature_info_customer'];
            $bec->signature_required_agent = 0;
            $bec->signature_info_agent = 'none';
            $bec->state_id = 21;
            $bec->channel_id = 1;
            $bec->language_id = $data['language_id'];
            $bec->commodity = $data['commodity'];
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
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([date_af_date])
        /T (date_af_date)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([rate_info_gas_calculated_rate_amount])
        /T (rate_info_gas_calculated_rate_amount)
        >>';
    }
}
