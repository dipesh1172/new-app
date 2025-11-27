<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FillInNewFieldsOnRpaNjContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_nj_allMarket_allChannel_dual_variable_english_custom_2020026.docx',
            'rpa_nj_allMarket_allChannel_dual_variable_spanish_custom_2020026.docx'
        ];

        foreach ($contracts as $contract) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                $contract
            )
            ->get();
    
            foreach ($becs as $bec) {
                $new = BrandEztpvContract::find($bec->id);
                $new->file_name = $bec->contract_pdf;
                $new->original_contract = $becs[0]->id;
                $new->save();
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
