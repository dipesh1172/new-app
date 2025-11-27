<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

use Twilio\Security\RequestValidator;

class TwilioRequestValidator
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
        // Allow POST requests only
        if(!$request->isMethod('post')) {
            abort(Response::HTTP_FORBIDDEN, "Only POST requests are allowed.");
        }

        // Allow requests against local ENV
        if(config('app.env') === 'local') {
            return $next($request);
        }

        // Validator requires this header to be present and not null
        if(!$request->header('X-Twilio-Signature')) {
            abort(Response::HTTP_FORBIDDEN, "Missing required headers");
        }

        // Initialize the validator utility
        $requestValidator = new RequestValidator(config('services.twilio.auth_token'));

        // Validate the request
        $isValid = $requestValidator->validate(
            $request->header('X-Twilio-Signature'),
            $request->fullUrl(),
            $request->toArray()
        );

        if($isValid) {
            return $next($request);
        } else {
            abort(Response::HTTP_FORBIDDEN, "You are not Twilio");
        }
    }
}
