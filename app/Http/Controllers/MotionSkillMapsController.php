<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use App\Models\Brand;
use App\Models\Dnis;
use App\Models\Language;
use App\Models\MotionSkill;
use App\Models\MotionSkillMap;

/**
 * 2023-05-03 - Alex Kolosha
 * 
 * Controller for mapping a Focus brand, DNIS, and language combination to a motion Motion skill.
 */
class MotionSkillMapsController extends Controller
{
    /**
     * Define controller's routes
     */
    public static function routes()
    {
        Route::group(
            ['middleware' => ['auth']],
            function () {
                Route::get('motion/skill_maps', 'MotionSkillMapsController@index')->name('motion_skill_maps.index');              // Show skills list page
                Route::get('motion/skill_maps/list', 'MotionSkillMapsController@list')->name('motion_skill_maps.list');           // Retrieve skills list
                Route::get('motion/skill_maps/create', 'MotionSkillMapsController@create')->name('motion_skill_maps.create');     // Show add new skill page
                Route::post('motion/skill_maps/store', 'MotionSkillMapsController@store')->name('motion_skill_maps.store');       // Store new skill record
                Route::get('motion/skill_maps/{id}/edit', 'MotionSkillMapsController@edit')->name('motion_skill_maps.edit');      // Show edit page for specific skill record
                Route::put('motion/skill_maps/{skillMap}', 'MotionSkillMapsController@update')->name('motion_skill_maps.update');    // Update existing skill record
                Route::delete('motion/skill_maps/{id}', 'MotionSkillMapsController@destroy')->name('motion_skill_maps.destroy');  // Delete specific skill record                
            }
        );
    }

    /**
     * Display list of Motion skills.
     */
    public function index(Request $request)
    {
        return view('motion.skill_maps.show', [
            'createUrl' => json_encode(route('motion_skill_maps.create'))   
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
        $maps = MotionSkillMap::select(
            'motion_skill_maps.id',
            'brands.name AS brand_name',
            'focus_dnis.dnis AS focus_dnis',
            'languages.language',
            'motion_skills.name AS motion_skill',
            'motion_skills.dnis AS motion_dnis'
        )->leftJoin(
            'brands',
            'motion_skill_maps.brand_id',
            'brands.id'
        )->leftJoin(
            'motion_skills',
            'motion_skill_maps.motion_skills_id',
            'motion_skills.id'
        )->leftJoin(
            'languages',
            'motion_skill_maps.language_id',
            'languages.id'
        )->leftJoin(
            'dnis as focus_dnis',
            'motion_skill_maps.dnis_id',
            'focus_dnis.id'
        );

        // Add on search text to query, if any
        if($search) {
            $maps = $maps->where('brands.name', 'LIKE', '%' . $search . '%');
        }

        // Add on column sort and directio to query, if provided
        if($column && $direction) {
            $maps = $maps->orderBy($column, $direction);
        } else {
            $maps = $maps->orderBy('brand_name', 'asc'); // Default sort
        }

        $maps = $maps->paginate(20);

        return response()->json($maps);
    }

    /**
     * Show the page for creating a new skill
     */
    public function create(Request $request)
    {
        $brands    = Brand::select('id', 'name')->whereNotNull('client_id')->orderBy('name')->get();
        $languages = Language::select('id', 'language')->orderBy('language')->get();
        $dnis      = DNIS::select('id', 'brand_id', 'dnis')->where('platform', '!=', 'motion single q')->orderBy('dnis')->get();
        $skills    = MotionSkill::select('id', 'name', 'dnis')->orderBy('name')->get();

        return view('motion.skill_maps.create', [
            'brands' => $brands,
            'dnis' => $dnis,
            'languages' => $languages,
            'skills' => $skills
        ]);
    }

    /**
     * Create a new Motion skill record.
     */
    public function store(Request $request)
    {
        // Set up for, and run validation on input fields
        $rules = [
            'brand_id'         => 'required',
            'dnis_id'          => 'required',
            'language_id'      => 'required',
            'motion_skills_id' => 'required'
        ];

        $validator = Validator::make(Input::all(), $rules);

        // Errors? Send user back to 'add new skill' page, with an error message list to display.
        if($validator->fails()) {
            return redirect()->route('motion_skill_maps.create')
                ->withErrors($validator)
                ->withInput();
        }

        // All good. Save new record and send user back to skills list page.
        $skillMap = new MotionSkillMap();

        $skillMap->brand_id         = $request->brand_id;
        $skillMap->dnis_id          = $request->dnis_id; 
        $skillMap->language_id      = $request->language_id;
        $skillMap->motion_skills_id = $request->motion_skills_id;

        $skillMap->save();

        return redirect()->route('motion_skill_maps.index');
    }

    /**
     * Show edit page for selected Motion skill record.
     */
    public function edit($id)
    {
        $skillMap  = MotionSkillMap::where('id', $id)->first();
        $brands    = Brand::select('id', 'name')->whereNotNull('client_id')->orderBy('name')->get();
        $dnis      = DNIS::select('id', 'brand_id', 'dnis')->where('platform', '!=', 'motion single q')->orderBy('dnis')->get();
        $languages = Language::select('id', 'language')->orderBy('language')->get();
        $skills    = MotionSkill::select('id', 'name', 'dnis')->orderBy('name')->get();

        return view('motion.skill_maps.edit', [
            'skillMap' => $skillMap,
            'brands' => $brands,
            'dnis' => $dnis,
            'languages' => $languages,
            'skills' => $skills
        ]);
    }

    /**
     * Update a Motion skill record.
     */
    public function update(Request $request, MotionSkillMap $skillMap)
    {
        $rules = array(
            'brand_id'         => 'required',
            'dnis_id'          => 'required',
            'language_id'      => 'required',
            'motion_skills_id' => 'required'
        );

        $validator = Validator::make(Input::all(), $rules);

        // Errors? Send user back to 'add new skill' page, with an error message list to display.
        if($validator->fails()) {
            return redirect()->route('motion_skill_maps.edit', $skillMap->id)
                ->withErrors($validator)
                ->withInput();
        }

        $skillMap->brand_id         = $request->brand_id;
        $skillMap->dnis_id          = $request->dnis_id;
        $skillMap->language_id      = $request->language_id;
        $skillMap->motion_skills_id = $request->motion_skills_id;

        $skillMap->save();

        session()->flash('flash_message', 'Skill was successfully edited!');

        return redirect()->route('motion_skill_maps.edit', $skillMap->id);
    }

    /**
     * Delete specific Motion skill record.
     */
    public function destroy($id)
    {
        $skillMap = MotionSkillMap::find($id)->first();

        try {
            if($skillMap) {
                $skillMap->delete();

                session()->flash('flash_message', 'Skill Map was successfully deleted');
            }

            return response()->json([
                'status'  => true,
                'message' => ''
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
