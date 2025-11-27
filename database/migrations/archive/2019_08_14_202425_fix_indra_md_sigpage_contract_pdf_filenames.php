<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class FixIndraMdSigpageContractPdfFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'indra_md_sigpage_fixed_electric_english_20190814.pd' => 'indra_md_sigpage_fixed_electric_english_20190814.pdf',
            'indra_md_sigpage_fixed_electric_spanish_20190814.pd' => 'indra_md_sigpage_fixed_electric_spanish_20190814.pdf'
        ];

        foreach ($data as $old => $new) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                $old
            )
            ->update([
                'contract_pdf' => $new
            ]);
        }
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
