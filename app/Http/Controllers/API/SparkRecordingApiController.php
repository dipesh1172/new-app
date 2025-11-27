<?php

namespace App\Http\Controllers\API;

use App\Helpers\FtpHelper;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * Class SparkRecordingApiController
 * @package App\Http\Controllers\API
 */
class SparkRecordingApiController extends Controller
{
    protected $mode = '';

    protected $env = 'prod';

    protected $brand = 'Spark';

    protected $ftpSettings = null;

    protected $brandId = [
        'prod' => '7845a318-09ff-42fa-8072-9b0146b174a5',
        'stage' => ''
    ];

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function download(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            $signedUrl = $data['signedUrl'];
            $fileName  = $data['fileName'];

            $contents = file_get_contents($signedUrl);
            Storage::disk('public')->put($fileName, $contents);

            $filePath = 'app/public/'.$fileName;
            $path = storage_path($filePath);
            if(!file_exists($path)) {
                Log::error('******* FILE DOES NOT EXIST *********');
                return response()->json(['success' => false], 400);
            }
            if ($this->sendToFtp($path, $fileName)) {
                Log::info("SPARK File ". $fileName . " sent successfully");
                return response()->json(['success' => true], 200);
            }
        } catch(\Exception $e) {
            Log::error($e->getMessage());
        }
        return response()->json(['success' => false], 400);
    }

    /**
     * @param string $filePath
     * @param string $fileName
     * @return bool
     * @throws \League\Flysystem\FileExistsException
     */
    public function sendToFtp(string $filePath, string $fileName): bool
    {
        $this->ftpSettings = $this->getFtpSettings();
        $adapter = null;

        if(!$this->ftpSettings) {
            return false;
        }
        $this->ftpSettings['root'] = '/To Spark/Audio Files';
        $this->ftpSettings['passive'] = true;
        $this->ftpSettings['ssl'] = true;
        $this->ftpSettings['timeout'] = 60;
        $this->ftpSettings['directoryPerm'] = 0755;

        $adapter = new SftpAdapter($this->ftpSettings);
        $fs = new Filesystem($adapter);
        $stream = fopen($filePath, 'r+');
        $fs->writeStream(
            $fileName,
            $stream
        );
        if(is_resource($stream)) {
            fclose($stream);
        }

        unlink($filePath);
        return true;
    }

    /**
     * Retrieve FTP settings from provider_integrations table
     */
    private function getFtpSettings(): ?array {

        return FtpHelper::getSettings(
            $this->brandId[$this->env],
            48,
            1,
            (config('app.env') === 'production' ? 1 : 2)
        );
    }

    /**
     * Test function
     */
    public function ping(Request $request)
    {
        return "Echoing loud and clear";
    }
}
