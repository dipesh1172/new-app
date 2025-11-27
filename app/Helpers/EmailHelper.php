<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

use App\Models\EmailMessage;

class EmailHelper
{
    /**
     * Sends an email using the generic email template.
     */
    public static function sendGenericEmail(array $messageObject)
    {
        // Validate messageObject
        $validator = Validator::make(
            $messageObject, 
            self::emailMessageValidationRules(), 
            self::emailMessageValidationMessages()
        );

        if($validator->fails()) {
            throw new \Exception('sendGenericEmail() :: ' . $validator->errors()->first());
        }
        
        // Data for generic email template
        $data = [
            'subject' => '',
            'content' => $messageObject['body']
        ];

        return self::sendEmail($messageObject, 'emails.generic', $data);
    }

    /**
     * Sends an email using the specified template.
     */
    public static function sendEmail(array $messageObject, $template, $templateData)
    {
        // Validate messageObject
        $validator = Validator::make(
            $messageObject, 
            self::emailMessageValidationRules(), 
            self::emailMessageValidationMessages()
        );

        if($validator->fails()) {
            throw new \Exception('sendEmail() :: ' . $validator->errors()->first());
        }

        // Start with the a default email object, then merge user object to overwrite populated fields
        $msg = array_merge(self::newEmailMessage(), $messageObject);
          
        try {
            Mail::send(
                $template,
                $templateData,
                function ($message) use (&$msg) {
                    $msg['id'] = $message->getId();
                    
                    $message->subject($msg['subject']);
                    $message->from($msg['from']);
                    $message->to($msg['to']);

                    // Add attachments
                    foreach($msg['attachments'] as $attachment) {
                        $message->attach($attachment);
                    }
                }
            );

        } catch (\Exception $e) {
            info("EmailHelper::sendEmail() -- " . $e->getMessage());
            return false;
        }

        // Log email?
        if(isset($msg['track']) && $msg['track']) {
            $email = new EmailMessage();

            $email->conversation_id = $msg['id'];
            $email->message_id = $msg['id'];
            $email->to = $msg['to'];
            $email->from = $msg['from'];
            $email->subject = $msg['subject'];
            $email->body = $msg['body'];

            // Log brand ID, if provided
            if(isset($msg['brand_id']) && $msg['brand_id']) {
                $email->brand_id = $msg['brand_id'];
            }

            // Log event ID, if provided
            if(isset($msg['event_id']) && $msg['event_id']) {
                $email->event_id = $msg['event_id'];
            }

            $email->save();
        }

        return true;
    }

    /**
     * Convenience function to create an associative array representing an email message
     */
    public static function newEmailMessage()
    {
        return [
            'id' => null,
            'to' => [],
            'from' => config('mail.from'),
            'subject' => null,
            'body' => null,
            'attachments' => [],
            'track' => false,
            'brand_id' => null,
            'event_id' => null
        ];
    }

    /**
     * Validator rules for email message object used by the sending functions in this class
     */
    private static function emailMessageValidationRules()
    {
        return [
            'to' => 'required|string',
            'from' => 'required|string',
            'subject' => 'required|string',
            'body' => 'required|string',
            'attachments' => 'array',
            'brand_id' => 'nullable|string',
            'event_id' => 'nullable|string'
        ];
    }

    /**
     * Validator rule messages for email message object used by the sending functions in this class
     */    
    private static function emailMessageValidationMessages()
    {
        return [
            "to.required" => "Missing required field 'to'",
            "to.string" => "Field 'to' must be a string",
            "from.required" => "Missing required field 'from'",
            "from.string" => "Field 'from' must be an array",
            "subject.required" => "Missing required field 'subject'",
            "subject.string" => "Field 'subject' must be a string",
            "body.required" => "Missing required field 'body'",
            "body.string" => "Field 'body' must be a string",
            "attachments.array" => "The 'attachments' field must be an array",
            "brand_id.string" => "Field 'brand_id' must be an array",
            "event_id.string" => "Field 'event_id' must be an array"
        ];
    }
}
