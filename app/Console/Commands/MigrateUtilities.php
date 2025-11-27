<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use App\Models\Utility;
use App\Models\UtilitySupportedFuel;
use App\Models\UtilityAccountIdentifier;
use App\Models\Rate;

class MigrateUtilities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:utilities';

    private $bar;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates the original Utility table layout to the new one processing only those entries that need to be converted';

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
        $this->info('Gathering Utility Information');
        
        $utils = Utility::withTrashed()
            ->orderBy('ldc_code', 'asc')
            ->orderBy('utility_type_id', 'asc')
            ->get()
            ->groupBy('ldc_code');

        $this->info('Processing Utility Groups for updates');
        $this->bar = $this->output->createProgressBar($utils->count());
        $toProcess = [];
        $groups = [];
        $cnt = 0;
        
        foreach ($utils as $ldccode => $util_list) {
            $needed = [];
            $inames = [];
            foreach ($util_list as $util) {
                $z = UtilitySupportedFuel::where('utility_id', $util->id)->withTrashed()->count();
                if ($z == 0) {
                    $needed[] = $util;
                }
                
            }
            
            if (count($needed) > 0) {
                $cnt += count($needed);
                $toProcess[$ldccode] = $needed;
                $groups[] = ['meta' => $this->gatherGroupInfo($ldccode, $util_list), 'list' => $util_list->toArray()];
            }
            $this->bar->advance();
        }
        $this->bar->finish();

        $this->line('');
        
        if ($cnt == 0) {
            $this->comment('Nothing to do!');
            return;
        }

        $utilitiesToUpdate = $this->checkNames($groups);
        
        $this->line('');
        $toUpdate = collect(
            array_merge(
                $utilitiesToUpdate, 
                $this->getRatesWithUpdates()
            )
        )->sort(
            function ($a, $b) {
                if ($a['type'] == 'migrate-products' && $b['type'] == 'migrate-products') {
                    return 0;
                } else {
                    if ($a['type'] == 'migrate-products') {
                        return 1;
                    }
                    if ($b['type'] == 'migrate-products') {
                        return -1;
                    }
                }
                if ($a['type'] == $b['type']) {
                    return 0;
                }
                return ($a['type'] < $b['type']) ? -1 : 1;
            }
        );
        $this->line('');
                
        if ($this->confirm('There are ' . $toUpdate->count() . ' updates to perform, would you like to proceed?')) {
            $this->processUpdates($toUpdate);
        } else {
            $this->comment('Update aborted.');
        }
    }

    private function processUpdates($updates) {
        $this->line('');
        $this->bar = $this->output->createProgressBar($updates->count());
        $me = $this;
        try {
            DB::transaction(
                function () use ($updates, $me) {
                    foreach ($updates as $update) {
                        switch($update['type']) {
                        case 'migrate-products':
                            $from = $update['from'];
                            $to = $update['to'];
                            Rate::where('utility_id', $from)->update(['utility_id' => $to]);
                            break;

                        case 'create-fuel':
                            $n = new UtilitySupportedFuel(
                                [
                                    'utility_id' => $update['utility_id'],
                                    'utility_fuel_type_id' => $update['utility_fuel_type_id'],
                                ]
                            );
                            
                            $n->dxc_rec_id = $update['dxc_rec_id'];
                            if ($update['deleted_at'] !== null) {
                                $n->deleted_at = $update['deleted_at'];
                            }
                            $n->save();

                            UtilityAccountIdentifier::where('utility_id', $update['utility_id'])
                                ->update(['utility_id' => $n->id]);
                            break;

                        case 'update-utility-name':
                            $n = Utility::find($update['utility_id']);
                            $n->name = $update['name'];
                            $n->save();
                            break;

                        case 'rate-update':
                            $n = UtilitySupportedFuel::where('utility_id', $update['utility_id'])
                                ->where('utility_fuel_type_id', $update['fuel_type'])
                                ->withTrashed()
                                ->get()
                                ->first();
                            if ($n != null) {
                                $r = Rate::find($update['rate_id']);
                                if ($r != null) {
                                    $r->utility_id = $n->id;
                                    $r->save();
                                }
                            }
                            break;
                        }
                        $me->bar->advance();
                    }
                }
            );
        } catch (\Exception $e) {
            $this->error($e);
        }
        
        $this->bar->finish();
    }

    private function buildCreateFuel($item, $isDeleted = true) {
        return [
            'type' => 'create-fuel',
            'utility_id' => $item['id'], 
            'utility_fuel_type_id' => $item['utility_type_id'], 
            'dxc_rec_id' => $item['dxc_rec_id'],
            'deleted_at' => $isDeleted ? $item['deleted_at'] : null
        ];
    }

    private function getRatesWithUpdates() {
        $this->line('');
        $allRates = Rate::withTrashed()->with('utility')->get();
        $supportedFuels = UtilitySupportedFuel::withTrashed()->get();
        $this->info('Checking Rates for needed updates');
        $this->bar = $this->output->createProgressBar($allRates->count());

        $ratesWithUpdates = [];

        foreach ($allRates as $rate) {
            $this->bar->advance();
            $ft = $supportedFuels->where('id', $rate->utility_id)->first(); 
            $ut = $rate->utility; 

            if ($ut != null && $ft == null) {
                $ratesWithUpdates[] = [
                    'type' => 'rate-update',
                    'rate_id' => $rate->id,
                    'utility_id' => $rate->utility->id,
                    'fuel_type' => $rate->utility->utility_type_id
                ];
            }
        }

        $this->bar->finish();
        return $ratesWithUpdates;
    }

    private function checkNames($groups) {
        //dd($groups);
        $this->line('');
        $this->info('Consolidating Utilities and Standardizing names');
        $this->bar = $this->output->createProgressBar(count($groups));
        $toCreate = [];
        foreach ($groups as $group) {
            $temp = array_values(
                array_filter(
                    $group['list'], function ($item) {
                        return $item['deleted_at'] == null;
                    }
                )
            );
            $tempDeleted = array_values(
                array_filter(
                    $group['list'], function ($item) {
                        return $item['deleted_at'] != null;
                    }
                )
            );

            if ($group['meta']['active'] == 1) {
                $toCreate[] = $this->buildCreateFuel($temp[0]);
            }
            if ($group['meta']['active'] == 2) {
                $dist = levenshtein(
                    $group['meta']['active_list'][0], 
                    $group['meta']['active_list'][1]
                );
                if ($dist == 8) {
                    $newName = (function ($list) {
                        $n1 = implode(" ", array_slice(explode(" ", $list[0]), 0, -1));
                        $n2 = implode(" ", array_slice(explode(" ", $list[1]), 0, -1));
                        if ($n1 === $n2) {
                            return $n1;
                        }
                        return '';
                    })($group['meta']['active_list']);
                    if ($newName != '') {
                        
                        $toCreate[] = [
                            'type' => 'update-utility-name',
                            'utility_id' => $temp[0]['id'],
                            'name' => $newName
                        ];
                        //first element is the one we're going to save
                        $toCreate[] = $this->buildCreateFuel($temp[0]);
                        $toCreate[] = $this->buildCreateFuel( //we just need the fuel type and rec id for the second
                            [
                                'id' => $temp[0]['id'],
                                'utility_type_id' => $temp[1]['utility_type_id'],
                                'dxc_rec_id' => $temp[1]['dxc_rec_id'],
                                'deleted_at' => $temp[1]['deleted_at']
                            ]
                        );
                          
                        $toCreate[] = [
                            'type' => 'migrate-products',
                            'from' => $temp[1]['id'],
                            'to' => $temp[0]['id']
                        ];
                    } else {
                        $this->comment('got here');
                    }
                } else {
                    $this->comment('and here');
                }
                
            } else {
                if ($group['meta']['active'] > 2) {
                    foreach ($temp as $titem) {
                        $toCreate[] = $this->buildCreateFuel($titem);
                    }
                }
            }
            foreach ($tempDeleted as $tempItem) {
                $toCreate[] = $this->buildCreateFuel($tempItem, true);
            }
            $this->bar->advance();
        }
        $this->bar->finish();

        return $toCreate;
    }

    private function gatherGroupInfo($ldc_code, $utilities) {
        $active = [];
        $inactive = [];

        foreach ($utilities as $util) {
            if ($util->deleted_at === null) {
                $active[] = $util->name;
            } else {
                $inactive[] = $util->name;
            }
        }

        /*foreach ($utilities as $util) {
            $n = new UtilitySupportedFuel(
                [
                    'utility_id' => $util->id,
                    'utility_fuel_type_id' => $util->utility_type_id
                ]
            );
            $n->dxc_rec_id = $util->dxc_rec_id;
            if ($util->deleted_at !== null) {
                $n->deleted_at = $util->deleted_at;
            }
            //$n->save();
            
            $this->bar->advance();
        }*/

        return ['group' => $ldc_code, 'active' => count($active), 'inactive' => count($inactive), 'active_list' => $active];
      
    }
}
