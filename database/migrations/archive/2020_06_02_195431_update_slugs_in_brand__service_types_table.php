<?php

use App\Models\BrandServiceType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateSlugsInBrandServiceTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $types = BrandServiceType::get();
        
        foreach ($types as $type) {
            $newSlug = str_replace('-', '_', $type->slug);

            $bst = BrandServiceType::where(
                'id',
                $type->id
            )
            ->update([
                'slug' => $newSlug
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
