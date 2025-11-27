<?php

use App\Models\BrandServiceType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PopulateSlugColumnOfBrandServiceTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $slugs = [
            'HRTPV' => 'hrtpv',
            'Customer Device Signature' => 'customer-device-signature',
            'Custom Submission' => 'custom-submission'
        ];

        foreach ($slugs as $name => $slug) {
            $bst = BrandServiceType::where(
                'name',
                $name
            )
            ->update([
                'slug' => $slug
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
