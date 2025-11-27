<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddMissingRules1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('business_rules')->insert(
            [
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'slug' => 'acceptable_responses',
                    'business_rule' => 'What type of responses is the TPV allowed to accept?',
                    'answers' => json_encode(
                        [
                            'type' => 'choice',
                            'choices' => [
                                'Clear Yes and No Only',
                                'Affirmative'
                            ],
                        ]
                    ),
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'slug' => 'customer_receiving_assistance',
                    'business_rule' => 'May the Customer receive assistance from the Sales Agent or any other persons during the TPV?',
                    'answers' => json_encode(
                        [
                            'type' => 'switch',
                            'on' => 'Yes',
                            'off' => 'No',
                            'default' => 'No',
                        ]
                    ),
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'slug' => 'customer_asking_questions',
                    'business_rule' => 'Is the TPV permitted to answer questions from the customer?',
                    'answers' => json_encode(
                        [
                            'type' => 'switch',
                            'on' => 'Yes',
                            'off' => 'No',
                            'default' => 'No',
                        ]
                    ),
                ],
            ]
        );
        DB::table('business_rules')->where('slug', 'disposition_timeout')->update(['deleted_at' => null]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //nothing to do
    }
}
