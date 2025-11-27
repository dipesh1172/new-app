<?php

use App\Models\BrandEztpvContract;
use Illuminate\Database\Migrations\Migration;

class AddCustomVerbiageToIdtContracts extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $verbiage = '{"subject":"IDT Energy Welcome Packet","message_body":"<p>Thank you for enrolling with IDT Energy.</p><p>If you have any questions about your contract, please call IDT Energy directly at 1-877-887-6866, or you can visit the IDT Energy website at <a href=\"https://idtenergy.com/\">https://idtenergy.com</a></p><p>Thank you for joining the IDT Energy family!</p>"}';

        $bec = BrandEztpvContract::where(
            'brand_id',
            '77c6df91-8384-45a5-8a17-3d6c67ed78bf'
        )
        ->update([
            'email_verbiage_info' => $verbiage,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // unnecessary
    }
}
