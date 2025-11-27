<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaTieredContractSignatureUpdate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $dual = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'indra_pa_tiered_dual_english%'
        )
        ->update([
            'signature_info' => '{"6":{"0":"121,654,122,22"}, "13":{"0":"126,611,122,24"}}',
            'signature_info_customer' => '{"6":{"0":"121,654,122,22"}, "13":{"0":"126,611,122,24"}}',
            'signature_info_agent' => '{"6":{"0":"121,680,122,22"}, "13":{"0":"126,635,122,24"}}'
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
