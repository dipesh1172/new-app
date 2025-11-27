<?php


use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIdStorageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('identification_storage', function (Blueprint $table) {
            $table->string('id', 36);
            $table->timestamps();
            $table->softDeletes();
            $table->bigInteger('identification_type_id');
            $table->bigInteger('state_id')->nullable();
            $table->bigInteger('country_id')->default(1);
            $table->string('associated_type_id', 36);
            $table->string('associated_type', 100);
            $table->string('control_number', 150);
            $table->string('named_person');
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('upload_id', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('identification_storage');
    }
}
