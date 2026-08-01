    <!-- Sidebar Start -->
    <aside class="left-sidebar">
      <!-- Sidebar scroll-->
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="./index.html" class="text-nowrap logo-img">
            {{-- <img src="assets/images/logos/logo.svg" alt="" /> --}}
          </a>
          <button class="close-btn d-xl-none d-block sidebartoggler cursor-pointer border-0 bg-transparent" type="button"
            id="sidebarCollapse" aria-label="Close navigation" aria-controls="main-wrapper">
            <i class="ti ti-x fs-6"></i>
          </button>
        </div>
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav scroll-sidebar" data-simplebar="">
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <iconify-icon icon="solar:menu-dots-linear" class="nav-small-cap-icon fs-4"></iconify-icon>
              <span class="hide-menu">Home</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link active" href="./index.html" aria-expanded="false">
                <i class="ti ti-atom"></i>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between"  
                href="#" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-aperture"></i>
                  </span>
                  <span class="hide-menu">Analytical</span>
                </div>
                
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between"  
                href="#" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-shopping-cart"></i>
                  </span>
                  <span class="hide-menu">eCommerce</span>
                </div>
                
              </a>
            </li>
            <li class="sidebar-item">
              <button class="sidebar-link justify-content-between has-arrow border-0 w-100" type="button"
                data-bs-toggle="collapse" data-bs-target="#catalog-navigation"
                aria-expanded="false" aria-controls="catalog-navigation">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-layout-grid"></i>
                  </span>
                  <span class="hide-menu">Catalog</span>
                </div>
                
              </button>
              <ul id="catalog-navigation" aria-expanded="false" class="collapse first-level">
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="{{ route('admin.products.index') }}">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Products</span>
                    </div>
                    
                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"  
                    href="{{ route('admin.categories.index') }}">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Categories</span>
                    </div>
                    
                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"  
                    href="{{ route('admin.attributes.index') }}">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Attributes</span>
                    </div> 
                  </a>
                </li>
                <li class="sidebar-item">
                  <a class="sidebar-link justify-content-between"
                    href="{{ route('admin.inventory.index') }}">
                    <div class="d-flex align-items-center gap-3">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Inventory</span>
                    </div>
                  </a>
                </li>
              </ul>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between" href="{{ route('admin.orders.index') }}" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-receipt"></i>
                  </span>
                  <span class="hide-menu">Orders</span>
                </div>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between {{ request()->routeIs('admin.shipping-methods.*') ? 'active' : '' }}"
                href="{{ route('admin.shipping-methods.index') }}" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-truck-delivery"></i>
                  </span>
                  <span class="hide-menu">Shipping Methods</span>
                </div>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}"
                href="{{ route('admin.coupons.index') }}" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-ticket"></i>
                  </span>
                  <span class="hide-menu">Coupons</span>
                </div>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"
                href="{{ route('admin.customers.index') }}" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-users"></i>
                  </span>
                  <span class="hide-menu">Customers</span>
                </div>
              </a>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link justify-content-between {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"
                href="{{ route('admin.notifications.index') }}" aria-expanded="false">
                <div class="d-flex align-items-center gap-3">
                  <span class="d-flex">
                    <i class="ti ti-bell"></i>
                  </span>
                  <span class="hide-menu">Notifications</span>
                </div>
              </a>
            </li>
          </ul>
        </nav>
        <!-- End Sidebar navigation -->
      </div>
      <!-- End Sidebar scroll-->
    </aside>
    <!--  Sidebar End -->
