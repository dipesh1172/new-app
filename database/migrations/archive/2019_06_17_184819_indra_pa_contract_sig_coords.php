<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaContractSigCoords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_dual_english_20190617.pdf'
        )
        ->update([
            'signature_info' => '{"6":{"0":"116,653,117,22"}, "13":{"0":"120,616,122,22"}}',
            'signature_info_customer' => '{"6":{"0":"116,653,117,22"}, "13":{"0":"120,616,122,22"}}',
            'signature_info_agent' => '{"6":{"0":"116,714,117,22"}, "13":{"0":"120,642,122,22"}}'
        ]);

        $contracts = BrandEztpvContract::where(
            'contract_pdf',
            'indra_pa_variable_dual_spanish_20190617.pdf'
        )
        ->update([
            'signature_info' => '{"6":{"0":"124,726,109,22"}, "13":{"0":"122,628,120,22"}}',
            'signature_info_customer' => '{"6":{"0":"124,726,109,22"}, "13":{"0":"122,628,120,22"}}',
            'signature_info_agent' => '{"6":{"0":"124,752,109,22"}, "13":{"0":"122,654,121,22"}}'
        ]);
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
