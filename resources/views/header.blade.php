<header class="app-header navbar">
    <button class="navbar-toggler mobile-sidebar-toggler d-lg-none mr-auto" type="button"><i class="fa fa-bars"></i></button>
    <a class="navbar-brand" href="/dashboard"></a>
    <button class="navbar-toggler sidebar-minimizer d-md-down-none" type="button"><i class="fa fa-bars"></i></button>
    <ul class="nav navbar-nav ml-auto padding-right-15">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle nav-link" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                <span class="d-md-down-none">{{ @Auth::user()->first_name }} {{ @Auth::user()->last_name }}</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
            <a class="dropdown-item" href="/tpv_staff/{{ Auth::id() }}/time"><span class="fa fa-clock-o"></span> Time Clock</a>
                <div class="divider"></div>
                <a class="dropdown-item" href="/logout"><span class="fa fa-sign-out"></span> Logout</a>
            </div>
        </li>
    </ul>
</header>
