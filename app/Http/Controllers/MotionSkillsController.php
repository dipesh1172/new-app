<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\Dnis;
use App\Models\Language;
use App\Models\MotionSkill;

/**
 * 2023-05-03 - Alex Kolosha
 * 
 * Controller for managing Motion skills in Focus. The purpose isn't to manage skills in Motion. Rather, this is meant to manage a copy of the skill that 
 * are set up in Motion for the Focus/Motion Single Queue project. The data will be used to dial the correct Motion DNIS when assigning a TPV call to a Twilio user.
 */
class MotionSkillsController extends Controller
{
    /**
     * Define controller's routes
     */
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                // Route::get('support/motion/skills', 'MotionSkillsController@index')->name('motion_skills.index');              // Show skills list page
                // Route::get('support/motion/skills/list', 'MotionSkillsController@list')->name('motion_skills.list');           // Retrieve skills list
                // Route::get('support/motion/skills/create', 'MotionSkillsController@create')->name('motion_skills.create');     // Show add new skill page
                // Route::post('support/motion/skills/store', 'MotionSkillsController@store')->name('motion_skills.store');       // Store new skill record
                // Route::get('support/motion/skills/{id}/edit', 'MotionSkillsController@edit')->name('motion_skills.edit');      // Show edit page for specific skill record
                // Route::put('support/motion/skills/{skill}', 'MotionSkillsController@update')->name('motion_skills.update');    // Update existing skill record
                // Route::delete('support/motion/skills/{id}', 'MotionSkillsController@destroy')->name('motion_skills.destroy');  // Delete specific skill record
                Route::get('/motion/skills', 'MotionSkillsController@index')->name('motion_skills.index');              // Show skills list page
                Route::get('/motion/skills/list', 'MotionSkillsController@list')->name('motion_skills.list');           // Retrieve skills list
                Route::get('/motion/skills/create', 'MotionSkillsController@create')->name('motion_skills.create');     // Show add new skill page
                Route::post('/motion/skills/store', 'MotionSkillsController@store')->name('motion_skills.store');       // Store new skill record
                Route::get('/motion/skills/{id}/edit', 'MotionSkillsController@edit')->name('motion_skills.edit');      // Show edit page for specific skill record
                Route::put('/motion/skills/{skill}', 'MotionSkillsController@update')->name('motion_skills.update');    // Update existing skill record
                Route::delete('/motion/skills/{id}', 'MotionSkillsController@destroy')->name('motion_skills.destroy');  // Delete specific skill record
            }
        );
    }

    /**
     * Display list of Motion skills.
     */
    public function index(Request $request)
    {
        return view('motion.skills.show', [
            'createUrl' => json_encode(route('motion_skills.create'))   
        ]);
    }

    /** 
     * Retrieve skills list from databse 
     */
    public function list(Request $request)
    {
        $column    = $request->get('column');    // Column to sort by
        $direction = $request->get('direction'); // Sort direction
        $search    = $request->get('search');    // Search text, if search is being performed on previous result

        // Base query
        $skills = MotionSkill::select(
            'motion_skills.id',
            'motion_skills.name',
            'languages.language',
            'motion_skills.dnis'
        )->leftJoin(
            'languages', 
            'motion_skills.language_id', 
            'languages.id'
        );

        // Add on search text to query, if any
        if($search) {
            $skills = $skills->search($search);
        }

        // Add on column sort and directio to query, if provided
        if($column && $direction) {
            $skills = $skills->orderBy($column, $direction);
        } else {
            $skills = $skills->orderBy('name', 'asc'); // Default sort
        }

        $skills = $skills->paginate(20);

        return response()->json($skills);
    }

    /**
     * Show the page for creating a new skill
     */
    public function create(Request $request)
    {
        $languages = Language::select('id', 'language')->orderBy('language')->get();

        return view('motion.skills.create', [
            'languages' => $languages
        ]);
    }

    /**
     * Create a new Motion skill record.
     */
    public function store(Request $request)
    {
        // Set up for, and run validation on input fields
        $rules = [
            'name' => 'required',
            'language_id' => 'required',
            'dnis' => 'required'
        ];

        $validator = Validator::make(Input::all(), $rules);

        // Errors? Send user back to 'add new skill' page, with an error message list to display.
        if($validator->fails()) {
            return redirect()->route('motion_skills.create')
                ->withErrors($validator)
                ->withInput();
        }

        // All good. Save new record and send user back to skills list page.
        $skill = new MotionSkill();

        $skill->name        = $request->name;
        $skill->language_id = $request->language_id;
        $skill->dnis     = $request->dnis;

        $skill->save();

        return redirect()->route('motion_skills.index');
    }

    /**
     * Show edit page for selected Motion skill record.
     */
    public function edit($id)
    {
        $languages = Language::select('id', 'language')->orderBy('language')->get();

        $skill = MotionSkill::where('id', $id)->first();
     
        return view('motion.skills.edit', [
            'skill' => $skill,
            'languages' => $languages
        ]);
    }

    /**
     * Update a Motion skill record.
     */
    public function update(Request $request, MotionSkill $skill)
    {
        $rules = array(
            'name' => [
                'required',
                Rule::unique('motion_skills')->ignore($skill->name, 'name'),
            ],
            'language_id' => 'required',
            'dnis' => 'required'
        );

        $validator = Validator::make(Input::all(), $rules);

        // Errors? Send user back to 'add new skill' page, with an error message list to display.
        if($validator->fails()) {
            return redirect()->route('motion_skills.edit', $skill->id)
                ->withErrors($validator)
                ->withInput();
        }

        $skill->name = $request->name;
        $skill->language_id = $request->language_id;
        $skill->dnis = $request->dnis;

        $skill->save();

        session()->flash('flash_message', 'Skill was successfully edited!');

        return redirect()->route('motion_skills.edit', $skill->id);
    }

    /**
     * Delete specific Motion skill record.
     */
    public function destroy($id)
    {
        $skill = MotionSkill::find($id)->first();

        try {
            if($skill) {
                $skill->delete();

                session()->flash('flash_message', 'Skill was successfully deleted');
            }

            return response()->json([
                'status' => true,
                'message' => ''
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }

        // return redirect()->route('motion_skills.index');
        // return back();
    }
}
