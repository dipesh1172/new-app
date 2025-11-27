<?php

use App\Models\RefType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDataToRefTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            'event',
            'digital'
        ];

        foreach ($data as $datum) {
            $rt = new RefType();
            $rt->ref_type = $datum;
            $rt->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ref_types', function (Blueprint $table) {
            //
        });
    }
}
