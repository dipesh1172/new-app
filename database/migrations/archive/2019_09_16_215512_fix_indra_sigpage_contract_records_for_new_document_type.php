<?php

use App\Models\Brand;
use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FixIndraSigpageContractRecordsForNewDocumentType extends Migration
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
            'Indra Energy'
        )
        ->first();

        $becs = BrandEztpvContract::where(
            'brand_id',
            $brand->id
        )
        ->where(
            'contract_pdf',
            'LIKE',
            '%sigpage%'
        )
        ->update([
            'document_type_id' => 4
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
