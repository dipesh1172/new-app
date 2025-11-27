<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Disposition;
use Ramsey\Uuid\Uuid;
use Carbon\Carbon;

class AddCustomerListDispositions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $brands = Disposition::select('brand_id')
            ->distinct()
            ->get();

        if (count($brands) > 0) {
            foreach ($brands as $brand) {
                $disp = new Disposition();
                $disp->id = Uuid::uuid4();
                $disp->created_at = Carbon::now();
                $disp->updated_at = Carbon::now();
                $disp->disposition_type_id = 1;
                $disp->disposition_category_id = 7;
                $disp->brand_id = $brand->brand_id;
                $disp->brand_label = '200002';
                $disp->fraud_indicator = 0;
                $disp->reason = 'Existing Customer';
                $disp->description = 'Existing Customer/Account number check was triggered.';
                $disp->save();

                $disp = new Disposition();
                $disp->id = Uuid::uuid4();
                $disp->created_at = Carbon::now();
                $disp->updated_at = Carbon::now();
                $disp->disposition_type_id = 1;
                $disp->disposition_category_id = 7;
                $disp->brand_id = $brand->brand_id;
                $disp->brand_label = '200003';
                $disp->fraud_indicator = 0;
                $disp->reason = 'Blacklisted Account';
                $disp->description = 'Existing Customer/Blacklisted Account number check was triggered.';
                $disp->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
