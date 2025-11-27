<?php
use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBusinessRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list')->first();

        if ($br) {
            $br->slug = 'eztpv_ip_lookup_alert_distro_list_dtd';
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - DTD";
            $br->save(); 
        }

        $br1 = new BusinessRule();
        $br1->slug = 'eztpv_ip_lookup_alert_distro_list_tm';
        $br1->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - TM";
        $br1->answers = '{"type":"textbox","text":""}';
        $br1->save(); 

        $brd1 = new BusinessRuleDefault();
        $brd1->business_rule_id = $br1->id;
        $brd1->save();

        $br2 = new BusinessRule();
        $br2->slug = 'eztpv_ip_lookup_alert_distro_list_retail';
        $br2->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert - RETAIL";
        $br2->answers = '{"type":"textbox","text":""}';
        $br2->save(); 

        $brd2 = new BusinessRuleDefault();
        $brd2->business_rule_id = $br2->id;
        $brd2->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $br = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_dtd')->first();

        if ($br) {
            $br->slug = 'eztpv_ip_lookup_alert_distro_list';
            $br->business_rule = "EZTPV: Email distro for ''Proxy/VPN Detected'' alert. Separate emails with a comma; no spaces between email addresses. Leave blank to disable alert.";
            $br->save(); 
        }

        // Find the business rule by its slug
        $br1 = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_tm')->first();

        if ($br1) {
            // Delete the associated default answer
            BusinessRuleDefault::where('business_rule_id', $br1->id)->delete();

            // Delete the business rule itself
            $br1->delete();
        }

        // Find the business rule by its slug
        $br2 = BusinessRule::where('slug', 'eztpv_ip_lookup_alert_distro_list_retail')->first();

        if ($br2) {
            // Delete the associated default answer
            BusinessRuleDefault::where('business_rule_id', $br2->id)->delete();
            
            // Delete the business rule itself
            $br2->delete();
        }
    }
}
