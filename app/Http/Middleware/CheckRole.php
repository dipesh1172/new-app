<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $allowed = NULL;
        switch ($request->route()->getName()) {
            //home controller
            case 'home':
                $allowed = [1,2,3,4,5,6,7,8,9];
                break;

            //brands controller
            case 'brands.index':
            case 'brands.search':
            case 'brands.create':
            case 'brands.login':
            case 'brands.store':
            case 'brands.edit':
            case 'brands.update':
            case 'brands.destroy':
                $allowed = [1,2];
                break;

            //clients controller
            case 'clients.index':
            case 'clients.search':
            case 'clients.create':
            case 'clients.store':
            case 'clients.edit':
            case 'clients.update':
            case 'clients.destroy':
                $allowed = [1,2];
                break;

            //dnis controller
            case 'dnis.index':
            case 'dnis.create':
            case 'dnis.store':
            case 'dnis.edit':
            case 'dnis.update':
            case 'dnis.destroy':
                $allowed = [1,2];
                break;

            //events controller
            case 'events.index':
            case 'events.show':
                $allowed = [1,2,3,4,8];
                break;
            
            //qa_review controller
            case 'qa_review.index':
            case 'qa_review.search':
            case 'qa_review.show':
            case 'qa_review.update':
                $allowed = [1,3,4];
                break;

            //qa_final controller
            case 'qa_final.index':
            case 'qa_final.show':
            case 'qa_final.update':
                $allowed = [1,3,4];
                break;

            //rules controller
            case 'rules.index':
            case 'rules.create':
            case 'rules.store':
            case 'rules.edit':
            case 'rules.update':
            case 'rules.destroy':
                $allowed = [1,2];
                break;

            //tpv_staff controller
            case 'tpv_staff.index':
            case 'tpv_staff.create':
            case 'tpv_staff.store':
            case 'tpv_staff.edit':
            case 'tpv_staff.update':
            case 'tpv_staff.destroy':
                $allowed = [1];
                break;

            //twilio controller
            case 'twilio.calls':
            case 'twilio.get_queues':
            case 'twilio.get_workers':
                $allowed = [1,2,3,4,8];
                break;

            //if not caught above, then the request will be denied and rerouted to dashboard
            //this prevents url tampering and security holes due to unsecured routes
            default:
                return redirect('/dashboard');
                break;
        }
        if (!is_null($allowed) && !in_array(Auth::user()->role_id, $allowed)) {
            return redirect('/dashboard');
        }
        
        return $next($request);
    }
}
