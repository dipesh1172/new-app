<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\CustomField;
use App\Models\BrandCustomField;
use App\Models\Brand;

class CreateTxuExtraFieldsCustomFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $data = [
            [
                'name' => 'Sub Channel',
                'output_name' => 'sub_channel',
            ],
            [
                'name' => 'WS Campaign Name',
                'output_name' => 'ws_campaign_name',
            ],
            [
                'name' => 'Account Number',
                'output_name' => 'account_number',
            ],
            [
                'name' => 'Business Partner',
                'output_name' => 'business_partner',
            ],
            [
                'name' => 'EDC',
                'output_name' => 'edc',
            ],
            [
                'name' => 'Mail Drop Date',
                'output_name' => 'mail_drop_date',
            ],
            [
                'name' => 'Meter Read Cycle',
                'output_name' => 'meter_read_cycle',
            ],
            [
                'name' => 'Avg Monthly KWH',
                'output_name' => 'avg_monthly_kwh',
            ],
        ];
        $brand = Brand::where('name', 'TXU Energy')->whereNotNull('client_id')->first();

        if ($brand) {
            foreach ($data as $item) {
                $cf = new CustomField();
                $cf->name = $item['name'];
                $cf->output_name = $item['output_name'];
                $cf->description = 'Filled in when a lead is selected';
                $cf->custom_field_type_id = 5;
                $cf->save();

                $bcf = new BrandCustomField();
                $bcf->brand_id = $brand->id;
                $bcf->custom_field_id = $cf->id;
                $bcf->associated_with_type = 'Event';
                $bcf->save();
            }
        } else {
            throw new \Exception('TXU brand not found!');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
