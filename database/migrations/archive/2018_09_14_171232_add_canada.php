<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddCanada extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'states',
            function (Blueprint $table) {
                $table->integer('country_id')->default(1)->after('state_abbrev');
            }
        );

        Schema::table(
            'zips',
            function (Blueprint $table) {
                $table->string('zip', 6)->change();
            }
        );

        DB::table('rate_uoms')
            ->insert(
                [
                    [
                        'id' => 6,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'uom' => 'gj',
                    ],
                ]
            );

        DB::table('states')
            ->insert(
                [
                    [
                        'id' => 52,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Alberta',
                        'state_abbrev' => 'AB',
                        'status' => 1,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 53,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'British Columbia',
                        'state_abbrev' => 'BC',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 54,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Manitoba',
                        'state_abbrev' => 'MB',
                        'status' => 1,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 55,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'New Brunswich',
                        'state_abbrev' => 'NB',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 56,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Newfoundland and Labrador',
                        'state_abbrev' => 'NL',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 57,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Northwest Territories',
                        'state_abbrev' => 'NT',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 58,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Nova Scotia',
                        'state_abbrev' => 'NS',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 59,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Nunavut Territory',
                        'state_abbrev' => 'NU',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 60,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Ontario',
                        'state_abbrev' => 'ON',
                        'status' => 1,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 61,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Prince Edward Island',
                        'state_abbrev' => 'PE',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 62,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Quebec',
                        'state_abbrev' => 'QC',
                        'status' => 1,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 63,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Saskatchewan',
                        'state_abbrev' => 'SK',
                        'status' => 0,
                        'country_id' => 2,
                    ],
                    [
                        'id' => 64,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'name' => 'Yukon Territory',
                        'state_abbrev' => 'YT',
                        'status' => 0,
                        'country_id' => 2,
                    ],
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
        Schema::table(
            'states',
            function (Blueprint $table) {
                $table->removeColumn('country_id');
            }
        );

        Schema::table(
            'zips',
            function (Blueprint $table) {
                $table->string('zip', 5)->change();
            }
        );
    }
}
