<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\CustomFieldStorage;
use App\Models\CustomField;

class CleanSensitiveCF extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:sensitive {--debug} {--dryrun}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup Sensitive custom field data';

    /**
     * Create a new command instance.
     *
     * @return void
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
        $cf = CustomField::select(
            'custom_fields.id'
        )->where(
            'question',
            'LIKE',
            '%"sensitive":true%'
        )->pluck('id')->toArray();
        if ($cf) {
            $cfs = CustomFieldStorage::whereIn(
                'custom_field_id',
                $cf
            )->whereRaw(
                'LENGTH(value) > 4'
            )->where(
                'created_at',
                '<=',
                Carbon::now()->subHours(24)
            )->get();
            foreach ($cfs as $value) {
                $last4 = substr($value->value, -4);
                $this->info('Updating ' . $value->id . ' to ' . $last4);
                if (!$this->option('dryrun')) {
                    $value->value = $last4;
                    $value->save();
                }
            }

            if ($this->option('debug')) {
                $this->info(print_r($cfs->toArray()));
            }
        } else {
            $this->info('No custom fields found marked as sensitive.');
        }
    }
}
