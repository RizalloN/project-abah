@php
    $mantriPerformance = (array) data_get($payload, 'nett_workbook.mantriPerformance', []);
    $rmKurProductivity = (array) data_get($payload, 'nett_workbook.rmKurProductivity', []);
    $formatInteger = static fn ($value): string => number_format((int) $value, 0, ',', '.');
    $nettFormats = [
        'formatInteger' => $formatInteger,
        'formatJuta' => static fn ($value): string => number_format((float) $value / 1_000_000, 0, ',', '.'),
        'formatAmount' => static fn ($value): string => 'Rp '.number_format((float) $value / 1_000_000, 0, ',', '.').' jt',
        'formatPercent' => static fn ($value): string => number_format((float) $value, 1, ',', '.').'%',
    ];
@endphp
<div class="kpi-nett-workbook" data-kpi-nett-workbook data-abah-no-table-guard="1">
    <header class="micro-nett-report-header">
        <div>
            <h2 id="micro-nett-title">Nett Disbursement <span>{{ $selectedRmCategory === 'mantri' ? 'Mantri' : 'RM Mikro' }}</span></h2>
            <p>{{ $selectedPeriodLabel }} · {{ $selectedRmCategory === 'mantri' ? data_get($mantriPerformance, 'total.branch', 'Area 6') : data_get($rmKurProductivity, 'scope_label', 'Area 6') }}</p>
        </div>
        <span class="micro-nett-unit-label">Akumulasi MTD · Rp juta</span>
    </header>
    <dl class="micro-nett-summary">
        @if($selectedRmCategory === 'mantri')
            <div><dt>Nett disbursement</dt><dd>{{ $nettFormats['formatAmount'](data_get($mantriPerformance, 'total.net_disbursement.amount', 0)) }}</dd></div>
            <div><dt>Mantri PT non Briguna</dt><dd>{{ $formatInteger(data_get($mantriPerformance, 'total.headcount.pt_non_briguna', 0)) }} <small>orang</small></dd></div>
            <div><dt>Mantri kontrak</dt><dd>{{ $formatInteger(data_get($mantriPerformance, 'total.headcount.contract', 0)) }} <small>orang</small></dd></div>
            <div><dt>Hari kerja efektif</dt><dd>{{ $formatInteger(data_get($mantriPerformance, 'working_days', 0)) }} <small>hari</small></dd></div>
        @else
            <div><dt>Total nett sumber</dt><dd>{{ $nettFormats['formatAmount'](data_get($rmKurProductivity, 'total.realisasi_os', 0)) }}</dd></div>
            <div><dt>Nett RM teridentifikasi</dt><dd>{{ $nettFormats['formatAmount'](data_get($rmKurProductivity, 'assigned_total.realisasi_os', 0)) }}</dd></div>
            <div><dt>RM sesuai referensi SDM</dt><dd>{{ $formatInteger(data_get($rmKurProductivity, 'total.rm_count', 0)) }} <small>orang</small></dd></div>
            <div><dt>Rekening sumber</dt><dd>{{ $formatInteger(data_get($rmKurProductivity, 'total.realisasi_deb', 0)) }} <small>rekening</small></dd></div>
        @endif
    </dl>
    @if($selectedRmCategory === 'mantri')
        @include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-mantri-nett-sheets', $nettFormats)
    @else
        @include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-rm-nett-sheets', $nettFormats)
    @endif
</div>
@include('report.dashboard-pinjaman._kinerjarmmikro_partials.micro-nett-styles')

<script>
(function() {
    function initWorkbookFreezeScroll() {
        const wrappers = document.querySelectorAll('.micro-nett-wrap');
        
        wrappers.forEach(function(wrap) {
            if (wrap.__freezeScrollInit) return;
            wrap.__freezeScrollInit = true;

            function checkScroll() {
                if (wrap.scrollLeft > 4) {
                    wrap.classList.add('is-scrolled-x');
                } else {
                    wrap.classList.remove('is-scrolled-x');
                }
            }
            wrap.addEventListener('scroll', checkScroll, { passive: true });
            checkScroll();

            let isDown = false;
            let startX = 0;
            let scrollStart = 0;
            let hasDragged = false;

            wrap.addEventListener('mousedown', function(e) {
                if (e.target.closest('button, input, select, a, label')) return;
                if (e.button !== 0) return;

                isDown = true;
                hasDragged = false;
                startX = e.pageX - wrap.getBoundingClientRect().left;
                scrollStart = wrap.scrollLeft;
            });

            window.addEventListener('mouseup', function() {
                if (!isDown) return;
                isDown = false;
                wrap.classList.remove('is-dragging');
            });

            wrap.addEventListener('mousemove', function(e) {
                if (!isDown) return;
                const currentX = e.pageX - wrap.getBoundingClientRect().left;
                const distance = currentX - startX;
                if (Math.abs(distance) > 5) {
                    hasDragged = true;
                    wrap.classList.add('is-dragging');
                    wrap.scrollLeft = scrollStart - distance;
                }
            });
        });

        document.querySelectorAll('.micro-nett-scroll-btn').forEach(function(btn) {
            if (btn.__scrollBtnInit) return;
            btn.__scrollBtnInit = true;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const container = btn.closest('.micro-nett-sheets') || document;
                const visiblePanels = Array.from(container.querySelectorAll('.micro-nett-sheet-panel')).filter(function(panel) {
                    return window.getComputedStyle(panel).display !== 'none';
                });
                const targetPanel = visiblePanels[0] || container;
                const wrap = targetPanel.querySelector('.micro-nett-wrap');
                if (!wrap) return;

                const dir = btn.dataset.scrollDir === 'left' ? -1 : 1;
                wrap.scrollBy({ left: dir * 360, behavior: 'smooth' });
            });
        });

        document.querySelectorAll('.micro-nett-sheet-nav input[type="radio"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                setTimeout(function() {
                    const activePanel = document.querySelector('.micro-nett-sheet-panel:not([style*="display: none"])');
                    if (activePanel) {
                        const wrap = activePanel.querySelector('.micro-nett-wrap');
                        if (wrap) {
                            if (wrap.scrollLeft > 4) {
                                wrap.classList.add('is-scrolled-x');
                            } else {
                                wrap.classList.remove('is-scrolled-x');
                            }
                        }
                    }
                }, 50);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWorkbookFreezeScroll);
    } else {
        initWorkbookFreezeScroll();
    }
})();
</script>
