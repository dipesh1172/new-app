<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Eztpv;

class CleanupOldSignatureLocation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Eztpv::whereNotNull(
            'signature'
        )->withTrashed()->update(
            [
                'signature' => null,
                'signature_date' => null,
                'signature2' => null,
                'signature2_date' => null,
            ]
        );
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
