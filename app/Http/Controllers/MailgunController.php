<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\EmailMessage;

class MailgunController extends Controller
{
    public function store(Request $r)
    {
        $messageId = $r->input('Message-Id');
        $replyToId = $r->input('In-Reply-To');

        // If no In-Reply-To header, then this not a reply to one of our messages.
        // Don't log it.
        if(!$replyToId) {
            return response()->json(['status' => 'ok', 'message' => 'Not a reply.']); // Return a 200 response or Mailgun will retry the webhook.
        }

        // Remove < and > enclosures.
        if(substr($messageId, 0, 1) == '<') {
            $messageId = substr($messageId, 1);
            $messageId = substr($messageId, 0, strlen($messageId) - 1);
        }

        if(substr($replyToId, 0, 1) == '<') {
            $replyToId = substr($replyToId, 1);
            $replyToId = substr($replyToId, 0, strlen($replyToId) - 1);
        }        

        // Find the email being replied to.
        // We only using this to validate that the reploy is to a tracked message,
        // and to get the conversation id (message ID of original email), so only grab the first occurrence
        $originalEmail = EmailMessage::where('message_id', $replyToId)->first();

        if(!$originalEmail) {
            return response()->json(['status' => 'ok', 'message' => 'Unable to find the email being replied to.']);
        }

        // It's reply to one of our track emails! Log it.
        $email = new EmailMessage();

        $email->conversation_id = $originalEmail->conversation_id; // Carry the conversation ID forward so we can group emails that are part of the same conversation
        $email->message_id      = $messageId;
        $email->to              = $r->input('recipient');
        $email->from            = $r->input('sender');
        $email->subject         = $r->input('subject');
        $email->body            = $r->input('body-plain');
        $email->headers         = $r->input('message-headers');

        $email->save();

        // Return a 200 even for emails we don't accept, or Mailgun will retry the webhook
        return response()->json(['status' => 'ok']);
    }
}
