<?php

use App\Models\BrandServiceType;
use Illuminate\Database\Migrations\Migration;

class AddEsiIdLookupToBrandServiceTypes extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $bst = new BrandServiceType();
        $bst->slug = 'esi_id_lookup';
        $bst->name = 'ESI ID Lookup';
        $bst->description = 'Show/Hide ESI ID Lookup business rule';
        $bst->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // unnecessary
    }
}
