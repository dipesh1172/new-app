<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuLinksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $id_qa = DB::table('menu_links')->insertGetId([
                'name' => 'QA',
                'url' => null,
                'icon' => 'fa-hand-paper-o',
                'parent_id' => null,
                'position' => 9,
                'role_permissions' => '1, 3, 4, 8, 10'
            ]);

            $id_users = DB::table('menu_links')->insertGetId([
                'name' => 'Users',
                'url' => null,
                'icon' => 'fa-hand-paper-o',
                'parent_id' => null,
                'position' => 12,
                'role_permissions' => '1, 5, 6, 9, 10'
            ]);

            $id_support = DB::table('menu_links')->insertGetId([
                'name' => 'Support',
                'url' => null,
                'icon' => 'fa-ambulance',
                'parent_id' => null,
                'position' => 11,
                'role_permissions' => '1'
            ]);

            DB::table('menu_links')->insert(
                [
                    [
                        'name' => 'Call Dashboard',
                        'url' => '/dashboard',
                        'icon' => 'fa-dashboard',
                        'parent_id' => null,
                        'position' => 1,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Sales Dashboard',
                        'url' => '/sales_dashboard',
                        'icon' => 'fa-dashboard',
                        'parent_id' => null,
                        'position' => 2,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Brands',
                        'url' => '/brands',
                        'icon' => 'fa-tags',
                        'parent_id' => null,
                        'position' => 3,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Brand Users',
                        'url' => '/brand_users',
                        'icon' => 'fa-tags',
                        'parent_id' => null,
                        'position' => 4,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Billing',
                        'url' => '/billing',
                        'icon' => 'fa-usd',
                        'parent_id' => null,
                        'position' => 5,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Configuration',
                        'url' => '/config',
                        'icon' => 'fa-gears',
                        'parent_id' => null,
                        'position' => 6,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'DNIS',
                        'url' => '/dnis',
                        'icon' => 'fa-phone-square',
                        'parent_id' => null,
                        'position' => 7,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Knowledge Base',
                        'url' => '/kb',
                        'icon' => 'fa-book',
                        'parent_id' => null,
                        'position' => 8,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Call Followups',
                        'url' => '/qa_review',
                        'icon' => 'fa-hand-paper-o',
                        'parent_id' => $id_qa,
                        'position' => 1,
                        'role_permissions' => '1, 3, 4'
                    ],
                    [
                        'name' => 'Events',
                        'url' => '/events',
                        'icon' => 'fa-archive',
                        'parent_id' => $id_qa,
                        'position' => 2,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Reports',
                        'url' => '/reports',
                        'icon' => 'fa-table',
                        'parent_id' => null,
                        'position' => 10,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Agents',
                        'url' => '/agents',
                        'icon' => 'fa-user-circle-o',
                        'parent_id' => $id_users,
                        'position' => 1,
                        'role_permissions' => '1, 9'
                    ],
                    [
                        'name' => 'TPV Staff',
                        'url' => '/tpv_staff',
                        'icon' => 'fa-id-badge',
                        'parent_id' => $id_users,
                        'position' => 2,
                        'role_permissions' => '1, 5, 6, 10'
                    ],
                    [
                        'name' => 'Clear Test Call',
                        'url' => '/support/clear_test_calls',
                        'icon' => 'fa-trash-o',
                        'parent_id' => $id_support,
                        'position' => 1,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Clear Caches',
                        'url' => '/support/clear_cache',
                        'icon' => 'fa-trash-o',
                        'parent_id' => $id_support,
                        'position' => 2,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Make Test Calls',
                        'url' => '/test_calls',
                        'icon' => 'fa-phone',
                        'parent_id' => $id_support,
                        'position' => 3,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Call Status Search',
                        'url' => '/qa-tool/callsearch',
                        'icon' => 'fa-search',
                        'parent_id' => $id_support,
                        'position' => 4,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Chat Settings',
                        'url' => '/chat-settings',
                        'icon' => 'fa-envelope',
                        'parent_id' => $id_support,
                        'position' => 5,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Issues Dashboard',
                        'url' => '/issues_dashboard',
                        'icon' => 'fa-exclamation-triangle',
                        'parent_id' => $id_support,
                        'position' => 6,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Manage Menu',
                        'url' => '/menus/manage_menu',
                        'icon' => 'fa-bars',
                        'parent_id' => $id_support,
                        'position' => 7,
                        'role_permissions' => '1'
                    ],
                    [
                        'name' => 'Utilities',
                        'url' => '/utilities',
                        'icon' => 'fa-bolt',
                        'parent_id' => null,
                        'position' => 13,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Vendors',
                        'url' => '/vendors',
                        'icon' => 'fa-tags',
                        'parent_id' => null,
                        'position' => 14,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Floor Issues',
                        'url' => '/issues',
                        'icon' => 'fa-bug',
                        'parent_id' => null,
                        'position' => 15,
                        'role_permissions' => null
                    ],
                    [
                        'name' => 'Site Errors',
                        'url' => '/errors',
                        'icon' => 'fa-bug',
                        'parent_id' => null,
                        'position' => 16,
                        'role_permissions' => '1'
                    ]
                ]
            );
        });
    }
}