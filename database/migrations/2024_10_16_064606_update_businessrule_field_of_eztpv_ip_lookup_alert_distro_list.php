<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\BusinessRule;

class UpdateBusinessruleFieldOfEztpvIpLookupAlertDistroList extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_dtd')->whereNull('deleted_at')->first();

        if ($br) {
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN/Datacenter Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - DTD";
            $br->save(); 
        }

        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_tm')->whereNull('deleted_at')->first();

        if ($br) {
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN/Datacenter Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - TM";
            $br->save(); 
        }

        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_retail')->whereNull('deleted_at')->first();

        if ($br) {
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN/Datacenter Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - RETAIL";
            $br->save(); 
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_dtd')->whereNull('deleted_at')->first();

        if ($br) {
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - DTD";
            $br->save(); 
        }

        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_tm')->whereNull('deleted_at')->first();

        if ($br) {
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - TM";
            $br->save(); 
        }

        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_retail')->whereNull('deleted_at')->first();

        if ($br) {
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - RETAIL";
            $br->save(); 
        }
    }
}
