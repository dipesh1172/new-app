<?php

use App\Models\BrandServiceType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddContractPreviewServiceToBrandServiceTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $bst = new BrandServiceType();
        $bst->slug = 'contract_preview';
        $bst->name = 'Contract Preview';
        $bst->description = 'Generate and show preview contracts in EzTPV';
        $bst->save();
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
