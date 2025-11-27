<?php

use App\Models\EventSource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceToOutboundQueue extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('outbound_call_queue', function (Blueprint $table) {
            $table->integer('event_source_id')->default(EventSource::where('source', 'Digital')->first()->id);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('outbound_call_queue', function (Blueprint $table) {
            $table->dropColumn('event_source_id');
        });
    }
}
