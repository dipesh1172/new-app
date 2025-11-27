<?php

namespace App\Http\Controllers;

use App\Events\Amazon\SnsNotification;
use App\Http\Controllers\Controller;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;

class AmazonSnsProcessor extends Controller
{
    public function process_request()
    {
        $message = Message::fromRawPostData();
        $validator = new MessageValidator();
        if (!$validator->isValid($message)) {
            return response('Invalid Signature', 401);
        }
        info($message->toArray());
        switch ($message['Type']) {
            case 'SubscriptionConfirmation':
                try {
                    $sub = file_get_contents($message['SubscribeURL']);
                    //info($sub);
                } catch (\Exception $e) {
                    info($e);
                }
                break;

            case 'Notification':
                event(new SnsNotification($message));
                break;

            default:
                info('Unknown Type of SNS: ' . $message);
                break;
        }
        return response('', 200);
    }
}
