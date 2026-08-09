      <!--  Header Start -->
      <header class="app-header">
          <nav class="navbar navbar-expand-lg navbar-light">
              <ul class="navbar-nav">
                  <li class="nav-item d-block d-xl-none">
                      <button class="nav-link sidebartoggler border-0 bg-transparent" id="headerCollapse" type="button"
                          aria-label="Open navigation" aria-controls="main-wrapper">
                          <i class="ti ti-menu-2"></i>
                      </button>
                  </li>
                  <li class="nav-item dropdown">
                      <a class="nav-link position-relative" href="{{ route('admin.notifications.index') }}"
                          aria-label="Notifications">
                          <i class="ti ti-bell"></i>
                          @if ($adminNotificationCount > 0)
                              <span
                                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                  {{ $adminNotificationCount }}
                              </span>
                          @endif
                      </a>
                  </li>
              </ul>
              <div class="navbar-collapse justify-content-end px-0" id="navbarNav">
                  <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-end">

                      <li class="nav-item dropdown">
                          <button class="nav-link border-0 bg-transparent" type="button" id="drop2"
                              data-bs-toggle="dropdown" aria-expanded="false"
                              aria-label="Open administrator account menu">

                              @if ($adminLogoUrl)
                                  <img src="{{ $adminLogoUrl }}" alt="{{ $adminCompanyName }}" class="img-fluid"
                                      style="max-height: 40px;">
                              @endif
                          </button>
                          <div class="dropdown-menu dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop2">
                              <div class="message-body">
                                  {{-- <a href="javascript:void(0)" class="d-flex align-items-center gap-2 dropdown-item">
                                      <i class="ti ti-user fs-6"></i>
                                      <p class="mb-0 fs-3">My Profile</p>
                                  </a> --}}
                                  <form action="{{ route('admin.logout') }}" method="POST" class="mx-3 mt-2">
                                      @csrf
                                      <button type="submit" class="btn btn-outline-primary w-100">Logout</button>
                                  </form>
                              </div>
                          </div>
                      </li>
                  </ul>
              </div>
          </nav>
      </header>
      <!--  Header End -->
