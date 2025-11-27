<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CopySignatureInfoAndRequirementsToNewColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::withTrashed()->get();

        foreach ($becs as $bec) {
            $update = BrandEztpvContract::where('id', $bec->id)
                ->update([
                    'signature_required_customer' => $bec->signature_required,
                    'signature_info_customer' => $bec->signature_info
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
}
