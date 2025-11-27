<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDyngeyPaContractRecordsToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec = new BrandEztpvContract;
        $bec->brand_id = '306e48d7-6ed2-41d2-a355-55adf79506a5';
        $bec->document_type_id = 1;
        $bec->contract_pdf = 'Dynergy_PA_Electric_2019-02-20.pdf';
        $bec->contract_fdf = $this->pa_fdf();
        $bec->page_size = 'Letter';
        $bec->number_of_pages = 8;
        $bec->signature_info = '{"7":{"0":"100,686,323,46"}}';
        $bec->state_id = 39;
        $bec->channel_id = 1;
        $bec->commodity = 'electric';
        $bec->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    private function pa_fdf()
    {
        return '<<
        /V ([utility_electric_address_full])
        /T (utility_address)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name)
        >> 
        <<
        /V ([client_email_address])
        /T (client_email_address)
        >> 
        <<
        /V ([utility_electric_customer_service])
        /T (utility_phone_number)
        >> 
        <<
        /V ([date])
        /T (signature_date)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ()
        /T (signature)
        >> 
        <<
        /V ([utility_electric_website])
        /T (utility_website)
        >> 
        <<
        /V ([rate_info_electric_date_to])
        /T (rate_info_electric_date_to)
        >> 
        <<
        /V ([client_phone_number])
        /T (client_phone-number)
        >> 
        <<
        /V ([client_phone_number])
        /T (client_phone_number)
        >>';
    }
}
