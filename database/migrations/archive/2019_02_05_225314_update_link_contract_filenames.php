<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateLinkContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 1)
            ->where('state_id', 52)
            ->where('channel_id', 2)
            ->update([
                'contract_pdf' => 'Link_TM_dual_2019_02_05.pdf'
            ]);

        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 1)
            ->where('state_id', 52)
            ->where('channel_id', 2)
            ->update([
                'contract_pdf' => 'Link_TM_site_schedule_2019_02_05.pdf'
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 1)
            ->where('state_id', 52)
            ->where('channel_id', 2)
            ->update([
                'contract_pdf' => 'Link_TM_dual_2018_11_16.pdf'
            ]);

        $documents = BrandEztpvContract::where('brand_id', '776c7324-32f9-4163-b6ed-f1d13df92aca')
            ->where('document_type_id', 1)
            ->where('state_id', 52)
            ->where('channel_id', 2)
            ->update([
                'contract_pdf' => 'Link_TM_site_schedule_2018_11_16.pdf'
            ]);
    }
}
