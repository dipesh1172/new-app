<?php

namespace App\Http\Controllers;

use App\Models\BusinessRule;
use App\Models\BusinessRuleDefault;
use App\Models\BrandConfig;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Input;
use Validator;

class BusinessRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $rules = BusinessRule::select('id', 'slug', 'business_rule', 'answers')->paginate(30);
        foreach ($rules as $rule) {
            $answers = json_decode($rule->answers, $assoc = TRUE);
            switch ($answers['type']) {
                case 'timer':
                    $rule->answers_html = $answers['default'] . " " . $answers['unit'];
                    break;
                
                case 'switch':
                    switch ($answers['default']) {
                        case true:
                            $rule->answers_html = "On";
                            break;
                        
                        case false:
                            $rule->answers_html = "Off";
                            break;
                    }
                    break;
                
                case 'hours_of_operation':
                    // code...
                    break;
                
                case 'textbox':
                    $rule->answers_html = $answers['text'];
                    break;
                
            }
        }
        return view('rules.rules', ['rules' => $rules]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('rules.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        switch ($request->answer_type) {
            case 'timer':
                $rules = array(
                    'timer_from' => 'required',
                    'timer_to' => 'required',
                    'timer_step' => 'required',
                    'timer_default' => 'required',
                    'timer_unit' => 'required'
                );
                break;
            
            case 'switch':
                $rules = array(
                    'switch_on' => 'required',
                    'switch_off' => 'required',
                    'switch_default' => 'required'
                );
                break;
            
            case 'hours_of_operation':
                
                break;
            
            case 'textbox':
                $rules = array(
                    'textbox_text' => 'required'
                );
                break;
        }

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect('rules.create')
                ->withErrors($validator)
                ->withInput();
        } else {
            $rule = new BusinessRule;
            $rule->business_rule = $request->business_rule;
            switch ($request->answer_type) {
                case 'timer':
                    $array = ['type' => 'timer', 'from' => $request->timer_from, 'to' => $request->timer_to, 'step' => $request->timer_step, 'default' => $request->timer_default, 'unit' => $request->timer_unit];
                    $timer = json_encode($array);
                    $rule->answers = $timer;
                    break;
                
                case 'switch':
                    $array = ['type' => 'switch', 'on' => $request->switch_on, 'off' => $request->switch_off, 'default' => $request->switch_default];
                    $switch = json_encode($array);
                    $rule->answers = $switch;
                    break;
                
                case 'hours_of_operation':
                    
                    break;
                
                case 'textbox':
                    $array = ['type' => 'textbox', 'text' => $request->textbox_text];
                    $textbox = json_encode($array);
                    $rule->answers = $textbox;
                    break;
            }
            $rule->save();

            $default = new BusinessRuleDefault;
            $default->business_rule_id = $rule->id;
            $default->default_answer = $rule->answers;
            $default->save();

            return redirect()->route('rules.index');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     * w
     *
     * @param  User $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $rule = BusinessRule::find($id);
        $rule->answers = json_decode($rule->answers, $assoc = TRUE);
        $rule->answer_type = $rule->answers['type'];
            switch ($rule->answers['type']) {
                case 'timer':
                    $rule->answers_html = "<label for='timer_from'>From</label> <input type='number' name='timer_from' min='" . $rule->answers['from'] . "' max='" . $rule->answers['to'] . "' step='" . $rule->answers['step'] . "' value='" . $rule->answers['from'] . "'> <label for='timer_to'>To</label> <input type='number' name='timer_to' min='" . $rule->answers['from'] . "' max='" . $rule->answers['to'] . "' step='" . $rule->answers['step'] . "' value='" . $rule->answers['to'] . "'> <label for='timer_step'>Step</label> <input type='number' name='timer_step' min='" . $rule->answers['from'] . "' max='" . $rule->answer['to'] . "' step='" . $rule->answers['step'] . "' vale='" . $rule->answers['step'] . "'> <label for='timer_default'>Default</label> <input type='number' name='timer_default' min='" . $rule->answer['from'] . "' max='" . $rule->answer['to'] . "' step='" . $rule->answer['step'] . "' value='" . $rule->answer['default'] . "'> <select name='timer_unit' id='timer_unit'><option value='seconds'>Seconds</option><option value='minutes'>Minutes</option><option value='days'>Days</option></select>";
                    break;
                
                case 'switch':
                    $rule->answers_html = "<label for='switch_on'>On</label> <input type='text' name='switch_on' value='" . $rule->answers['on'] . "' class='form-control'> <label for='switch_off'>Off</label> <input type='text' name='switch_off' value='" . $rule->answers['off'] . "' class='form-control'><label for='switch_default'>Default</label><select name='switch_default' id='switch_default' class='form-control'><option value='on'>On</option><option value='off'>Off</option></select>";
                    break;
                
                case 'hours_of_operation':
                    // code...
                    break;
                
                case 'textbox':
                    $rule->answers_html = "<textarea name='textbox_text' id='textbox_text' cols='30' rows='10' class='form-control'>" . $rule->answers['text'] . "</textarea>";
                    break;
                
            }
        return view('rules.edit', ['rule' => $rule]);
    }

    /**
     * Update the specified business rule.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  User                     $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        switch ($request->answer_type) {
            case 'timer':
                $rules = array(
                    'timer_from' => 'required',
                    'timer_to' => 'required',
                    'timer_step' => 'required',
                    'timer_default' => 'required',
                    'timer_unit' => 'required'
                );
                break;
            
            case 'switch':
                $rules = array(
                    'switch_on' => 'required',
                    'switch_off' => 'required',
                    'switch_default' => 'required'
                );
                break;
            
            case 'hours_of_operation':
                
                break;
            
            case 'textbox':
                $rules = array(
                    'textbox_text' => 'required'
                );
                break;
        }

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return redirect('rules.edit')
                ->withErrors($validator)
                ->withInput();
        } else {
            $rule = BusinessRule::find($id);
            $rule->business_rule = $request->business_rule;
            switch ($request->answer_type) {
                case 'timer':
                    $array = ['type' => 'timer', 'from' => $request->timer_from, 'to' => $request->timer_to, 'step' => $request->timer_step, 'default' => $request->timer_default, 'unit' => $request->timer_unit];
                    $timer = json_encode($array);
                    $rule->answers = $timer;
                    break;
                
                case 'switch':
                    $array = ['type' => 'switch', 'on' => $request->switch_on, 'off' => $request->switch_off, 'default' => $request->switch_default];
                    $switch = json_encode($array);
                    $rule->answers = $switch;
                    break;
                
                case 'hours_of_operation':
                    
                    break;
                
                case 'textbox':
                    $array = ['type' => 'textbox', 'text' => $request->textbox_text];
                    $textbox = json_encode($array);
                    $rule->answers = $textbox;
                    break;
            }
            $rule->save();
            
            $default = BusinessRuleDefault::where('business_rule_id', '=', $rule->id)->first();
            $default->default_answer = $rule->answers;
            $default->save();

            return redirect()->route('rules.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($rule)
    {
        $default = BusinessRuleDefault::where('business_rule_id', '=', $rule)->first();
        $default->delete();
        $toDelete = BusinessRule::find($rule);
        $toDelete->delete();

        session()->flash('flash_message', 'Business Rule was successfully deleted!');
        return redirect()->route('rules.index');
    }

    public function updateBrandConfigs(Request $request)
    {
        $defaults = BusinessRuleDefault::join('business_rules', 'business_rules.id', 'business_rule_defaults.business_rule_id')->get();
        $configs = array();

        foreach ($defaults as $default) {
            switch ($request['rule_'.$default->business_rule_id]) {
                case 'on':
                    $configs[$default->slug] = true;
                    break;
                case 'off':
                    $configs[$default->slug] = false;
                    break;
                default:
                    $configs[$default->slug] = $request['rule_'.$default->business_rule_id];

            }
        }

        $toDelete = BrandConfig::where('brand_id', '=', session('current_brand')->id)->first();
        if (!is_null($toDelete)) {
            $toDelete->delete();    
        }

        $rules = json_encode($configs);
        $config = new BrandConfig;
        $config->brand_id = session('current_brand')->id;
        $config->rules = $rules;
        $config->save();

        return redirect()->route('rules.index');
    }
}
