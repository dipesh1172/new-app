<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BrandEztpvContract;

class RpaContractFieldSizes extends Migration
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
                'signature_info' => '{"6":{"0":"37,644,190,40"}}',
                'signature_info_customer' => '{"6":{"0":"37,644,190,40"}}',
                'signature_info_agent' => '{"6":{"0":"37,697,190,40"}}'
            ],
            1 => [
                'state' => 'md',
                'signature_info' => '{"6":{"0":"82,577,190,45"}}',
                'signature_info_customer' => '{"6":{"0":"82,577,190,45"}}',
                'signature_info_agent' => '{"6":{"0":"82,641,190,45"}}'
            ],
            2 => [
                'state' => 'nj',
                'signature_info' => '{"6":{"0":"36,609,195,40"}}',
                'signature_info_customer' => '{"6":{"0":"36,609,195,40"}}',
                'signature_info_agent' => '{"6":{"0":"36,660,195,40"}}'
            ],
            3 => [
                'state' => 'oh',
                'signature_info' => '{"6":{"0":"18,55,190,40"}}',
                'signature_info_customer' => '{"6":{"0":"18,55,190,40"}}',
                'signature_info_agent' => '{"6":{"0":"18,112,190,40"}}'
            ],
            4 => [
                'state' => 'pa',
                'signature_info' => '{"6":{"0":"36,576,200,45"}}',
                'signature_info_customer' => '{"6":{"0":"36,576,200,45"}}',
                'signature_info_agent' => '{"6":{"0":"36,645,200,45"}}'
            ]
        ];

        foreach ($states as $state) {
            $bec = BrandEztpvContract::where(
                'contract_pdf',
                'LIKE',
                'rpa_' . $state['state'] . '%'
            )
            ->update([
                'signature_info' => $state['signature_info'],
                'signature_info_customer' => $state['signature_info_customer'],
                'signature_info_agent' => $state['signature_info_agent']
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
