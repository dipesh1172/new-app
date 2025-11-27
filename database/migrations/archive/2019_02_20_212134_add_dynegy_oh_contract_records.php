<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDynegyOhContractRecords extends Migration
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
        $bec->contract_pdf = 'Dynergy_OH_Electric_2019-02-20.pdf';
        $bec->contract_fdf = $this->oh_fdf();
        $bec->page_size = 'Letter';
        $bec->number_of_pages = 6;
        $bec->signature_info = '{"6":{"0":"155,674,418,27"}}';
        $bec->state_id = 36;
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

    private function oh_fdf()
    {
        return '<<
        /V ([client_email_address])
        /T (client_email_address)
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
        /V ([rate_info_electric_date_to])
        /T (rate_info_electric_date_to)
        >> 
        <<
        /V ([client_phone_number])
        /T (client_phone_number)
        >>';
    }
}
