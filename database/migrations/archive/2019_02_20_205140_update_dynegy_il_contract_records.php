<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateDynegyIlContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contract = BrandEztpvContract::where('contract_pdf', 'Dynegy_IL_Electric_with_UDS_2019-02-13')
            ->first();
        $contract->contract_pdf = 'Dynegy_IL_Electric_with_UDS_2019-02-20.pdf';
        $contract->contract_fdf = $this->il_fdf();
        $contract->signature_info = '{"5":{"0":"104,245,220,38"}}';
        $contract->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // code
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
        /V ([date])
        /T (date)
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
        /T (signature_date)
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
