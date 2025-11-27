<?php

use App\Models\BrandTaskQueue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRetpvQueues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $eng = new BrandTaskQueue();
        $spa = new BrandTaskQueue();
        $eng->task_queue = 'ReTPV - English';
        $spa->task_queue = 'ReTPV - Spanish';

        if (config('app.env') != 'production') {
            // staging
            $eng->task_queue_sid = 'WQc4b8eb5827f76094abb260aa3d3d8bbf';
            $spa->task_queue_sid = 'WQba76c48686dfb763cba531bd52f40adb';
        } else {
            // production
            $eng->task_queue_sid = 'WQ6149656345af55af908d0ca3f286e70b';
            $spa->task_queue_sid = 'WQ71193784e15da8a14c7dcb03e7fcc578';
        }
        $eng->save();
        $spa->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('brand_task_queues')->where('task_queue', 'LIKE', 'ReTPV%')->delete();
    }
}
