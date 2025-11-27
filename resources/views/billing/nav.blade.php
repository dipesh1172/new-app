<ul class="nav nav-tabs">
    <li class="nav-item">
        <a 
            class="nav-link {{ Request::is('billing/notapproved') ? 'active' : '' }}" 
            href="/billing/notapproved">
            Needs Approval
        </a>
    </li>
    <li class="nav-item">
        <a 
            class="nav-link {{ Request::is('billing') ? 'active' : '' }}" 
            href="/billing">
            Billing
        </a>
    </li>
    <li class="nav-item">
        <a 
            class="nav-link {{ Request::is('billing/charges') ? 'active' : '' }}" 
            href="/billing/charges">
            Charges
        </a>
    </li>
</ul>