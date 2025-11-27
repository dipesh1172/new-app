<?php

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVmMessageBusinessRules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $br = new BusinessRule();
        $br->slug = 'vm_greeting_english';
        $br->business_rule = 'Message to use when leaving a voicemail (English)';
        $br->answers = '{"type":"textbox","text":""}';
        $br->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $br->id;
        $brd->default_answer = 'Hello, I am calling on behalf of {{client.name}}’s Sales Quality Assurance Team. I am sorry we missed you. We will attempt to contact you at a more convenient time. Thank you and have a great day!';
        $brd->save();

        $br = new BusinessRule();
        $br->slug = 'vm_greeting_spanish';
        $br->business_rule = 'Message to use when leaving a voicemail (Spanish)';
        $br->answers = '{"type":"textbox","text":""}';
        $br->save();

        $brd = new BusinessRuleDefault();
        $brd->business_rule_id = $br->id;
        $brd->default_answer = 'Hola, llamo en nombre del equipo de control de calidad de ventas de {{client.name}}. Lamentamos no haber podido contactarlo/a el dia de hoy. Intentaremos comunicarnos con usted en un momento más conveniente. ¡Gracias y que tengas un buen día!';
        $brd->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
