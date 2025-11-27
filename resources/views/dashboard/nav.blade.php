<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link {{ Request::is('dashboard') ? 'active' : '' }}" href="/dashboard">Sales Dashboard</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ Request::is('callcenter') ? 'active' : '' }}" href="/callcenter">Call Center Dashboard</a>
    </li>
</ul>