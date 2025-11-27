<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DynergyIlContractRecords extends Migration
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
        $bec->contract_pdf = 'Dynergy_IL_Electric_with_UDS_2019-02-13';
        $bec->contract_fdf = $this->il_fdf();
        $bec->page_size = 'Letter';
        $bec->number_of_pages = 6;
        $bec->signature_info = 'none';
        $bec->state_id = 14;
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
        $bec = BrandEztpvContract::where('contract_pdf', 'Dynergy_IL_Electric_with_UDS_2019-02-13')->delete();
    }

    private function il_fdf()
    {
        return '<<
        /V ([utility_electric_customer_service])
        /T (utility_phone_number)
        >> 
        <<
        /V ([rate_info_electric_final_rate_500kWh])
        /T (rate_info_electric_final_rate_500kWh)
        >> 
        <<
        /V ([rate_info_electric_date_to])
        /T (rate_info_electric_date_to)
        >> 
        <<
        /V ([rate_info_electric_name])
        /T (rate_info_electric_product_name)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([client_email_address])
        /T (client_email_address)
        >> 
        <<
        /V ([vendor_name])
        /T (vendor_name)
        >> 
        <<
        /V ([rate_info_electric_final_rate_1500kWh])
        /T (rate_info_electric_final_rate_1500kWh)
        >> 
        <<
        /V ([rate_info_electric_final_rate_1000kWh])
        /T (rate_info_electric_final_rate_1000kWh)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([date])
        /T (agent_id_date)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name)
        >> 
        <<
        /V ([vendor_phone_number])
        /T (vendor_phone_number)
        >> 
        <<
        /V ([agent_id])
        /T (agent_id)
        >> 
        <<
        /V ([client_phone_number])
        /T (client_phone_number)
        >>';
    }
}
