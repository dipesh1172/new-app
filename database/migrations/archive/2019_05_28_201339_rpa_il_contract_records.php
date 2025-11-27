<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaIlContractRecords extends Migration
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
                'RPA Energy'
            )
            ->first();

        $commodities = [
            'dual',
            'electric',
            'gas'
        ];

        foreach ($commodities as $commodity) {
            $bec = new BrandEztpvContract;
            $bec->brand_id = $brand->id;
            $bec->document_type_id = 1;
            $bec->contract_pdf = 'rpa_il_dtd_residential_dual_2019_05_28.pdf';
            $bec->contract_fdf = $this->rpa_il_fdf();
            $bec->page_size = 'Letter';
            $bec->number_of_pages = 2;
            $bec->signature_required = 1;
            $bec->signature_info = '{"1":{"0":"36,633,144,21"}}';
            $bec->state_id = 14;
            $bec->channel_id = 1;
            $bec->commodity = $commodity;
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

    private function rpa_il_fdf()
    {
        return '<<
        /V ([address_service])
        /T (address_service)
        >> 
        <<
        /V ([utility_electric_name])
        /T (utility_electric_name)
        >> 
        <<
        /V ([confirmation_code])
        /T (confirmation_code)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_fullname)
        >> 
        <<
        /V ()
        /T (signature_agent)
        >> 
        <<
        /V ([account_number_gas])
        /T (account_number_gas)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >> 
        <<
        /V ([utility_gas_name])
        /T (utility_gas_name)
        >> 
        <<
        /V ([phone_number])
        /T (phone_number)
        >> 
        <<
        /V ([bill_fullname])
        /T (bill_fullname)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([city_state_zip_service])
        /T (city_state_zip_service)
        >> 
        <<
        /V ([email_address])
        /T (email_address)
        >> 
        <<
        /V ([agent_id])
        /T (agent_id)
        >>';
    }
}
