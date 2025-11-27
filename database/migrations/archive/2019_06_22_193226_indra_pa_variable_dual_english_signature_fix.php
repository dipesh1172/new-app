<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndraPaVariableDualEnglishSignatureFix extends Migration
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
            'indra_pa_variable_dual_english%'
        )
        ->update([
            'signature_info' => '{"6":{"0":"116,692,117,22"}, "13":{"0":"120,616,122,22"}}',
            'signature_info_customer' => '{"6":{"0":"116,692,117,22"}, "13":{"0":"120,616,122,22"}}'
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
}
