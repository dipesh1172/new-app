<?php

use App\Models\SignatureType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DefaultSignatureTypesRecords extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $types = [
            'customer',
            'agent',
        ];

        foreach ($types as $type) {
            $st = new SignatureType;
            $st->type = $type;
            $st->save();
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
