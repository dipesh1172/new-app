<div class="sidebar">
    <sidebar />
    {{-- <nav class="sidebar-nav">
        <ul class="nav">
            @if(Route::currentRouteName() != 'globo.search')
            <li class="nav-item">
                <form method="GET" action="/search">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control bg-dark text-white" name="query" placeholder="Search" aria-label="Search">
                        <div class="input-group-append">
                            <button class="btn btn-outline-primary" type="button"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link" href="/dashboard">
                    <i class="fa fa-dashboard"></i> Call Center
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/sales_dashboard">
                    <i class="fa fa-dashboard"></i> Sales
                </a>
            </li>
            <li class="nav-title">
                Pages
            </li>
            @if (in_array(@Auth::user()->role_id, array('1', '2', '3', '4', '10')))
            <li class="nav-item">
                <a class="nav-link {{ Request::is('brands*') ? 'active' : '' }}" href="/brands"><i class="fa fa-tags"></i> Brands</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('brand_users') ? 'active' : '' }}" href="/brand_users"><i class="fa fa-tags"></i> Brand Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('billing*') ? 'active' : '' }}" href="{{route('billing.index')}}"><i class="fa fa-usd"></i> Billing</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('config*') ? 'active' : '' }}" href="{{route('config.index')}}"><i class="fa fa-gears"></i> Configuration</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('dnis') ? 'active' : '' }}" href="/dnis"><i class="fa fa-phone-square"></i> DNIS</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('kb/home') ? 'active' : '' }}" href="{{route('kb/home')}}"><i class="fa fa-book"></i> Knowledge Base</a>
            </li>
            {{-- <li class="nav-item">
                <a class="nav-link {{ Request::is('documents') ? 'active' : '' }}" href="/documents"><i class="fa fa-file"></i> Documents</a>
            </li> --}}
            {{-- <li class="nav-item">
                <a class="nav-link {{ Request::is('utilities') ? 'active' : '' }}" href="/utilities"><i class="fa fa-bolt"></i> Utilities</a>
            </li> --}}
            {{-- @endif

            @if (in_array(@Auth::user()->role_id, array('1', '3', '4', '8', '10')))
            <li class="nav-item" style="margin-left: 20px;">
                <i class="fa fa-hand-paper-o"></i> QA
            </li>
            @if (in_array(@Auth::user()->role_id, array('1', '3', '4')))
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="{{ URL::route('qa_review.index') }}"><i class="fa fa-hand-paper-o"></i> Call Followups</a>
            </li>
            @endif
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="/events"><i class="fa fa-archive"></i> Events</a>
            </li>
            @endif

            <li class="nav-item">
                <a class="nav-link {{ Request::is('reports') ? 'active' : '' }}" href="/reports"><i class="fa fa-table"></i> Reports</a>
            </li>

            @if (in_array(@Auth::user()->role_id, array('1', '5', '6', '9', '10')))
            <li class="nav-item nav-dropdown" style="margin-left: 20px;">
                <i class="fa fa-hand-paper-o"></i> Users
            </li>

            @if (in_array(@Auth::user()->role_id, array('1', '9')))
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link {{ Request::is('agent*') ? 'active' : '' }}" href="{{route('tpv_staff.agents')}}"><i class="fa fa-user-circle-o "></i> Agents</a>
            </li>
            @endif
            @if (in_array(@Auth::user()->role_id, array('1', '5', '6', '10')))
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link {{ Request::is('tpv_staff*') ? 'active' : '' }}" href="{{route('tpv_staff.index')}}"><i class="fa fa-id-badge"></i> TPV Staff</a>
            </li>
            @endif
            @endif

            @if (in_array(@Auth::user()->role_id, array('1')))
            <li class="nav-item" style="margin-left: 20px;">
                <i class="fa fa-ambulance"></i> Support
            </li>
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="{{route('support.clear_test_calls')}}"><i class="fa fa-trash-o"></i> Clear Test Calls</a>
            <li>
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="{{route('support.clear_cache')}}"><i class="fa fa-trash-o"></i> Clear Caches</a>
            <li>
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="{{route('twilio.testCalls')}}"><i class="fa fa-phone"></i> Make Test Calls</a>
            <li>
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" target="_blank" href="/qa-tool/callsearch"><i class="fa fa-search"></i> Call Status Search</a>
            </li>
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="/chat-settings"><i class="fa fa-envelope"></i> Chat Settings</a>
            </li>
            <li class="nav-item indent-right" style="margin-left:20px;">
                <a class="nav-link" href="{{route('issues_dashboard')}}"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Issues Dashboard</a>
            </li>
            @endif

            <li class="nav-item">
                <a class="nav-link {{ Request::is('utilities') ? 'active' : '' }}" href="/utilities"><i class="fa fa-bolt"></i> Utilities</a>
            </li>

            <li class="nav-item">
                <a class="nav-link {{ Request::is('vendors') ? 'active' : '' }}" href="/vendors"><i class="fa fa-tags"></i> Vendors</a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="/issues"><i class="fa fa-bug"></i> Floor Issues</a>
            </li>
            @if(@Auth::user()->role_id === 1)
            <li class="nav-item">
                <a class="nav-link" href="/errors"><i class="fa fa-bug"></i> Site Errors</a>
            </li>
            @endif
        </ul>
    </nav> --}}
</div>