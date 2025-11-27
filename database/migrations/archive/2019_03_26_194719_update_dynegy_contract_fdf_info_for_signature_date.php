<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class UpdateDynegyContractFdfInfoForSignatureDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bec1 = BrandEztpvContract::where('contract_pdf', 'Dynegy_IL_Electric_with_UDS_2019-02-22.pdf')
            ->update([
                'contract_fdf' => $this->il_fdf()
            ]);

        $bec1 = BrandEztpvContract::where('contract_pdf', 'Dynegy_PA_Electric_with_UDS_2019-02-20.pdf')
            ->update([
                'contract_fdf' => $this->pa_fdf()
            ]);    
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

    private function il_fdf()
    {
        return '<<
        /V ()
        /T (signature)
        >> 
        <<
        /V ([utility_electric_customer_service])
        /T (utility_phone_number)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
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
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_final_rate_1500kWh)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_final_rate_1000kWh)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
        >> 
        <<
        /V ([date])
        /T (signature_date)
        >> 
        <<
        /V ([utility_name_all])
        /T (utility_name)
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
