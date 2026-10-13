      <!--begin::Header-->
      <nav class="app-header navbar navbar-expand bg-body">
        <!--begin::Container-->
        <div class="container-fluid">
          <!--begin::Start Navbar Links-->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a
                class="nav-link"
                data-lte-toggle="sidebar"
                href="#"
                role="button"
                aria-label="Toggle sidebar"
              >
                <i class="bi bi-list"></i>
              </a>
            </li>
          </ul>
          <!--end::Start Navbar Links-->

          <!--begin::End Navbar Links-->
          <ul class="navbar-nav ms-auto align-items-center">
            <!--begin::Company Switcher-->
            @php
              $activeCompany = app(\App\Support\Tenancy\CurrentCompany::class)->get();
              $otherCompanies = auth()->user()->companies()
                  ->where('status', \App\Models\Company::STATUS_ACTIVE)
                  ->whereKeyNot($activeCompany?->id)
                  ->orderBy('name')
                  ->get();
            @endphp
            @if ($activeCompany)
              <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"
                   aria-label="Current company: {{ $activeCompany->name }}">
                  <i class="bi bi-building"></i>
                  <span class="ms-1">{{ $activeCompany->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><h6 class="dropdown-header">Current company</h6></li>
                  <li><span class="dropdown-item-text fw-semibold">{{ $activeCompany->name }}</span></li>
                  @if ($otherCompanies->isNotEmpty())
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Switch to</h6></li>
                    @foreach ($otherCompanies as $otherCompany)
                      <li>
                        <form method="POST" action="{{ route('companies.switch', $otherCompany) }}">
                          @csrf
                          <button type="submit" class="dropdown-item">{{ $otherCompany->name }}</button>
                        </form>
                      </li>
                    @endforeach
                  @endif
                </ul>
              </li>
            @endif
            <!--end::Company Switcher-->

            <!--begin::Notifications-->
            @php
              $unreadCount = auth()->user()->unreadNotifications()->count();
              $latestNotifications = auth()->user()->notifications()->limit(5)->get();
            @endphp
            <li class="nav-item dropdown">
              <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false"
                 aria-label="Notifications{{ $unreadCount ? ", {$unreadCount} unread" : '' }}">
                <i class="bi bi-bell-fill"></i>
                @if ($unreadCount)
                  <span class="navbar-badge badge text-bg-danger">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                @endif
              </a>
              <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <span class="dropdown-item dropdown-header">
                  {{ $unreadCount ? $unreadCount.' unread' : 'No unread notifications' }}
                </span>
                @foreach ($latestNotifications as $notification)
                  <div class="dropdown-divider"></div>
                  <a href="{{ route('notifications.open', $notification->id) }}" class="dropdown-item {{ $notification->read_at ? '' : 'fw-semibold' }}">
                    <div class="text-truncate">{{ $notification->data['title'] ?? 'Notification' }}</div>
                    <small class="text-secondary fw-normal">{{ $notification->created_at->diffForHumans() }}</small>
                  </a>
                @endforeach
                <div class="dropdown-divider"></div>
                <a href="{{ route('notifications.index') }}" class="dropdown-item dropdown-footer">See all notifications</a>
              </div>
            </li>
            <!--end::Notifications-->
            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
              <a
                class="nav-link"
                href="#"
                data-lte-toggle="fullscreen"
                aria-label="Toggle fullscreen"
              >
                <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                <i data-lte-icon="minimize" class="bi bi-fullscreen-exit d-none"></i>
              </a>
            </li>
            <!--end::Fullscreen Toggle-->

            <!--begin::Color Mode Toggle (#6010)-->
            <li class="nav-item dropdown">
              <a
                class="nav-link"
                href="#"
                id="bd-theme"
                aria-label="Toggle color scheme"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                <i class="bi bi-sun-fill" data-lte-theme-icon="light"></i>
                <i class="bi bi-moon-fill d-none" data-lte-theme-icon="dark"></i>
                <i class="bi bi-circle-half d-none" data-lte-theme-icon="auto"></i>
              </a>
              <ul
                class="dropdown-menu dropdown-menu-end"
                aria-labelledby="bd-theme"
                style="--bs-dropdown-min-width: 8rem"
              >
                <li>
                  <button
                    type="button"
                    class="dropdown-item d-flex align-items-center"
                    data-bs-theme-value="light"
                    aria-pressed="false"
                  >
                    <i class="bi bi-sun-fill me-2"></i>
                    Light
                    <i class="bi bi-check-lg ms-auto d-none"></i>
                  </button>
                </li>
                <li>
                  <button
                    type="button"
                    class="dropdown-item d-flex align-items-center"
                    data-bs-theme-value="dark"
                    aria-pressed="false"
                  >
                    <i class="bi bi-moon-fill me-2"></i>
                    Dark
                    <i class="bi bi-check-lg ms-auto d-none"></i>
                  </button>
                </li>
                <li>
                  <button
                    type="button"
                    class="dropdown-item d-flex align-items-center active"
                    data-bs-theme-value="auto"
                    aria-pressed="true"
                  >
                    <i class="bi bi-circle-half me-2"></i>
                    Auto
                    <i class="bi bi-check-lg ms-auto d-none"></i>
                  </button>
                </li>
              </ul>
            </li>
            <!--end::Color Mode Toggle-->

            <!--begin::User Menu Dropdown-->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-4"></i>
                <span class="d-none d-md-inline ms-1">{{ auth()->user()->name }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!--begin::User Header-->
                <li class="user-header text-bg-primary">
                  <i class="bi bi-person-circle" style="font-size: 3rem;"></i>
                  <p>
                    {{ auth()->user()->name }}
                    <small>{{ auth()->user()->email }}</small>
                  </p>
                </li>
                <!--end::User Header-->
                <!--begin::Menu Footer-->
                <li class="user-footer d-flex justify-content-between">
                  <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Profile</a>
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Sign out</button>
                  </form>
                </li>
                <!--end::Menu Footer-->
              </ul>
            </li>
            <!--end::User Menu Dropdown-->
          </ul>
          <!--end::End Navbar Links-->
        </div>
        <!--end::Container-->
      </nav>
      <!--end::Header-->
