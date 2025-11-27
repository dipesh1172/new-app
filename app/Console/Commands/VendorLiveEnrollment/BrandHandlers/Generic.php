<?php

namespace App\Console\Commands\VendorLiveEnrollment\BrandHandlers;
use Log;

class Generic implements IHandler {
    
    private $brand;

    function __construct($brand)
    {
        $this->brand = $brand;
    }

    public function applyCustomFilter($query)
    {
        return $query;
    }

    public function handleSubmission($sps, $options)
    {
    }

    protected function log($message, $type = "info")
    {
        Log::info("[$type]: $message");
        print("[$type]: $message\n");
    }
}
