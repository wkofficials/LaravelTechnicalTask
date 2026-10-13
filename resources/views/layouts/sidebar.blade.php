      <!--begin::Sidebar-->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand">
          <!--begin::Brand Link-->
          <a href="{{ auth()->user()->current_company_id ? route('dashboard') : (auth()->user()->is_super_admin ? route('platform.dashboard') : url('/')) }}" class="brand-link">
            <span class="if-logo" aria-hidden="true"><i class="bi bi-boxes"></i></span>
            <span class="brand-text">{{ config('app.name') }}</span>
          </a>
          <!--end::Brand Link-->
        </div>
        <!--end::Sidebar Brand-->
        <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
          <nav class="mt-2" aria-label="Main navigation">
            <!--begin::Sidebar Menu-->
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              data-accordion="false"
              id="navigation"
            >
              @if (app(\App\Support\Tenancy\CurrentCompany::class)->has())
                <li class="nav-item">
                  <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-speedometer2"></i>
                    <p>Dashboard</p>
                  </a>
                </li>
              @endif
              <li class="nav-item">
                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                  <i class="nav-icon bi bi-person-gear"></i>
                  <p>Profile</p>
                </a>
              </li>
              @can('view records')
                @foreach (app(\App\Datasets\DatasetRegistry::class)->all() as $sidebarDataset)
                  <li class="nav-item">
                    <a href="{{ route('records.index', $sidebarDataset->key()) }}"
                       class="nav-link {{ request()->routeIs('records.*') && request()->route('dataset')?->key() === $sidebarDataset->key() ? 'active' : '' }}">
                      <i class="nav-icon bi bi-table"></i>
                      <p>{{ $sidebarDataset->name() }}</p>
                    </a>
                  </li>
                @endforeach
              @endcan
              @can('import records')
                <li class="nav-item">
                  <a href="{{ route('imports.create') }}" class="nav-link {{ request()->routeIs('imports.create') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-file-earmark-spreadsheet"></i>
                    <p>Import Records</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{ route('imports.index') }}" class="nav-link {{ request()->routeIs('imports.index', 'imports.show') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-clock-history"></i>
                    <p>Import History</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{ route('templates.index') }}" class="nav-link {{ request()->routeIs('templates.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-diagram-3"></i>
                    <p>Import Templates</p>
                  </a>
                </li>
              @endcan
              @canany(['manage team', 'view usage', 'view audit log', 'manage billing', 'manage company'])
                <li class="nav-header">COMPANY</li>
              @endcanany
              @can('manage team')
                <li class="nav-item">
                  <a href="{{ route('team.index') }}" class="nav-link {{ request()->routeIs('team.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-people"></i>
                    <p>Team</p>
                  </a>
                </li>
              @endcan
              @can('view usage')
                <li class="nav-item">
                  <a href="{{ route('usage') }}" class="nav-link {{ request()->routeIs('usage') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-speedometer"></i>
                    <p>Usage &amp; Plan</p>
                  </a>
                </li>
              @endcan
              @can('view audit log')
                <li class="nav-item">
                  <a href="{{ route('audit.index') }}" class="nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-journal-text"></i>
                    <p>Audit Log</p>
                  </a>
                </li>
              @endcan
              @can('manage billing')
                <li class="nav-item">
                  <a href="{{ route('billing.index') }}" class="nav-link {{ request()->routeIs('billing.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-credit-card"></i>
                    <p>Billing</p>
                  </a>
                </li>
              @endcan
              @can('manage company')
                <li class="nav-item">
                  <a href="{{ route('company.settings') }}" class="nav-link {{ request()->routeIs('company.settings*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-gear"></i>
                    <p>Company Settings</p>
                  </a>
                </li>
              @endcan
              @if (auth()->user()->is_super_admin)
                <li class="nav-header">PLATFORM</li>
                <li class="nav-item">
                  <a href="{{ route('platform.dashboard') }}" class="nav-link {{ request()->routeIs('platform.dashboard') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-hdd-network"></i>
                    <p>Overview</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{ route('platform.companies.index') }}" class="nav-link {{ request()->routeIs('platform.companies.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-buildings"></i>
                    <p>Companies</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{ route('platform.billing') }}" class="nav-link {{ request()->routeIs('platform.billing', 'platform.invoices.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-receipt"></i>
                    <p>Billing</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="{{ route('platform.plans') }}" class="nav-link {{ request()->routeIs('platform.plans*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-sliders"></i>
                    <p>Plans</p>
                  </a>
                </li>
              @endif
            </ul>
            <!--end::Sidebar Menu-->
          </nav>

          {{-- Plan & monthly usage at a glance --}}
          @php $sidebarCompany = app(\App\Support\Tenancy\CurrentCompany::class)->get(); @endphp
          @if ($sidebarCompany && auth()->user()->can('view usage'))
            @php
              $sidebarLimits = \App\Support\PlanLimits::for($sidebarCompany);
              $sidebarLimit = $sidebarLimits->plan()->monthly_import_rows;
              $sidebarUsed = $sidebarLimits->importRowsUsed();
              $sidebarPercent = $sidebarLimit > 0 ? min(100, (int) floor($sidebarUsed / $sidebarLimit * 100)) : 100;
            @endphp
            <a href="{{ route('usage') }}" class="if-sidebar-card d-block text-decoration-none">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold text-white"><i class="bi bi-gem me-1 text-warning"></i> {{ $sidebarLimits->plan()->name }} plan</span>
                <span class="small">{{ $sidebarPercent }}%</span>
              </div>
              <div class="progress" role="progressbar" aria-label="Monthly import usage" aria-valuenow="{{ $sidebarPercent }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar {{ $sidebarPercent >= 100 ? 'bg-danger' : ($sidebarPercent >= 80 ? 'bg-warning' : '') }}" style="width: {{ $sidebarPercent }}%"></div>
              </div>
              <div class="small mt-2 opacity-75">{{ number_format($sidebarUsed) }} / {{ number_format($sidebarLimit) }} rows this month</div>
            </a>
          @endif
        </div>
        <!--end::Sidebar Wrapper-->
      </aside>
      <!--end::Sidebar-->
