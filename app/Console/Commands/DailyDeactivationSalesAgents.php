<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\ReportResult;
use App\Models\Report;
use App\Models\BrandUser;
use App\Models\BrandConfig;

class DailyDeactivationSalesAgents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deactivate:sales-agents 
        {--dryrun : Do everything except deactivate agents} 
        {--dump-configs : Display brand deactivation configurations and exit}
        {--brand_id= : Only run updates for this brand}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to deactivate sales agent upon x days business rule';

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
        $allBrands = BrandConfig::select(
            'brands.name',
            'brand_configs.rules',
            'brand_configs.brand_id'
        )->leftJoin(
            'brands',
            'brand_configs.brand_id',
            'brands.id'
        )->whereNull(
            'brands.deleted_at'
        )->whereNotNull(
            'brands.client_id'
        )->orderBy('brands.name', 'ASC')->get();

        $brands = [];
        foreach ($allBrands as $brand) {
            $brand->rules = json_decode($brand->rules, true);
            if ($this->option('dump-configs')) {
                $brands[] = $brand->toArray();
            } else {
                if (
                    isset($brand->rules)
                    && isset($brand->rules['sales_agent_inactive_auto_disable'])
                    && is_numeric($brand->rules['sales_agent_inactive_auto_disable'])
                    && intval($brand->rules['sales_agent_inactive_auto_disable']) > 0
                ) {
                    $brands[] = $brand->toArray();
                }
            }
        }

        if ($this->option('dump-configs')) {
            $this->info('Inactive Sales Agent Deactivation Configuration settings:');
            $row = [];
            foreach ($brands as $brand) {
                if (
                    !isset($brand['rules'])
                    || !isset($brand['rules']['sales_agent_inactive_auto_disable'])
                    || !is_numeric($brand['rules']['sales_agent_inactive_auto_disable'])
                ) {
                    $config = 'Not Activated';
                } else {
                    $config = $brand['rules']['sales_agent_inactive_auto_disable'] . ' days';
                }
                $row[] = [$brand['name'], $config];
            }
            $this->table(['Brand', 'Setting'], $row);

            return;
        }

        $wording = ($this->option('dryrun')) ? 'Would' : 'Will';
        $totalBus = 0;
        foreach ($brands as $brand) {
            if (
                $this->option('brand_id')
                && $brand['brand_id'] !== $this->option('brand_id')
            ) {
                continue;
            }

            try {
                $results = [];

                $days = $brand['rules']['sales_agent_inactive_auto_disable'];
                $brand_users = BrandUser::select(
                    'brand_users.created_at',
                    'brand_users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.username',
                    'brand_users.tsr_id',
                    'brand_users.protected',
                    'brand_users.last_sale_date',
                    'brands.name AS employee_of',
                    'offices.name AS office_name',
                    'brand_users.activated_at'
                )->where(
                    'brand_users.works_for_id',
                    $brand['brand_id']
                )->leftJoin(
                    'users',
                    'brand_users.user_id',
                    'users.id'
                )->leftJoin(
                    'brands',
                    'brand_users.employee_of_id',
                    'brands.id'
                )->leftJoin(
                    'brand_user_offices',
                    'brand_users.id',
                    'brand_user_offices.brand_user_id'
                )->leftJoin(
                    'offices',
                    'brand_user_offices.office_id',
                    'offices.id'
                );

                $brand_users = $brand_users->where(
                    'brand_users.protected',
                    0
                );

                $brand_users = $brand_users->where(
                    'brand_users.role_id',
                    3
                )->get();
                if ($brand_users) {
                    $bus = [];
                    foreach ($brand_users as $brand_user) {
                        if ($brand_user->last_sale_date === null && $brand_user->activated_at === null ) {
                            $deleted = DB::table('audits')->where('auditable_id', $brand_user->id)->where('event', 'deleted')->orderBy('created_at', 'desc')->first();
                            if ($deleted) {
                                $restored = DB::table('audits')->where('auditable_id', $brand_user->id)->where('event', 'updated')->whereDate('created_at', '>', $deleted->created_at)->orderBy('created_at', 'asc')->first();
                                if ($restored) {
                                    $date = new Carbon($restored->created_at);
                                } else {
                                    $date = new Carbon($brand_user->created_at);
                                }
                            } else {
                                $date = new Carbon($brand_user->created_at);
                            }    
                        } 
                        else {
                            if($brand_user->last_sale_date != null && $brand_user->activated_at != null) {
                                $date = new Carbon($brand_user->last_sale_date) > new Carbon($brand_user->activated_at) ? new Carbon($brand_user->last_sale_date) : new Carbon($brand_user->activated_at);
                            } else if($brand_user->last_sale_date != null) {
                                $date = new Carbon($brand_user->last_sale_date);
                            } else {
                                $date = new Carbon($brand_user->activated_at);
                            }
                        }
                        $limit = Carbon::now('America/Chicago')->subDays($days);
                        if ($date->gt($limit)) {
                            $brand_user->safe = true;
                        } else {
                            $brand_user->safe = false;
                        }

                        $bus[] = $brand_user->toArray();
                    }
                }

                $this->info('##########[ ' . $brand['name'] . ': ' . $days . ' days ]##########');
                if (count($bus) > 0) {
                    $totalBus = $totalBus + count($bus);
                    $this->info('#####[     ' . count($bus) . '     ]#####');

                    $rowcount = 0;
                    $rows = [];
                    foreach ($bus as $bu) {
                        $eventDate = (isset($bu['last_sale_date']))
                            ? $bu['last_sale_date']
                            : 'No Event Found.';

                        $office_name = null;
                        if (isset($bu['office_name'])) {
                            $office_name = $bu['office_name'];
                        }

                        ++$rowcount;
                        $rows[] = [
                            $rowcount,
                            $bu['username'],
                            $bu['employee_of'],
                            $office_name,
                            $bu['tsr_id'],
                            $bu['created_at'],
                            $eventDate,
                            $bu['protected'] ? 'Yes' : 'No',
                            $bu['safe'] || $bu['protected'] ? 'No' : 'Yes',
                        ];

                        if (!$this->option('dryrun')) {
                            if (!$bu['protected'] && !$bu['safe']) {
                                $buupdate = BrandUser::find($bu['id']);
                                if ($buupdate) {
                                    $results[] = $bu['id'];
                                    $buupdate->deleted_reason = 'Auto:NoSales:' . $days . '-days';
                                    $buupdate->status = 0;
                                    $buupdate->save();
                                    $buupdate->delete();
                                }
                            }
                        }
                    }

                    $this->table(
                        [
                            '#',
                            'Username',
                            'Vendor',
                            'Office',
                            'Rep ID',
                            'Created',
                            'Last Activity',
                            'Protected',
                            $wording . ' Remove?',
                        ],
                        $rows
                    );
                }

                $this->info('checked: ' . count($bus));
                $this->info('results: ' . count($results));

                if (count($results) > 0) {
                    $this->info('writing ReportResult');
                    $report = Report::where('name', 'Daily Deactivation')->first();
                    if ($report) {
                        $rr = new ReportResult();
                        $rr->for_date = now();
                        $rr->parameters = ['days' => $days];
                        $rr->parameters_hash = hash('sha256', json_encode(['days' => $days]));
                        $rr->report_id = $report->id;
                        $rr->brand_id = $brand['brand_id'];
                        $rr->results = $results;
                        $rr->save();
                        $this->info('ReportResult written');
                    } else {
                        $this->info('Warning! Daily Deactivation Report Not Found');
                    }
                } else {
                    $this->info('no results to store');
                }
                $this->info('------------------------------');
            } catch (\Exception $e) {
                info('Error processing daily deactivations for brand ' . $brand['brand_id'], [$e]);
                $this->warn('Error: ' . $e->getMessage() . ' while processing brand ' . $brand['brand_id']);
            }
        }


        $this->info('---<< Total Sales Agents: ' . $totalBus . ' >>---');
        //}
    }
}
