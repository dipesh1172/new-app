<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use App\Models\State;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FteDemoSigpageContractRecords extends Migration
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
                'Forward Thinking Energy'
            )
            ->first();

        $channels = [
            1,
            3
        ];

        $commodities = [
            'electric',
            'gas',
            'dual'
        ];

        $states = State::get();

        foreach ($channels as $channel) {
            foreach ($commodities as $commodity) {
                foreach ($states as $state) {
                    $bec = new BrandEztpvContract;
                    $bec->brand_id = $brand->id;
                    $bec->document_type_id = 3;
                    $bec->contract_pdf = 'FTE_Demo_Sigpage_Contract_2019_08_27.pdf';
                    $bec->contract_fdf = 'none';
                    $bec->page_size = 'Letter';
                    $bec->number_of_pages = 5;
                    $bec->signature_required = 0;
                    $bec->signature_required_customer = 0;
                    $bec->signature_info = 'none';
                    $bec->signature_info_customer = 'none';
                    $bec->signature_required_agent = 0;
                    $bec->signature_info_agent = 'none';
                    $bec->state_id = $state->id;
                    $bec->channel_id = $channel;
                    $bec->language_id = 1;
                    $bec->commodity = $commodity;
                    $bec->save();
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
        // unnecessary
    }
}
