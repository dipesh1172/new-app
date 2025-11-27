<?php

use App\Models\Brand;
use App\Models\BrandUser;
use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Database\Seeder;

class DevTestUsers extends Seeder
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

        $exists = User::where('email', 'demo-cs@tpv.com')->first();
        if ($exists == null) {
            $testUser = new User();
            $testUser->first_name = 'Demo';
            $testUser->last_name = 'ClientServices';
            $testUser->password = bcrypt('tpvdev');
            $testUser->email = 'demo-cs@tpv.com';
            $testUser->save();
            
            $bu = new BrandUser();
            $bu->employee_of_id = $brand_id1->id;
            $bu->works_for_id = $brand_id1->id;
            $bu->user_id = $testUser->id;
            $bu->tsr_id = 'DEV-CS';
            $bu->role_id = 1;
            $bu->status = true;
            $bu->save();
        } else {
            echo "  * demo-admin@tpv.com user already exists.\n";
        }

        $exists = User::where('email', 'demo-admin@tpv.com')->first();
        if ($exists == null) {
            $testUser = new User();
            $testUser->first_name = 'Demo';
            $testUser->last_name = 'Admin';
            $testUser->password = bcrypt('tpvdev');
            $testUser->email = 'demo-admin@tpv.com';
            $testUser->save();
            
            $bu = new BrandUser();
            $bu->employee_of_id = $brand_id1->id;
            $bu->works_for_id = $brand_id1->id;
            $bu->user_id = $testUser->id;
            $bu->tsr_id = 'DEV-ADMIN';
            $bu->role_id = 2;
            $bu->status = true;
            $bu->save();
        } else {
            echo "  * demo-admin@tpv.com user already exists.\n";
        }

        $exists = User::where('email', 'demo-exec@tpv.com')->first();
        if ($exists == null) {
            $testUser = new User();
            $testUser->first_name = 'Demo';
            $testUser->last_name = 'Executive';
            $testUser->password = bcrypt('tpvdev');
            $testUser->email = 'demo-exec@tpv.com';
            $testUser->save();
            
            $bu = new BrandUser();
            $bu->employee_of_id = $brand_id1->id;
            $bu->works_for_id = $brand_id1->id;
            $bu->user_id = $testUser->id;
            $bu->tsr_id = 'DEV-EXEC';
            $bu->role_id = 3;
            $bu->status = true;
            $bu->save();
        } else {
            echo "  * demo-exec@tpv.com user already exists.\n";
        }

        $exists = User::where('email', 'demo-manager@tpv.com')->first();
        if ($exists == null) {
            $testUser = new User();
            $testUser->first_name = 'Demo';
            $testUser->last_name = 'Manager';
            $testUser->password = bcrypt('tpvdev');
            $testUser->email = 'demo-manager@tpv.com';
            $testUser->save();
            
            $bu = new BrandUser();
            $bu->employee_of_id = $brand_id1->id;
            $bu->works_for_id = $brand_id1->id;
            $bu->user_id = $testUser->id;
            $bu->tsr_id = 'DEV-MANAGER';
            $bu->role_id = 4;
            $bu->status = true;
            $bu->save();
        } else {
            echo "  * demo-manager@tpv.com user already exists.\n";
        }

        $exists = User::where('email', 'demo-rep@tpv.com')->first();
        if ($exists == null) {
            $testUser = new User();
            $testUser->first_name = 'Demo';
            $testUser->last_name = 'Rep';
            $testUser->password = bcrypt('tpvdev');
            $testUser->email = 'demo-rep@tpv.com';
            $testUser->save();
            
            $up = new UserPhone();
            $up->user_id = $testUser->id;
            $up->phone = '9182083133';

            $bu = new BrandUser();
            $bu->employee_of_id = $brand_id1->id;
            $bu->works_for_id = $brand_id1->id;
            $bu->user_id = $testUser->id;
            $bu->tsr_id = 'DEV-REP';
            $bu->role_id = 5;
            $bu->status = true;
            $bu->save();
        } else {
            echo "  * demo-agent@tpv.com user already exists.\n";
        }
    }
}
