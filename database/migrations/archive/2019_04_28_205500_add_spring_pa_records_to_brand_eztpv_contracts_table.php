<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSpringPaRecordsToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = new BrandEztpvContract;
        $bec->brand_id = 'd758c445-6144-4b9c-b683-717aadec83aa';
        $bec->document_type_id = 1;
        $bec->contract_pdf = 'spring_pa_res_electric_20190428.pdf';
        $bec->contract_fdf = $this->electric_fdf();
        $bec->page_size = 'Letter';
        $bec->number_of_pages = 14;
        $bec->signature_required = 0;
        $bec->signature_info = 'none';
        $bec->state_id = 39;
        $bec->channel_id = 1;
        $bec->commodity = 'electric';
        $bec->save();

        $bec2 = new BrandEztpvContract;
        $bec2->brand_id = 'd758c445-6144-4b9c-b683-717aadec83aa';
        $bec2->document_type_id = 1;
        $bec2->contract_pdf = 'spring_pa_res_gas_20190428.pdf';
        $bec2->contract_fdf = $this->gas_fdf();
        $bec2->page_size = 'Letter';
        $bec2->number_of_pages = 14;
        $bec2->signature_required = 0;
        $bec2->signature_info = 'none';
        $bec2->state_id = 39;
        $bec2->channel_id = 1;
        $bec2->commodity = 'gas';
        $bec2->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // not necessary
    }

    private function electric_fdf()
    {
        return '<<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date_af_date)
        >>';
    }

    private function gas_fdf()
    {
        return '<<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([rate_info_gas_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([date])
        /T (date_af_date)
        >>';
    }
}
