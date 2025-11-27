<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link {{ Request::is('kb') ? 'active' : '' }}" href="/kb">Knowledge Base</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ Request::is('kb/video') ? 'active' : '' }}" href="/kb/video">Videos</a>
    </li>
</ul>