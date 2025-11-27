<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FixLoginLandings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $fixThese = DB::table('login_landing')
            ->select('vendors_id', DB::raw('count(*) as cnt'))
            ->whereNull('deleted_at')
            ->groupBy('vendors_id')
            ->get()
            ->where('cnt', '>', 1)
            ->flatten();

        foreach ($fixThese as $fixThis) {
            $fixing = DB::table('login_landing')
                ->where('vendors_id', $fixThis->vendors_id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'ASC')
                ->get();

            $correctOne = $fixing->shift();

            $fixing->each(function ($item) use ($correctOne) {
                $now = Carbon::now();
                DB::table('login_landing_ips')
                    ->where('login_landing_id', $item->id)
                    ->update([
                        'login_landing_id' => $correctOne->id
                    ]);

                DB::table('login_landing')
                    ->where('id', $item->id)
                    ->update([
                        'deleted_at' => $now
                    ]);
            });
        }
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
