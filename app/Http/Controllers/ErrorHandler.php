<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\JsonDocument;

class ErrorHandler extends Controller
{
    public static function reportError(int $type, \Exception $exception): array
    {
        if ($type !== 404) {
            $msg = $exception->getMessage();
            $msg = strtolower($msg);
            $isMissingRecord = str_contains($msg, 'no query results');
            $errorId = uniqid($type . '.', true);
            $request = request();
            $info = [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'ips' => $request->ips(),
                'useragent' => $request->userAgent(),
                'attr' => $request->attributes->all(),
                'session' => session()->all(),
                'user' => Auth::check() ? Auth::user() : null,
                'input' => $request->input(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ];
            info('Error ' . $errorId, $info);
            $j = new JsonDocument();
            $j->document_type = 'site-errors';
            $j->ref_id = $errorId;
            $j->document = $info;
            $j->save();


            return [
                'code' => $errorId,
                'is-missing-record' => $isMissingRecord,
                'message' => $info['message'],
            ];
        }
        return ['code' => 404, 'is-missing-record' => false];
    }
}
