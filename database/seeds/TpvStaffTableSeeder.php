<?php

use App\Models\ServiceLogin;
use App\Models\TpvStaff;

use Illuminate\Database\Seeder;

class TpvStaffTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	//Administrator
        $exists = TpvStaff::where('username', 'demo-admin')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-admin';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-admin';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 1;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-admin@tpv.com user already exists.\n";
        }

        //Client Services Manager
        $exists = TpvStaff::where('username', 'demo-csm')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-csm';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-csm';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 2;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-csm@tpv.com user already exists.\n";
        }

        //QA Manager
        $exists = TpvStaff::where('username', 'demo-qam')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-qam';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-qam';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 3;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-qam@tpv.com user already exists.\n";
        }

        //QA
        $exists = TpvStaff::where('username', 'demo-qa')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-qa';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-qa';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 4;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-qa@tpv.com user already exists.\n";
        }

        //Human Resources Manager
        $exists = TpvStaff::where('username', 'demo-hrman')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-hrman';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-hrman';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 5;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-hrman@tpv.com user already exists.\n";
        }

        //Human Resources
        $exists = TpvStaff::where('username', 'demo-hr')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-hr';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-hr';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 6;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-hr@tpv.com user already exists.\n";
        }

        //Billing
        $exists = TpvStaff::where('username', 'demo-billing')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-billing';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-billing';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 7;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-billing@tpv.com user already exists.\n";
        }

        //TPV Agent Manager
        $exists = TpvStaff::where('username', 'demo-tpvman')->first();
        if ($exists == null) {
        	$seed 					= new TpvStaff();
        	$seed->first_name		= 'demo-tpvman';
        	$seed->last_name		= 'tpvcom';
        	$seed->username 		= 'demo-tpvman';
        	$seed->password 		= bcrypt('tpvdev');
        	$seed->call_center_id 	= 1;
        	$seed->language_id		= 1;
        	$seed->role_id 			= 8;
        	$seed->status 			= 1;
        	$seed->save();
        } else {
        	echo " * demo-tpvman@tpv.com user already exists.\n";
        }

        // TPV Agent
        $exists = TpvStaff::where('username', 'tpv-agent')->first();
        if ($exists == null) {
            $tpvstaff = new TpvStaff();
            $tpvstaff->first_name = "TPV";
            $tpvstaff->last_name = "Agent";
            $tpvstaff->username = "tpv-agent";
            $tpvstaff->password = bcrypt('tpvdev');
            $tpvstaff->call_center_id = 1;
            $tpvstaff->language_id = 1;
            $tpvstaff->role_id = 9;
            $tpvstaff->status = 1;
            $tpvstaff->save();
        } else {
            $lookup = TpvStaff::select('id')
                ->where("username", "tpv-agent")
                ->first();

            echo "  * tpv staff user already exists.\n";
        }

        $exists = ServiceLogin::where('tpv_staff_id', $lookup->id)->first();
        if ($exists == null) {
            $serviceLogin = new ServiceLogin();
            $serviceLogin->tpv_staff_id = $lookup->id;
            $serviceLogin->service_type_id = 1;
            $serviceLogin->username = "tpvdev-testuser1";
            $serviceLogin->password = "Monday16";
            $serviceLogin->save();
        } else {
            echo "  * service login already exists.\n";
        }

        //Workforce Manager
        $exists = TpvStaff::where('username', 'demo-wfman')->first();
        if ($exists == null) {
            $seed                   = new TpvStaff();
            $seed->first_name       = 'demo-wfman';
            $seed->last_name        = 'tpvcom';
            $seed->username         = 'demo-wfman';
            $seed->password         = bcrypt('tpvdev');
            $seed->call_center_id   = 1;
            $seed->language_id      = 1;
            $seed->role_id          = 12;
            $seed->status           = 1;
            $seed->save();
        } else {
            echo " * demo-wfman@tpv.com user already exists.\n";
        }

        //Workforce Coordinator
        $exists = TpvStaff::where('username', 'demo-wfco')->first();
        if ($exists == null) {
            $seed                   = new TpvStaff();
            $seed->first_name       = 'demo-wfco';
            $seed->last_name        = 'tpvcom';
            $seed->username         = 'demo-wfco';
            $seed->password         = bcrypt('tpvdev');
            $seed->call_center_id   = 1;
            $seed->language_id      = 1;
            $seed->role_id          = 13;
            $seed->status           = 1;
            $seed->save();
        } else {
            echo " * demo-wfco@tpv.com user already exists.\n";
        }
    }
}
