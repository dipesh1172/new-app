<?php

use App\Models\EventSource;
use App\Models\InteractionType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AddHrtpvSourcesAndTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $es = new EventSource;
        $es->source = 'HRTPV';
        $es->save();
        
        $it = new InteractionType;
        $it->name = 'hrtpv';
        $it->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        EventSource::where('source', 'HRTPV')->delete();

        InteractionType::where('name', 'hrtpv')->delete();
    }
}
