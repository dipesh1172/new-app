<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait S3OperationsTrait
{
    private function s3Upload($keyname, $localFile)
    {
        try {
            info('Upload of '.$keyname
                .' attempted.');

            if (!file_exists($localFile)) {
                Log::error('FILE: '.$localFile.' does not exist.');

                return [
                    'error',
                    'Contract genration failed: locally saved generated pdf file not found',
                ];
            }

            $s3 = Storage::disk('s3')->put(
                $keyname,
                file_get_contents($localFile),
                'public'
            );

            if ($s3) {
                info(
                    'Upload of '
                        .$keyname.' succeeded.'
                );
            }

            if (Storage::disk('s3')->exists($keyname)) {
                return [
                    'success',
                    'File uploaded to s3 successfully.'
                ];
            } else {
                info(
                    's3 Error: '
                        .$keyname.' does not exist.  Returning error.'
                );

                return [
                    'error',
                    's3 reports contract file not uploaded; file does not exist.',
                ];
            }
        } catch (\Aws\S3\Exception\S3Exception $e) {
            Log::error($e.' returned from file upload');

            return [
                'error',
                's3 Error: '.$e.' returned from file upload',
            ];
        }
    }

    private function s3Download($keyname)
    {
        $check = Storage::disk('s3')->exists($keyname);
        $this->info('s3 file check: ' . $check);
        if (Storage::disk('s3')->exists($keyname)) {
            $file = Storage::disk('s3')->get($keyname);

            return $file;
        } else {
            
            return [
                'error',
                $keyname . ' not found.'
            ];
        }
    }

    private function s3Delete($keyname)
    {
        $check = Storage::disk('s3')->exists($keyname);
        $this->info('s3 file check: ' . $check);
        if (Storage::disk('s3')->exists($keyname)) {
            $file = Storage::disk('s3')->delete($keyname);

            return 'success';
        } else {
            
            return [
                'error',
                $keyname . ' not found.'
            ];
        }
    }
}