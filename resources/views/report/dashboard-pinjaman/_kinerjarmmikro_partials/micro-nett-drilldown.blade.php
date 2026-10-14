{{--
    Shared double-click drilldown for the Nett Disbursement workbook.
    Expects $nettDrill (object with deduplicated 'people' rows and 'lists' of ['title', 'scope', 'ids']) and $nettDrillColumns
    (list of ['key', 'label', 'align'?, 'format'?]) collected while the sheet cells rendered.
--}}
<script type="application/json" data-nett-drill-source>@json(['columns' => $nettDrillColumns, 'people' => $nettDrill->people, 'lists' => $nettDrill->lists])</script>

@once
    <div class="modal fade micro-nett-drill-modal" id="microNettDrillModal" tabindex="-1" role="dialog" aria-labelledby="microNettDrillModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="microNettDrillModalTitle" data-nett-drill-title>Daftar Personel</h5>
                        <div class="micro-nett-drill-modal__scope" data-nett-drill-scope></div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="micro-nett-drill-modal__toolbar">
                        <span class="micro-nett-drill-modal__count" data-nett-drill-count></span>
                        <input type="search" class="form-control form-control-sm" placeholder="Cari PN / nama / unit..." data-nett-drill-search>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped micro-nett-drill-modal__table mb-0">
                            <thead><tr data-nett-drill-head></tr></thead>
                            <tbody data-nett-drill-body></tbody>
                            <tfoot data-nett-drill-foot></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [data-nett-drill] {
            cursor: pointer;
            user-select: none;
        }

        [data-nett-drill]:hover,
        [data-nett-drill]:focus {
            outline: 2px solid #0b5cab;
            outline-offset: -2px;
        }

        .micro-nett-drill-modal__scope {
            margin-top: .15rem;
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
        }

        .micro-nett-drill-modal__toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .65rem;
        }

        .micro-nett-drill-modal__toolbar input {
            max-width: 260px;
        }

        .micro-nett-drill-modal__count {
            color: #334155;
            font-size: .8rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .micro-nett-drill-modal__table {
            font-size: .78rem;
        }

        .micro-nett-drill-modal__table thead th {
            position: sticky;
            top: 0;
            background: #f1f5f9;
            white-space: nowrap;
        }

        .micro-nett-drill-modal__table tfoot td {
            font-weight: 800;
            background: #f8fafc;
        }
    </style>

    <script>
        (function () {
            const modal = document.getElementById('microNettDrillModal');
            if (!modal) {
                return;
            }
            // Escape scroll/overflow containers so the fixed-position modal is never clipped.
            document.body.appendChild(modal);

            const head = modal.querySelector('[data-nett-drill-head]');
            const body = modal.querySelector('[data-nett-drill-body]');
            const foot = modal.querySelector('[data-nett-drill-foot]');
            const count = modal.querySelector('[data-nett-drill-count]');
            const scope = modal.querySelector('[data-nett-drill-scope]');
            const title = modal.querySelector('[data-nett-drill-title]');
            const search = modal.querySelector('[data-nett-drill-search]');
            let activeColumns = [];
            let activeRows = [];

            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[char]);
            const formatters = {
                juta: value => (Number(value || 0) / 1000000).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
                integer: value => Number(value || 0).toLocaleString('id-ID'),
            };
            const formatCell = (column, value) => column.format && formatters[column.format]
                ? formatters[column.format](value)
                : escapeHtml(value === null || value === '' ? '-' : value);
            const sourceFor = () => {
                const source = document.querySelector('[data-nett-drill-source]');
                if (!source) {
                    return null;
                }
                if (!source.__nettDrillData) {
                    try {
                        source.__nettDrillData = JSON.parse(source.textContent || '{}');
                    } catch (error) {
                        source.__nettDrillData = { columns: [], people: [], lists: [] };
                    }
                }

                return source.__nettDrillData;
            };

            const render = function () {
                const keyword = (search.value || '').trim().toLowerCase();
                const searchable = activeColumns.filter(column => !column.format).map(column => column.key);
                const rows = keyword === ''
                    ? activeRows
                    : activeRows.filter(row => searchable.some(key => String(row[key] ?? '').toLowerCase().includes(keyword)));

                count.textContent = keyword === ''
                    ? `${activeRows.length} orang`
                    : `${rows.length} dari ${activeRows.length} orang`;
                body.innerHTML = rows.length
                    ? rows.map((row, index) => '<tr><td class="text-center">' + (index + 1) + '</td>'
                        + activeColumns.map(column => `<td class="${column.align === 'right' ? 'text-right' : ''}">${formatCell(column, row[column.key])}</td>`).join('')
                        + '</tr>').join('')
                    : `<tr><td colspan="${activeColumns.length + 1}" class="text-center text-muted py-3">Tidak ada personel pada kategori ini.</td></tr>`;

                const summed = activeColumns.filter(column => column.format);
                foot.innerHTML = rows.length && summed.length
                    ? '<tr><td></td>' + activeColumns.map((column, index) => {
                        if (column.format) {
                            return `<td class="text-right">${formatters[column.format](rows.reduce((total, row) => total + Number(row[column.key] || 0), 0))}</td>`;
                        }

                        return index === 0 ? '<td>Total</td>' : '<td></td>';
                    }).join('') + '</tr>'
                    : '';
            };

            const open = function (cell) {
                const data = sourceFor();
                const list = data?.lists?.[Number(cell.dataset.nettDrill)];
                if (!list) {
                    return;
                }

                activeColumns = data.columns || [];
                activeRows = (list.ids || []).map(id => data.people[id]).filter(Boolean);
                head.innerHTML = '<th class="text-center">No</th>' + activeColumns
                    .map(column => `<th class="${column.align === 'right' ? 'text-right' : ''}">${escapeHtml(column.label)}</th>`).join('');
                title.textContent = list.title || 'Daftar Personel';
                scope.textContent = list.scope || '';
                search.value = '';
                render();

                if (window.jQuery && window.jQuery.fn.modal) {
                    window.jQuery(modal).modal('show');
                }
            };

            search.addEventListener('input', render);
            document.addEventListener('dblclick', function (event) {
                const cell = event.target.closest('[data-nett-drill]');
                if (!cell) {
                    return;
                }

                event.preventDefault();
                window.getSelection()?.removeAllRanges();
                open(cell);
            });
            document.addEventListener('keydown', function (event) {
                const cell = event.target.closest?.('[data-nett-drill]');
                if (!cell || event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                open(cell);
            });
        })();
    </script>
@endonce
