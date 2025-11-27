<?php

namespace App\Http\Controllers;

use Twilio\Rest\Client;

class TestingController extends Controller
{
    public function voiceImprint()
    {
        $to = request()->input('to');
        $confirmation = request()->input('confirmation');
        $twilio = new Client(config('services.twilio.account'), config('services.twilio.auth_token'));
        $call = $twilio->calls->create(
            $to, // to
            config('services.twilio.default_number'),
            [
                'url' => str_replace(':/', '://', str_replace('//', '/', config('app.url').'/api/twilio/voiceimprint?confirmation='.$confirmation)),
            ]
        );

        return response()->back();
    }

    public function rpaWelcomeEmail()
    {
        return view(
            'emails.rpa_welcome',
            [
                'name' => 'Brian Williams',
                'address' => '123 Main St.',
                'city_state_zip' => 'Tulsa, OK 74144'
            ]
        );
    }
}
