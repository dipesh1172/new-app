<?php

namespace App\Traits;

trait ExportableTrait 
{
    public function writeCsvFile(string $filename, array $data, array $csvHeader) 
    {
        $file = fopen($filename, "w");

        // Write header row
        fputcsv($file, $csvHeader);

        foreach($data as $r) {
            fputcsv($file, $r);    
        }

        fclose($file);
    }

    public function writeFile(string $filename, array $data, array $header = null) 
    {
        $file = fopen($filename, "w");

        if($header) {
            fputs($file, $header . "\r\n");
        }

        foreach($data as $r) {
            fputs($file, $r . "\r\n");
        }

        fclose($file);
    }

    /**
     * Create's CSV Header array from an array's keys.
     * Header values are spaced and propert cased.
     */
    private function arrayKeysToCsvHeader(array $keys) {

        $header = [];
        foreach($keys as $key) {
            $header[] = ucwords(str_replace('_', ' ', $key));
        }

        return $header;
    }
}
