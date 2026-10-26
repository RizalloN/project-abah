{{--
    Ikon filter sudah tampil sebelum script preview selesai inisialisasi (halaman
    preview besar). Klik pada fase itu ditahan lalu dijalankan ulang begitu
    handler filter terpasang, sehingga klik pertama tidak pernah hilang.
--}}
<script>
    (function () {
        if (window.__importFilterMarkReady) {
            return;
        }

        var ready = false;
        var pendingButton = null;

        document.addEventListener('click', function (event) {
            if (ready) {
                return;
            }

            var button = event.target && event.target.closest ? event.target.closest('.import-preview-table .filter-btn') : null;
            if (!button) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (pendingButton) {
                pendingButton.classList.remove('is-filter-pending');
                pendingButton.removeAttribute('aria-busy');
            }
            pendingButton = button;
            button.classList.add('is-filter-pending');
            button.setAttribute('aria-busy', 'true');
        }, true);

        window.__importFilterMarkReady = function () {
            if (ready) {
                return;
            }

            ready = true;
            var button = pendingButton;
            pendingButton = null;
            if (button) {
                button.classList.remove('is-filter-pending');
                button.removeAttribute('aria-busy');
                window.setTimeout(function () {
                    button.click();
                }, 0);
            }
        };
    }());
</script>
<style>
    .import-preview-table .filter-btn.is-filter-pending {
        cursor: progress;
        opacity: .6;
    }
</style>
