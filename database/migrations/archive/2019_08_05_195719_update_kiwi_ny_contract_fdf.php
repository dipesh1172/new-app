<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateKiwiNyContractFdf extends Migration
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
            'kiwi_ny%'
        )->update([
            'contract_fdf' => $this->fdf()
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

    private function fdf()
    {
        return '<<
        /V ([auth_fullname])
        /T (auth_fullname)
        >> 
        <<
        /V ([date])
        /T (date)
        >> 
        <<
        /V ()
        /T (signature_customer)
        >> 
        <<
        /V ([auth_fullname_fl])
        /T (auth_fullname_fl)
        >>';
    }
}
