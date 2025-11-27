<?php

use App\Models\BrandEztpvContract;
use App\Models\Channel;
use App\Models\State;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFteDemoContractRowsToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $old = BrandEztpvContract::where('brand_id', '04B0F894-172C-470F-813B-4F58DBD35BAE')
            ->where('state_id', 31)
            ->delete();

        $channels = [
            'DTD',
            'Retail'
        ];

        $commodities = [
            'electric',
            'gas',
            'dual'
        ];

        $states = [
            'DE',
            'IL',
            'NJ',
            'NY',
            'CA',
            'CT',
            'DC',
            'ME',
            'MD',
            'MA',
            'MI',
            'NH',
            'OH',
            'PA',
            'RI',
            'TX',
            'VA'
        ];

        foreach ($channels as $channel) {
            foreach ($commodities as $commodity) {
                foreach ($states as $state) {
                    $contract = new BrandEztpvContract;
                    $contract->brand_id = '04B0F894-172C-470F-813B-4F58DBD35BAE';
                    $contract->document_type_id = 1;
                    $contract->contract_pdf = 'FTE_Demo_Contract_2019_02_06.pdf';
                    $contract->contract_fdf = $this->contractFdf();
                    $contract->page_size = 'Letter';
                    $contract->number_of_pages = 1;
                    $contract->signature_info = '{"1":{"0":"262,642,141,20"}}';
                    $contract->state_id = $this->getState($state);
                    $contract->channel_id = $this->getChannel($channel);
                    $contract->commodity = $commodity;
                    $contract->save();    
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $rows = BrandEztpvContract::where('contract_pdf', 'FTE_Demo_Contract_2019_02_06')
            ->delete();
    }

    public function getChannel($channel)
    {
        $channel = Channel::select('id')
            ->where('channel', $channel)
            ->first();

        return $channel->id;
    }

    public function getState($state_abbrev)
    {
        $state = State::select('id')
            ->where('state_abbrev', $state_abbrev)
            ->first();

        return $state->id;
    }

    public function contractFdf()
    {
        return '<<
        /V ([state_service])
        /T (service_state)
        >> 
        <<
        /V ([city_service])
        /T (service_city)
        >> 
        <<
        /V ([rate_info_gas_term])
        /T (rate_info_gas_term)
        >> 
        <<
        /V ([agent_fullname])
        /T (agent_fullname)
        >> 
        <<
        /V ([account_number_gas])
        /T (account_number_gas)
        >> 
        <<
        /V ([address_service])
        /T (service_address)
        >> 
        <<
        /V ([rate_info_gas_rate_amount])
        /T (rate_info_gas_rate_amount)
        >> 
        <<
        /V ([account_number_electric])
        /T (account_number_electric)
        >> 
        <<
        /V ([rate_info_electric_term])
        /T (rate_info_electric_term)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ([rate_info_electric_rate_amount])
        /T (rate_info_electric_rate_amount)
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
        /V ()
        /T (customer_signature)
        >> 
        <<
        /V ([date])
        /T (customer_signature_date)
        >> 
        <<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([zip_service])
        /T (service_zip)
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
