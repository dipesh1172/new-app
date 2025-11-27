<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class SetAgentSignatureInfoForRpaContractRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $contracts = [
            'rpa_il_dtd_residential_dual_2019_05_28.pdf',
            'rpa_nj_dtd_residential_dual_2019_05_28.pdf',
            'rpa_oh_dtd_residential_dual_2019_05_29.pdf',
            'rpa_pa_dtd_residential_dual_2019_05_31.pdf'
        ];

        foreach ($contracts as $contract) {
            $bec = BrandEztpvContract::where('contract_pdf', $contract)
                ->update([
                    'signature_required_agent' => 1,
                    'signature_info_agent' => $this->sig_info($contract)
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
        // unnecessary
    }

    private function sig_info($contract)
    {
        switch ($contract)
        {
            case 'rpa_il_dtd_residential_dual_2019_05_28.pdf':
                return '{"1":{"0":"36,890,144,21"}}';
                break;
            case 'rpa_nj_dtd_residential_dual_2019_05_28.pdf':
                return '{"1":{"0":"35,884,144,21"}}';
                break;
            case 'rpa_oh_dtd_residential_dual_2019_05_29.pdf':
                return '{"1":{"0":"16,339,144,21"}}';
                break;
            case 'rpa_pa_dtd_residential_dual_2019_05_31.pdf':
                return '{"1":{"0":"36,859,144,21"}}';
                break;
        }
        
    }
}
