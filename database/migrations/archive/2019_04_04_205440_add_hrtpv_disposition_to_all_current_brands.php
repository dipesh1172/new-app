<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Disposition;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;

class AddHrtpvDispositionToAllCurrentBrands extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $brands = Disposition::select('brand_id')
            ->distinct()
            ->get();
        
        if (count($brands) > 0) {
            foreach ($brands as $brand) {
                $disp = new Disposition;
                $disp->id = Uuid::uuid4();
                $disp->created_at = Carbon::now();
                $disp->updated_at = Carbon::now();
                $disp->disposition_type_id = 2;
                $disp->disposition_category_id = 3;
                $disp->brand_id = $brand->brand_id;
                $disp->brand_label = '700001';
                $disp->fraud_indicator = 0;
                $disp->reason = 'Agent Needs Clarification';
                $disp->description = 'Agent does not understand or is unable to complete the verification without questions being addressed.';
                $disp->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $disps = Disposition::where('reason', 'Agent Needs Clarification')
            ->delete();
    }
}
