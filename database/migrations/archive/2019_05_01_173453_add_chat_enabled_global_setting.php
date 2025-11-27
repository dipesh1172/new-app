<?php

use Carbon\Carbon;
use App\Models\RuntimeSetting;
use Illuminate\Database\Migrations\Migration;

class AddChatEnabledGlobalSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $setting = new RuntimeSetting();
        $setting->name = "chat_enabled";
        $setting->value = "1";
        $setting->namespace = "chat";
        $setting->modified_by = 1;
        $setting->created_at = Carbon::now('America/Chicago');
        $setting->updated_at = Carbon::now('America/Chicago');
        $setting->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
