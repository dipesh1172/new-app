<?php

use App\Models\RuntimeSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAgentsRuntimeSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $x = new RuntimeSetting();
        $x->modified_by = 1;
        $x->name = "logrocket_enable";
        $x->value = '1';
        $x->namespace = 'agents';
        $x->save();

        $x = new RuntimeSetting();
        $x->modified_by = 1;
        $x->name = "sentry_enable";
        $x->value = '1';
        $x->namespace = 'agents';
        $x->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        RuntimeSetting::where('namespace', 'agents')->whereIn('name', ['logrocket_enable', 'sentry_enable'])->forceDelete();
    }
}
