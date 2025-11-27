<?php

use App\Models\ZipCode;
use Illuminate\Database\Migrations\Migration;

class AddPlainfieldZip extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $z = new ZipCode();
        $z->zip = '60586';
        $z->city = 'Plainfield';
        $z->state = 'IL';
        $z->county = 'WILL';
        $z->lat = 41.568;
        $z->lon = -88.239;
        $z->timezone = -6;
        $z->dst = 1;
        $z->country = 1;
        $z->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
