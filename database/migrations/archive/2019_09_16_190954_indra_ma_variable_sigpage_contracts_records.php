<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraMaVariableSigpageContractsRecords extends Migration
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
            'indra_ma_res_dtd_variable_electric_english_sigpage_20190916.pdf' => [
                'number_of_pages' => 6,
                'language_id' => 1
            ],
            'indra_ma_res_dtd_variable_electric_spanish_sigpage_20190916.pdf' => [
                'number_of_pages' => 7,
                'language_id' => 2
            ]
        ];

        foreach ($contracts as $contract => $data) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 3;
            $bec->contract_pdf = $contract;
            $bec->contract_fdf = $this->fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = $data['number_of_pages'];
            $bec->signature_required = 0;
            $bec->signature_info = 'none';
            $bec->signature_required_customer = 0;
            $bec->signature_info_customer = 'none';
            $bec->signature_required_agent = 0;
            $bec->signature_info_agent = 'none';
            $bec->rate_type_id = 3;
            $bec->state_id = 22;
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

    private function fdf() {
        return '<<
        /V ([date])
        /T (date)
        >>';
    }
}
