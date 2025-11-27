<?php

use Illuminate\Database\Seeder;

use App\Models\Brand;
use App\Models\Dnis;

class DnisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $brand_id1 = Brand::select('id')
            ->where("name", "Forward Thinking Energy")
            ->first();

        $exists = Dnis::where('dnis', '+19189923401')->first();
        if ($exists == null) {
            $dnis = new Dnis();
            $dnis->brand_id = $brand_id1->id;
            $dnis->dnis = '+19189923401';
            $dnis->save();
        } else {
            echo "  * 918-992-3401 dnis already exists.\n";
        }
    }
}
