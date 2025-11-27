<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\TpvAlert;

class TpvAlertsController extends Controller
{
    function saveAlert(Request $request) {

        $rules = array(
            'start_date' => 'required',
            'end_date' => 'required',
            'message' => 'required'
        );

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->route('alerts')
                ->withErrors($validator)
                ->withInput();
        }
        else {

            $tpvAlertId = $request->get('id');
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            $message = $request->get('message');

            if ($tpvAlertId !== null) {
                $record = TpvAlert::find($tpvAlertId);

                session()->flash(
                    'flash_message',
                    'Data updated successfully!'
                );
            } else {
                $record = new TpvAlert();

                session()->flash(
                    'flash_message',
                    'Data added successfully!'
                );
            }

            $record->start_date = $start_date;
            $record->end_date = $end_date;
            $record->message = $message;
            $record->save();

            return redirect('/alerts');
        }
    }
    
    function getAlerts() {
        $tpvAlerts = TpvAlert::select('id', 'start_date', 'end_date', 'message')
        ->orderBy('created_at', 'desc')
        ->paginate(30);

        return response()->json($tpvAlerts);
    }

    function deleteAlert(Request $request) {
        $tpvAlertId = $request->get('id');
        $tpvAlert = TpvAlert::find($tpvAlertId);
        $tpvAlert->delete();

        return response()->json($request);
    }
}
