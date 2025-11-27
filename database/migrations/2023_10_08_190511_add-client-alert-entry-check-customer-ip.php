<?php
use App\Models\ClientAlert;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClientAlertEntryCheckCustomerIp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $ca = new ClientAlert();
        $ca->title = 'IP Address Used Before';
        $ca->channels = 'DTD,Retail,TM';
        $ca->description = "Customer's IP address (digital or signature IP) matches the customer IP address used in a previous event with a different account number.";
        $ca->threshold = 0;
        $ca->function = 'CheckDuplicatedIPAddress';
        $ca->category_id = 5;
        $ca->can_stop_call = false;
        $ca->has_threshold = false;
        $ca->client_alert_type_id = 2;
        $ca->brand_id = (config('app.env') != 'production' ? '250035df-4e77-465a-8d9a-293bf78b6283':'250035df-4e77-465a-8d9a-293bf78b6283') ; //NORDIC STAGING 250035df-4e77-465a-8d9a-293bf78b6283, prod:250035df-4e77-465a-8d9a-293bf78b6283
        $ca->sort = 17;

        $ca->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ClientAlert::where('function', 'CheckDuplicatedIPAddress')->delete();
    }
}
