<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

class MailgunWebhookValidator
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        info("In MailgunWebhookValidator::handle()");

        // Allow POST requests only
        if(!$request->isMethod('post')) {
            info("In MailgunWebhookValidator::handle() -- WRONG HTTP VERB");
            abort(Response::HTTP_FORBIDDEN, 'Only POST requests are allowed.');
        }

        // Verify timestamp and signature in request
        if($this->verify($request)) {            
            return $next($request);
        }

        info("In MailgunWebhookValidator::handle() -- SIGNATURE VALIDATION FAILED");

        abort(Response::HTTP_FORBIDDEN);
    }

    /**
     * Build signature to compare to request's signature
     */
    protected function buildSignature($request)
    {
        info("In MailgunWebhookValidator::buildSignature()");

        return hash_hmac(
            'sha256',
            sprintf('%s%s', $request->input('timestamp'), $request->input('token')),
            config('services.mailgun.webhook_key')
        );
    }

    /**
     * Verify the request's signature
     */
    protected function verify($request)
    {
        info("In MailgunWebhookValidator::verify()");

        if(abs(time() - $request->input('timestamp')) > 15) {
            info("In MailgunWebhookValidator::verify() -- TIMESTAMP GAP TOO LARGE");
            return false;
        }

        return $this->buildSignature($request) === $request->input('signature');
    }
}
