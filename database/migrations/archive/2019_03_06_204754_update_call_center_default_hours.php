<?php


use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class UpdateCallCenterDefaultHours extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $updates = [
            'Sunday' => [
              'open' => '09:00',
              'close' => '23:00',
            ],
            'Monday' => [
              'open' => '07:00',
              'close' => '23:00',
            ],
            'Tuesday' => [
              'open' => '07:00',
              'close' => '23:00',
            ],
            'Wednesday' => [
              'open' => '07:00',
              'close' => '23:00',
            ],
            'Thursday' => [
              'open' => '07:00',
              'close' => '23:00',
            ],
            'Friday' => [
              'open' => '07:00',
              'close' => '23:00',
            ],
            'Saturday' => [
              'open' => '08:00',
              'close' => '23:00',
            ],
        ];
        foreach ($updates as $day => $changes) {
            DB::table('hours_of_operation_defaults')
                ->where('day', $day)
                ->update(
                    [
                        'open' => $changes['open'],
                        'close' => $changes['close'],
                    ]
                );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // there is no down, only Zuul
    }
}
