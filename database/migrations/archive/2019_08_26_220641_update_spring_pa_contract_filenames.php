<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSpringPaContractFilenames extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $becs = BrandEztpvContract::where(
            'contract_pdf',
            'LIKE',
            'spring_pa%electric%'
        )
        ->where(
            'contract_pdf',
            'LIKE',
            'spring_pa%dual%'
        )
        ->get();
        
        foreach ($becs as $bec) {
            $new = str_replace('05', '26', $bec->contract_pdf);

            $update = BrandEztpvContract::where(
                'contract_pdf',
                $bec->contract_pdf
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
        // unnecessary
    }
}
