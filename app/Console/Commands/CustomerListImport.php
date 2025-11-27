<?php

namespace App\Console\Commands;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Upload;
use App\Models\PhoneNumber;
use App\Models\JsonDocument;
use App\Models\CustomerList;

class CustomerListImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customerlist:import
                {--file= : import the specified file}
                {--upload= : import the specified upload id}
                {--replace : replace existing entries with this list}
                {--update : add new entries from list}
                {--type= : the list type (1 Blacklist, 2 Active Customer, 3 Approved Customers, 4 Do Not Call)}
                {--brand= : the brand this list belongs to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports a customer list';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $isFile = false;
        $isUpload = false;
        $cleanFileToProcess = null;

        $action = null;
        if ($this->option('replace')) {
            $action = 'replace';
        }

        if ($this->option('update') && $action === null) {
            $action = 'update';
        }

        if ($this->option('update') && $this->option('replace')) {
            $this->error('You may specify only one of --replace or --update.');

            return 2;
        }

        $brand = $this->option('brand');
        if ($brand === null || $brand == '') {
            $this->error('You must specify the brand');

            return 3;
        }

        if ($this->option('file') !== null) {
            $isFile = true;
            $fileToProcess = $this->option('file');
            if (!file_exists($fileToProcess)) {
                $this->error('The file: ' . $fileToProcess . ' does not exist.');

                return 4;
            }
        }

        if ($this->option('upload') !== null && !$isFile) {
            $isUpload = true;
            $upload = Upload::find($this->option('upload'));
            if ($upload === null) {
                $this->error('Upload ' . $this->option('upload') . ' does not exist.');

                return 5;
            }
            $upload->processing = 1;
            $upload->save();
            $fileToProcess = tempnam(sys_get_temp_dir(), 'clim');
            if ($fileToProcess === false) {
                $this->error('Unable to create temporary file');

                return 6;
            }
            $success = file_put_contents($fileToProcess, Storage::disk('s3')->get($upload->filename));
            if ($success === false) {
                $this->error('Could not create temporary file');

                return 7;
            }
        }

        if ($this->option('file') && $this->option('upload')) {
            $this->error('You must only specify --file or --upload, not both');

            return 8;
        }

        $listTypeRaw = $this->option('type');
        if ($listTypeRaw === null) {
            $this->error('You must specify the type of list');

            return 9;
        }
        $listType = intval($listTypeRaw, 10);
        if ($listType == 0 || $listType < 3 || $listType > 4) {
            $this->error('This tool only supports customer list types 3 and 4 at this time');

            return 10;
        }

        // ok here we go!
        $totalFound = 0;
        $totalImported = 0;
        $totalExisting = 0;
        try {
            /* Deduplicating with shell tools */
            $cleanFileToProcess = tempnam(sys_get_temp_dir(), 'clim');
            shell_exec('cat ' . $fileToProcess . ' | sort | uniq > ' . $cleanFileToProcess);
            if (filesize($cleanFileToProcess) > 0) {
                if ($isUpload) {
                    unlink($fileToProcess);
                }
                $this->info('Duplicates removed from import file.');
                $fileToProcess = $cleanFileToProcess;
            }
            /* end dedupe */

            if ($action === 'replace') {
                //DB::beginTransaction();
                CustomerList::where('brand_id', $brand)->where('customer_list_type_id', $listType)->delete();
            }
            $fh = fopen($fileToProcess, 'rb');
            if ($fh === false) {
                $this->error('Could not open file for reading: ' . $fileToProcess);

                return 11;
            }
            fclose($fh);
            $lines = collect(file($fileToProcess, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES));

            $chunks = $lines->chunk(1000)->toArray();
            foreach ($chunks as $chunk) {
                switch ($listType) {
                    default:
                        throw new \Exception('These list types are not supported by this tool yet');
                        break;

                    case 3:
                    case 4:
                        //Approved and DNC
                        $cleaned = [];
                        foreach ($chunk as $line) {
                            $eline = trim($line);
                            if ($eline === '') {
                                info('CLimport - skipping blank line');
                            } else {
                                $cleanedPhone = CleanPhoneNumber($eline);
                                if ($cleanedPhone === null) {
                                    info('CLimport - could not clean: ' . $eline . ' into a valid phone number');
                                } else {
                                    $cleaned[] = $cleanedPhone;
                                }
                            }
                            ++$totalFound;
                        }

                        $existingPhones = PhoneNumber::select(
                            'id'
                        )->whereIn(
                            'phone_number',
                            $cleaned
                        )->whereNull('extension')->get();

                        $newPhones = [];
                        $newPhonesCL = [];
                        foreach ($cleaned as $cleanPhone) {
                            $existingPhone = $existingPhones->where('phone_number', $cleanPhone)->first();
                            if ($existingPhone === null) {
                                $new = [
                                    'id' => Uuid::uuid4(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                    'phone_number' => $cleanPhone,
                                ];
                                $newPhones[] = $new;
                                $newPhonesCL[] = [
                                    'id' => Uuid::uuid4(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                    'brand_id' => $brand,
                                    'customer_list_type_id' => $listType,
                                    'phone_number_id' => $new['id'],
                                ];
                            }
                        }
                        if (count($newPhones) > 0) {
                            DB::table('phone_numbers')->insert($newPhones);
                            DB::table('customer_lists')->insert($newPhonesCL);
                            $totalImported += count($newPhonesCL);
                        }
                        $existingPhoneIds = $existingPhones->whereNotIn(
                            'phone_number',
                            collect($newPhones)->pluck('phone_number')
                        )->pluck('id');
                        if (count($existingPhoneIds) > 0) {
                            // DB::table('phone_numbers')
                            //     ->whereNotNull('deleted_at')
                            //     ->whereIn('id', $existingPhoneIds)
                            //     ->update(['updated_at' => now(), 'deleted_at' => null]);

                            DB::table('customer_lists')
                                ->where('brand_id', $brand)
                                ->where('customer_list_type_id', $listType)
                                ->whereIn('phone_number_id', $existingPhoneIds)
                                ->whereNotNull('deleted_at')
                                ->update(['updated_at' => now(), 'deleted_at' => null]);

                            $inserted = DB::table('customer_lists')
                                ->select('phone_number_id')
                                ->where('brand_id', $brand)
                                ->where('customer_list_type_id', $listType)
                                ->whereIn('phone_number_id', $existingPhoneIds)
                                ->get()
                                ->pluck('phone_number_id');

                            $left = $existingPhones->whereNotIn('id', $inserted)->pluck('id');

                            if (count($left) > 0) {
                                $newList = [];
                                foreach ($left as $insertMe) {
                                    $newList[] = [
                                        'id' => Uuid::uuid4(),
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                        'brand_id' => $brand,
                                        'customer_list_type_id' => $listType,
                                        'phone_number_id' => $insertMe,
                                    ];
                                }
                                DB::table('customer_lists')->insert($newList);
                            }

                            $totalExisting += count($existingPhoneIds);
                        }
                        break;
                }
            }
            //if ($action === 'replace') {
            //    DB::commit();
            //}
            $blah = ['New Records: ' . $totalImported . ' of ' . $totalFound . ' in file, ' . $totalExisting . ' existing records in file.'];

            return $this->finishUp(0, $blah, $isUpload ? $this->option('upload') : null);
        } catch (\Exception $e) {
            if ($action === 'replace') {
                //DB::rollback();
                $err = [$e->getMessage()];
            } else {
                $err = [$e->getMessage() . ' | New Records: ' . $totalImported . ' of ' . $totalFound . ' in file, ' . $totalExisting . ' existing records in file.'];
            }

            $this->finishUp(42, $err, $isUpload ? $this->option('upload') : null);
            $this->error($e->getMessage());

            return 12;
        } finally {
            // cleanup
            //fclose($fh);
            if ($isUpload) {
                unlink($fileToProcess);
            } else {
                if ($cleanFileToProcess !== null) {
                    unlink($cleanFileToProcess); // normally only the upload path removes a file
                }
            }
        }
    }

    private function finishUp($exitCode, &$errors, $uploadId = null)
    {
        if ($uploadId !== null) {
            $now = Carbon::now();
            $upload = Upload::find($uploadId);
            $upload->processing = 0;
            if (count($errors) == 0 || $exitCode == 0) {
                $upload->processed_at = $now;
            } else {
                $upload->processing = 2;
            }
            $upload->save();

            $j = new JsonDocument();
            $j->document_type = 'upload-errors';
            $j->ref_id = $uploadId;
            $j->document = ['errors' => $errors];
            $j->save();
        }

        return $exitCode;
    }
}
