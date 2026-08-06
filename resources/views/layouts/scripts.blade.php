{{-- Third Party Plugin: OverlayScrollbars --}}
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
    crossorigin="anonymous"></script>

{{-- Bootstrap 5 --}}
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
    crossorigin="anonymous"></script>

{{-- AdminLTE --}}
<script src="{{ asset('adminlte/js/adminlte.js') }}"></script>


{{-- OverlayScrollbars Configuration --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const sidebarWrapper = document.querySelector('.sidebar-wrapper');

        const isMobile = window.innerWidth <= 992;

        if (
            sidebarWrapper &&
            OverlayScrollbarsGlobal?.OverlayScrollbars &&
            !isMobile
        ) {

            OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {

                scrollbars: {
                    theme: 'os-theme-light',
                    autoHide: 'leave',
                    clickScroll: true
                }

            });

        }

    });
</script>

{{-- Page-specific scripts --}}
@stack('scripts')