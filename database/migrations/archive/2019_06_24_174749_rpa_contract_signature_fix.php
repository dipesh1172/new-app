<?php

use App\Models\BrandEztpvContract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RpaContractSignatureFix extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $states = [
            0 => [
                'state' => 'il',
                'customer' => '{"1":{"0":"37,860,190,40"}}',
                'agent' => '{"1":{"0":"37,913,190,40"}}'
            ],
            1 => [
                'state' => 'md',
                'customer' => '{"1":{"0":"82,793,190,45"}}',
                'agent' => '{"1":{"0":"82,929,190,45"}}'
            ],
            2 => [
                'state' => 'nj',
                'customer' => '{"1":{"0":"36,825,195,40"}}',
                'agent' => '{"1":{"0":"35,876,195,40"}}'
            ],
            3 => [
                'state' => 'oh',
                'customer' => '{"1":{"0":"18,270,190,40"}}',
                'agent' => '{"1":{"0":"18,328,190,40"}}'
            ],
            4 => [
                'state' => 'pa',
                'customer' => '{"1":{"0":"36,792,200,45"}}',
                'agent' => '{"1":{"0":"36,861,200,45"}}'
            ]
        ];

        foreach ($states as $state) {
            $becs = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                'rpa_' . $state['state'] . '%'
            )
            ->update([
                'signature_info' => $state['customer'],
                'signature_info_customer' => $state['customer'],
                'signature_info_agent' => $state['agent']
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // unnecessary
    }
}
