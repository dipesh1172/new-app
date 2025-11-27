<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\TpvStaffRole;
use App\Models\MenuLink;

class MenuLinksController extends Controller
{
    public function index()
    {
        $links = Cache::remember(
            'menu_links',
            300,
            function () {
                return MenuLink::get();
            }
        );

        return $links->values()->all();
    }

    public function manage_menu()
    {
        $roles = TpvStaffRole::select('id', 'name')->get();
        return view('generic-vue')->with(
            [
                'componentName' => 'manage-menu',
                'title' => 'Manage Menu',
                'parameters' => [
                    'roles' => json_encode($roles)
                ]
            ]
        );
    }

    public function write_info_on_db(Request $request)
    {
        $columns = [
            'created_at' => now(),
            'name' => $request->name,
            'url' => $request->url,
            'icon' => $request->icon,
            'parent_id' => $request->parent_id,
            'position' => $request->position,
            'role_permissions' => $request->selected_roles
        ];

        if ($request->id) {
            $link = MenuLink::where('id', $request->id)->first();
            if ($link->position != $request->position) {
                $this->update_siblings($request, $link->position, $request->position);
            }
            //Now safely update link with new position
            MenuLink::where('id', $request->id)->update($columns);
        } else {
            $pos = MenuLink::where('parent_id', $request->parent_id)->get()->count();
            $this->update_siblings($request, $pos, $request->position);
            MenuLink::insert($columns);
        }
        Cache::forget('menu_links');
        session()->flash('flash_message', 'The link was added/updated to the menu.');
        return back();
    }

    public function destroy(Request $request)
    {
        $link = MenuLink::where('id', $request->id)->first();
        $pos_2 = $link->position;
        $pos_1 = MenuLink::where('parent_id', $request->parent_id)->get()->count();
        $this->update_siblings($request, $pos_2, $pos_1);
        MenuLink::where('id', $request->id)->delete();
        Cache::forget('menu_links');
        session()->flash('flash_message', 'The link was removed from the menu.');
        return back();
    }

    private function update_siblings($request, $pos_1, $pos_2)
    {
        MenuLink::where('parent_id', $request->parent_id)->get()->each(function ($l) use ($request, $pos_1, $pos_2) {
            $major = $pos_2 > $pos_1 ? $pos_2 : $pos_1;
            $minor = $pos_2 > $pos_1 ? $pos_1 : $pos_2;
            $action = ($pos_2 > $pos_1) ? 'decrement' : 'increment';

            if ($l->position >= $minor && $l->position <= $major && $l->id != $request->id) {
                $query = MenuLink::where('id', $l->id);
                if ($action == 'decrement') {
                    $query = $query->decrement('position');
                } else {
                    $query = $query->increment('position');
                }
            }
        });
    }
}
