<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Alerts\EventAction;
use App\Models\Alerts\EventActionType;
use App\Models\Alerts\EventType;
use App\Models\Alerts\Template;
use App\Models\Permission;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Alert Controller
 */
class AlertController extends Controller
{
    /**
     * Alert Controller routes
     *
     * @return void
     */
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::get('config/alerts', 'AlertController@index')->name('alerts.index');
                Route::get('config/alerts/create', 'AlertController@show_create')->name('alerts.create');
                Route::post('config/alerts/create', 'AlertController@save')->name('alerts.create.save');
                Route::post('config/alerts/save', 'AlertController@save')->name('alerts.save');
                Route::get('config/templates', 'AlertController@show_templates')->name('alerts.templates');
                Route::post('config/templates', 'AlertController@save_template')->name('alerts.templates.save');
                Route::get('config/templates/info', 'AlertController@get_template')->name('alerts.templates.info');
                Route::get('config/alerts/view/{event}', 'AlertController@show_edit')->name('alerts.edit');
            }
        );
    }

    /**
     * Alert Controller index
     *
     * @return void
     */
    public function index()
    {
        //dd(app_path('Events'));
        $events = EventType::orderBy('name', 'asc')->orderBy('enabled', 'desc')->get();

        try {
            $diskEvents = File::files(app_path('Events'));

            foreach ($diskEvents as $file) {
                $cname = 'App\Events\\'.File::name($file);
                $implements = class_implements($cname);
                $inherits = false;
                foreach ($implements as $key => $v) {
                    if ($v === 'App\Interfaces\ActionableEvent') {
                        $inherits = true;
                        break;
                    }
                }

                if (!$inherits) {
                    continue;
                }

                $name = title_case(str_replace("_", ' ', snake_case(File::name($file))));
                $found = false;
                foreach ($events as $item) {
                    if ($item->name == $name) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $e = new EventType();
                    $e->name = $name;
                    $e->description = call_user_func_array([$cname, 'description'], []);
                    $e->enabled = false;
                    $e->save();
                }
            }
        } catch (\InvalidArgumentException $e) {
            //pass;
        }

        $events = EventType::orderBy('enabled', 'desc')->orderBy('name', 'asc')->get();

        return view('alerts.index')->with(['events' => $events]);
    }

    public function show_edit(EventType $event)
    {
        $actions = EventActionType::orderBy('name', 'asc')->get();
        $templates = Template::where('enabled', true)->get();
        $vars = call_user_func_array(['App\Events\\'.studly_case($event->name), 'get_vars'], []);
        return view('alerts.create-event')->with(['actions' => $actions, 'templates' => $templates, 'event' => $event, 'vars' => $vars]);
    }

    public function show_create()
    {
        $actions = EventActionType::orderBy('name', 'asc')->get();
        $templates = Template::where('enabled', true)->get();
        return view('alerts.create-event')->with(['actions' => $actions, 'templates' => $templates, 'event' => null, 'vars' => []]);
    }

    public function show_templates()
    {
        $templates = Template::orderBy('name', 'asc')->orderBy('enabled', 'desc')->get();
        return view('alerts.templates')->with(['templates' => $templates]);
    }

    public function save()
    {
        //dd(request()->input());
        $validator = [
        'ename' => ['required', 'min:5', 'max:50'],
        'edesc' => 'required|min:10|max:255',
        'id' => 'nullable|exists:event_types,id',
        ];

        $actions = EventActionType::orderBy('name', 'asc')->get();
        $templates = Template::where('enabled', true)->get();
        $anames = [];
        $id = request()->input('id');
        if ($id != null) {
            $event = EventType::find($id);
        } else {
            $event = null;
            $validator['ename'][] = 'unique:event_types,name';
        }

        foreach ($actions as $action) {
            $anames[] = snake_case($action->name);
            $validator['template-action-' . snake_case($action->name)] = 'nullable|required_if:action-' . snake_case($action->name) . ',' . $action->id . '|exists:templates,id';
        }

        $this->validate(request(), $validator);

        try {
            DB::transaction(
                function () use ($anames, $event) {
                    if ($event == null) {
                        $event = new EventType();
                    } else {
                        EventAction::where('event_type', $event->id)->delete();
                    }
                    $actions_added = 0;
                    $event->name = request()->input('ename');
                    $event->description = request()->input('edesc');
                    $event->save();

                    $epermname = snake_case($event->name);

                    foreach ($anames as $action_name) {
                        if (request()->input('action-' . $action_name) != null) {
                            $action = new EventAction();
                            $action->event_type = $event->id;
                            $action->action_type = request()->input('action-' . $action_name);
                            $action->template_id = request()->input('template-action-' . $action_name);
                            $perm = Permission::where('short_name', 'events.' . $epermname . '_subscribe_to_' . $action_name)->first();
                            if ($perm == null) {
                                $perm = new Permission();
                                $perm->short_name = 'events.' . $epermname . '_subscribe_to_' . $action_name;
                                $perm->description = 'Autogenerated permission to subscribe to the ' . $action_name . ' action of the Event: ' . $event->name;
                                $perm->save();
                            }
                            $action->permission_id = $perm->id;
                            $action->save();
                            $actions_added++;
                        }
                    }
                    if ($actions_added > 0) {
                        $event->enabled = true;
                    } else {
                        $event->enabled = false;
                    }
                    $event->save();
                }
            );
        } catch (\Exception $e) {
            throw $e;
        }

        request()->session()->flash('status', 'Event Saved');
        return redirect()->route('alerts.index');

    }

    public function save_template()
    {
        $this->validate(
            request(), [
            'template-id' => 'nullable|exists:templates,id',
            'template-content' => 'required|max:4096',
            'template-name' => ['required', Rule::unique('templates')->ignore(request()->input('template-id'))],
            ]
        );

        $id = request()->input('template-id');
        if ($id == null) {
            $template = new Template();
        } else {
            $template = Template::find($id);
        }

        $template->name = request()->input('template-name');
        $template->template_content = request()->input('template-content');
        $template->save();
        request()->session()->flash('status', 'Template Saved!');

        return redirect()->route('alerts.templates');
    }

    public function get_template()
    {
        $id = request()->input('template');
        if ($id == null || !is_numeric($id)) {
            return response()->json(['errors' => 'Invalid Template'], 400);
        }
        $template = Template::find($id);
        if ($template == null) {
            return response()->json(['errors' => 'Template Not Found'], 400);
        }
        return response()->json(['errors' => null, 'template' => $template]);
    }
}
