<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_eztpv_contracts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->string('brand_id', 36);
            $table->string('contract_pdf');
            $table->text('contract_fdf');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_eztpv_contracts');
    }
}
