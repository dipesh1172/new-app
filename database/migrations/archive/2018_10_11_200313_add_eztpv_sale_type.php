<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\Models\EztpvSaleType;
use App\Models\Eztpv;

class AddEztpvSaleType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'eztpvs', function (Blueprint $table) {
                $table->integer('eztpv_sale_type_id')
                    ->nullable()->after('brand_id');
            }
        );

        $eztpvs = Eztpv::get();

        foreach ($eztpvs as $eztpv) {
            $form_data = json_decode($eztpv->form_data);
            $est = EztpvSaleType::select(
                'id'
            )->where(
                'slug',
                $form_data->contactType
            )->first();

            if ($est) {
                $e = Eztpv::find($eztpv->id);
                $e->eztpv_sale_type_id = $est->id;
                $e->save();
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
        Schema::table(
            'eztpvs', function (Blueprint $table) {
                $table->dropColumn('eztpv_sale_type_id');
            }
        );
    }
}
