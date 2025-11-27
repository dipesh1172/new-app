<?php

use App\Models\Disposition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class ChangePendingDispositionDescriptionsGlobally extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $disps = Disposition::where('reason', 'Pending')
            ->where('description', 'EzTPV is pending completion')
            ->update([
                'description' => 'Pending completion'
                ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $disps = Disposition::where('reason', 'Pending')
            ->where('description', 'Pending completion')
            ->update([
                'description' => 'EzTPV is pending completion'
                ]);
    }
}
