<ul class="nav nav-tabs">
    <li class="nav-item">
        <a class="nav-link {{ Request::is('brands/*/vendor/*/editVendor') ? 'active' : '' }}" href="/brands/{{$brand->id}}/vendor/{{$vendor->id}}/editVendor">Vendors</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ Request::is('brands/*/vendor/*/loginLanding') ? 'active' : '' }}" href="/brands/{{$brand->id}}/vendor/{{$vendor->id}}/loginLanding">Login Landing (TM Only)</a>
    </li>
</ul>