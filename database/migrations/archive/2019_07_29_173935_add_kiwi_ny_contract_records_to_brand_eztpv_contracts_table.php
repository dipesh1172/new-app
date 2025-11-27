<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddKiwiNyContractRecordsToBrandEztpvContractsTable extends Migration
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

        $data = [
            'kiwi_ny_dtd_residential_dual_english_20190729.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 13,
                'language_id' => 1,
                'commodity' => 'dual'
            ],
            'kiwi_ny_dtd_residential_dual_spanish_20190729.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 13,
                'language_id' => 2,
                'commodity' => 'dual'
            ],
            'kiwi_ny_dtd_residential_electric_english_20190729.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 12,
                'language_id' => 1,
                'commodity' => 'electric'
            ],
            'kiwi_ny_dtd_residential_electric_spanish_20190729.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 12,
                'language_id' => 2,
                'commodity' => 'electric'
            ],
            'kiwi_ny_dtd_residential_gas_english_20190729.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 12,
                'language_id' => 1,
                'commodity' => 'gas'
            ],
            'kiwi_ny_dtd_residential_gas_spanish_20190729.pdf' => [
                'page_size' => 'Letter',
                'number_of_pages' => 12,
                'language_id' => 2,
                'commodity' => 'gas'
            ]
        ];

        foreach ($data as $contract => $info) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = $contract;
            $bec->contract_fdf = $this->ny_fdf();
            $bec->page_size = $info['page_size'];
            $bec->number_of_pages = $info['number_of_pages'];
            $bec->signature_required = 0;
            $bec->signature_required_customer = 0;
            $bec->signature_required_agent = 0;
            $bec->state_id = 33;
            $bec->channel_id = 1;
            $bec->language_id = $info['language_id'];
            $bec->commodity = $info['commodity'];
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

    private function ny_fdf()
    {
        return '<<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >>';
    }
}
