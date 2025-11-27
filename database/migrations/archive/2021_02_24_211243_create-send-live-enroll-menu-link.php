<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\MenuLink;

class CreateSendLiveEnrollMenuLink extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $support = MenuLink::where('name', 'Support')->first();

        if ($support) {
            $x = new MenuLink();
            $x->name = 'Send Live Enroll';
            $x->icon = 'fa-paper-plane-o';
            $x->url = '/support/send_live_enroll';
            $x->position = 5;
            $x->parent_id = $support->id;
            $x->role_permissions = 1;
            $x->save();
        }
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
