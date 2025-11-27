<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

trait DeliverableTrait 
{
    public function getEmailConfig() {
        return [
            'from' => 'no-reply@tpvhub.com',
            'to' => array(),
            'subject' => '',
            'body' => '',
            'attachments' => array()
        ];
    }

    public function sendGenericEmail($config) {

        $cfg = array_merge($this->getEmailConfig(), $config);

        foreach($cfg['to'] as $to) {
            Mail::send(
                'emails.generic',
                [
                    'subject' => '',
                    'content' => $cfg['body']
                ],
                function ($message) use ($cfg, $to) {
                    $message->subject($cfg['subject']);
                    $message->from($cfg['from']);
                    $message->to(trim($to));

                    // add attachments
                    foreach ($cfg['attachments'] as $file) {
                        $message->attach($file);
                    }
                }
            );
        }
    }

    /**
     * Enrollment File SFTP Upload.
     *
     * @param string $file   - path to file being uploaded
     *
     * @return string - Status message
     */
    public function sftpUpload($file, $ftpSettings)
    {
        $status = 'FTP at ' . Carbon::now() . '. Status: ';
        try {

            if(!$ftpSettings) {
                return $status . 'Error! SFTP settings are required.';
            }

            $adapter = new SftpAdapter($ftpSettings);

            $fileParts = pathinfo($file);
            
            $filesystem = new Filesystem($adapter);
            $stream = fopen($file, 'r+');
            $filesystem->writeStream(
                $fileParts['basename'],
                $stream
            );

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Exception $e) {
            $status .= 'Error! The reason reported is: ' . $e;

            return $status;
        }
        $status .= 'Success!';

        return $status;
    }
}
