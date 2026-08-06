      <!--begin::Sidebar-->
      <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand">
          <!--begin::Brand Link-->
          <a href="{{ route('dashboard') }}" class="brand-link">
            <!--begin::Brand Image-->
            <img
              src="{{ asset('adminlte/assets/img/AdminLTELogo.png') }}"
              alt="{{ config('app.name') }} Logo"
              class="brand-image opacity-75 shadow"
            />
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light">{{ config('app.name') }}</span>
            <!--end::Brand Text-->
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
              <li class="nav-item">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                  <i class="nav-icon bi bi-speedometer2"></i>
                  <p>Dashboard</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                  <i class="nav-icon bi bi-person-gear"></i>
                  <p>Profile</p>
                </a>
              </li>
              @can('view records')
                <li class="nav-item">
                  <a href="{{ route('records.index') }}" class="nav-link {{ request()->routeIs('records.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-table"></i>
                    <p>Records</p>
                  </a>
                </li>
              @endcan
              @can('import records')
                <li class="nav-item">
                  <a href="{{ route('imports.create') }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}">
                    <i class="nav-icon bi bi-file-earmark-spreadsheet"></i>
                    <p>Import Records</p>
                  </a>
                </li>
              @endcan
            </ul>
            <!--end::Sidebar Menu-->
          </nav>
        </div>
        <!--end::Sidebar Wrapper-->
      </aside>
      <!--end::Sidebar-->
