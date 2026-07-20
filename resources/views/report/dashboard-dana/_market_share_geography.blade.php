@php
    $geoPayload = $marketShareGeography ?? ['ready' => false];
    $geoCoverage = $geoPayload['coverage'] ?? [];
@endphp

<section class="market-geo-app" data-market-geo-app>
    <header class="market-geo-header">
        <div>
            <span class="market-geo-eyebrow">Sheet {{ $geoPayload['sheet'] ?? 'REKAP' }}</span>
            <h2>{{ $geoPayload['title'] ?? 'Peta Potensi & Penetrasi Area 6' }}</h2>
            <p>{{ $geoPayload['subtitle'] ?? 'Visualisasi wilayah layanan Unit Kerja berbasis polygon kecamatan.' }}</p>
        </div>
        <div class="market-geo-freshness">
            <span class="market-geo-status-dot" aria-hidden="true"></span>
            <span>Workbook {{ $geoPayload['updated_at'] ?? '-' }}</span>
        </div>
    </header>

    <div class="market-geo-toolbar">
        <label class="market-geo-field" for="marketGeoBranch">
            <span class="market-geo-field-label">Kanca</span>
            <select id="marketGeoBranch" name="cabang" data-market-geo-branch>
                <option value="all">Seluruh Area 6</option>
                @foreach(($geoPayload['branches'] ?? []) as $branch)
                    <option value="{{ $branch['key'] ?? '' }}">{{ $branch['label'] ?? '-' }}</option>
                @endforeach
            </select>
        </label>

        <label class="market-geo-field market-geo-field--unit" for="marketGeoUnit">
            <span class="market-geo-field-label">Unit Kerja</span>
            <select id="marketGeoUnit" data-market-geo-unit>
                <option value="all">Semua Unit Kerja</option>
            </select>
        </label>

        <button type="button" class="market-geo-reset" data-market-geo-reset title="Reset filter" aria-label="Reset filter">
            <i class="fas fa-undo-alt" aria-hidden="true"></i>
        </button>
    </div>

    <div class="market-geo-stats" aria-live="polite">
        <div class="market-geo-stat">
            <span>Potensi Nasabah</span>
            <strong data-market-geo-stat="potential">-</strong>
        </div>
        <div class="market-geo-stat">
            <span>Existing Nasabah</span>
            <strong data-market-geo-stat="existing">-</strong>
        </div>
        <div class="market-geo-stat">
            <span>Penetrasi</span>
            <strong data-market-geo-stat="penetration">-</strong>
        </div>
        <div class="market-geo-stat">
            <span>Cakupan</span>
            <strong data-market-geo-stat="coverage">{{ $geoCoverage['mapped_district_count'] ?? 0 }} kecamatan</strong>
        </div>
    </div>

    <div class="market-geo-workspace">
        <div class="market-geo-map-shell">
            <div id="marketShareGeographyMap" class="market-geo-map" role="application" aria-label="Peta market share Area 6"></div>
            <div class="market-geo-map-state" data-market-geo-state>Memuat polygon wilayah...</div>
            <div class="market-geo-legend" data-market-geo-legend>
                <div class="market-geo-legend-title">Penetrasi Nasabah</div>
                <div class="market-geo-legend-scale" aria-hidden="true"></div>
                <div class="market-geo-legend-labels"><span>Rendah</span><span>Tinggi</span></div>
            </div>
        </div>

        <aside class="market-geo-side">
            <div class="market-geo-selection" data-market-geo-selection>
                <span class="market-geo-side-label">Wilayah aktif</span>
                <strong>Seluruh Area 6</strong>
                <p>{{ $geoCoverage['mapped_unit_count'] ?? 0 }} Unit Kerja pada {{ $geoCoverage['mapped_district_count'] ?? 0 }} kecamatan</p>
            </div>

            <div class="market-geo-ranking-head">
                <div>
                    <span class="market-geo-side-label">Peringkat Unit</span>
                    <strong data-market-geo-ranking-title>Penetrasi Nasabah</strong>
                </div>
                <span data-market-geo-ranking-count>-</span>
            </div>
            <div class="market-geo-ranking" data-market-geo-ranking></div>

            <div class="market-geo-source">
                <i class="fas fa-vector-square" aria-hidden="true"></i>
                <div>
                    <span>Batas administratif</span>
                    <a href="{{ $geoPayload['source']['url'] ?? '#' }}" target="_blank" rel="noopener">
                        {{ $geoPayload['source']['label'] ?? 'Badan Informasi Geospasial' }}
                    </a>
                    <small>Nilai Unit Kerja dengan dua kecamatan dialokasikan merata, lalu penetrasi dihitung ulang.</small>
                </div>
            </div>
        </aside>
    </div>
</section>

<script type="application/json" id="marketShareGeographyPayload">{!! json_encode($geoPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('vendor/leaflet-1.9.4/leaflet.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const app = document.querySelector('[data-market-geo-app]');
        const payloadElement = document.getElementById('marketShareGeographyPayload');
        const mapElement = document.getElementById('marketShareGeographyMap');
        if (!app || !payloadElement || !mapElement || typeof window.L === 'undefined') {
            return;
        }

        let payload = {};
        try {
            payload = JSON.parse(payloadElement.textContent || '{}');
        } catch (_) {
            return;
        }

        const units = Array.isArray(payload.units) ? payload.units : [];
        const branches = Array.isArray(payload.branches) ? payload.branches : [];
        const unitByCode = new Map(units.map(function (unit) { return [String(unit.code), unit]; }));
        const branchByKey = new Map(branches.map(function (branch) { return [String(branch.key), branch]; }));
        const state = {
            branch: 'all',
            unit: 'all',
        };
        const controls = {
            branch: app.querySelector('[data-market-geo-branch]'),
            unit: app.querySelector('[data-market-geo-unit]'),
            reset: app.querySelector('[data-market-geo-reset]'),
            loading: app.querySelector('[data-market-geo-state]'),
            ranking: app.querySelector('[data-market-geo-ranking]'),
            rankingTitle: app.querySelector('[data-market-geo-ranking-title]'),
            rankingCount: app.querySelector('[data-market-geo-ranking-count]'),
            selection: app.querySelector('[data-market-geo-selection]'),
        };
        const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
        const percentFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
        const penetrationScale = ['#f1f5f9', '#e2e8f0', '#cbd5e1', '#94a3b8', '#64748b', '#334155'];
        let geoData = null;
        let geoLayer = null;
        let fullBounds = null;

        const map = window.L.map(mapElement, {
            zoomControl: true,
            attributionControl: true,
            minZoom: 8,
            maxZoom: 13,
            zoomSnap: 0.25,
            preferCanvas: false,
        });
        const vectorRenderer = window.L.svg({ padding: 0.5 });
        map.attributionControl.setPrefix(false);
        map.attributionControl.addAttribution('Polygon: Badan Informasi Geospasial');

        function metricValue(unit, metricKey) {
            return Number(unit?.values?.total?.[metricKey] || 0);
        }

        function formatMetric(value, metricKey) {
            return metricKey === 'penetration'
                ? percentFormat.format(Number(value || 0)) + '%'
                : numberFormat.format(Math.round(Number(value || 0)));
        }

        function activeUnits() {
            return units.filter(function (unit) {
                if (state.branch !== 'all' && String(unit.branch) !== state.branch) {
                    return false;
                }
                return state.unit === 'all' || String(unit.code) === state.unit;
            });
        }

        function activeDistrictCodes() {
            return new Set(activeUnits().flatMap(function (unit) {
                return Array.isArray(unit.district_codes) ? unit.district_codes.map(String) : [];
            }));
        }

        function districtValues(districtCode, filteredUnits) {
            const totals = filteredUnits.reduce(function (result, unit) {
                const districtCodes = Array.isArray(unit.district_codes) ? unit.district_codes.map(String) : [];
                if (!districtCodes.includes(String(districtCode))) {
                    return result;
                }

                const divisor = Math.max(1, districtCodes.length);
                result.potential += metricValue(unit, 'potential') / divisor;
                result.existing += metricValue(unit, 'existing') / divisor;
                return result;
            }, { potential: 0, existing: 0 });

            totals.penetration = totals.potential > 0
                ? (totals.existing / totals.potential) * 100
                : 0;
            return totals;
        }

        function colorForPenetration(value) {
            if (!(value > 0)) {
                return penetrationScale[0];
            }
            const ratio = Math.min(1, Number(value) / 100);
            const index = Math.min(penetrationScale.length - 1, Math.floor(ratio * penetrationScale.length));
            return penetrationScale[index];
        }

        function featureElement(feature, districtUnits, values) {
            const wrapper = document.createElement('div');
            const title = document.createElement('strong');
            const regency = document.createElement('span');
            const metrics = document.createElement('div');
            title.textContent = String(feature.properties?.WADMKC || 'Kecamatan');
            regency.textContent = String(feature.properties?.WADMKK || '-');
            metrics.className = 'market-geo-tooltip__metrics';
            [
                ['Potensi', values.potential, 'potential'],
                ['Existing', values.existing, 'existing'],
                ['Penetrasi', values.penetration, 'penetration'],
            ].forEach(function (item) {
                const metric = document.createElement('span');
                metric.textContent = item[0] + ': ' + formatMetric(item[1], item[2]);
                metrics.appendChild(metric);
            });
            wrapper.className = 'market-geo-tooltip';
            wrapper.appendChild(title);
            wrapper.appendChild(regency);
            wrapper.appendChild(metrics);
            if (districtUnits.length) {
                const unitText = document.createElement('span');
                unitText.textContent = districtUnits.map(function (unit) { return unit.name; }).join(', ');
                wrapper.appendChild(unitText);
            }
            return wrapper;
        }

        function visibleFeatureCollection() {
            const codes = activeDistrictCodes();
            return {
                type: 'FeatureCollection',
                features: (geoData?.features || []).filter(function (feature) {
                    return codes.has(String(feature.properties?.KDCPUM || ''));
                }),
            };
        }

        function renderMap() {
            if (!geoData) {
                return;
            }
            const filteredUnits = activeUnits();
            const visibleData = visibleFeatureCollection();
            if (geoLayer) {
                map.removeLayer(geoLayer);
            }

            geoLayer = window.L.geoJSON(visibleData, {
                renderer: vectorRenderer,
                style: function (feature) {
                    const values = districtValues(String(feature.properties?.KDCPUM || ''), filteredUnits);
                    return {
                        color: '#ffffff',
                        weight: 1.25,
                        opacity: 1,
                        fillColor: colorForPenetration(values.penetration),
                        fillOpacity: values.penetration > 0 ? 0.92 : 0.58,
                    };
                },
                onEachFeature: function (feature, layer) {
                    const districtCode = String(feature.properties?.KDCPUM || '');
                    const districtUnits = filteredUnits.filter(function (unit) {
                        return (unit.district_codes || []).map(String).includes(districtCode);
                    });
                    const values = districtValues(districtCode, filteredUnits);
                    layer.bindTooltip(featureElement(feature, districtUnits, values), {
                        sticky: true,
                        direction: 'top',
                        opacity: 0.98,
                    });
                    layer.on({
                        mouseover: function () {
                            layer.setStyle({ weight: 2.5, color: '#0f2744', fillOpacity: 1 });
                            layer.bringToFront();
                        },
                        mouseout: function () {
                            geoLayer.resetStyle(layer);
                        },
                        click: function () {
                            renderSelection(feature, districtUnits, values);
                            map.fitBounds(layer.getBounds(), { padding: [28, 28], maxZoom: 11.5 });
                        },
                    });
                },
            }).addTo(map);

            const bounds = geoLayer.getBounds();
            const renderedLayerCount = geoLayer.getLayers().length;
            if (renderedLayerCount > 0 && bounds.isValid()) {
                map.invalidateSize({ pan: false });
                map.fitBounds(bounds, { padding: [18, 18], maxZoom: state.unit === 'all' ? 10.5 : 11.5 });
                if (!fullBounds) {
                    fullBounds = bounds.pad(0.12);
                    map.setMaxBounds(fullBounds);
                }
                if (controls.loading) {
                    controls.loading.classList.add('d-none');
                    controls.loading.classList.remove('is-error');
                }
            } else if (controls.loading) {
                controls.loading.textContent = 'Polygon wilayah tidak ditemukan untuk filter ini.';
                controls.loading.classList.remove('d-none');
                controls.loading.classList.add('is-error');
            }
        }

        function renderSelection(feature, districtUnits, values) {
            if (!controls.selection) {
                return;
            }
            controls.selection.innerHTML = '';
            const label = document.createElement('span');
            const title = document.createElement('strong');
            const copy = document.createElement('p');
            label.className = 'market-geo-side-label';
            label.textContent = String(feature.properties?.WADMKK || 'Wilayah');
            title.textContent = String(feature.properties?.WADMKC || 'Kecamatan');
            copy.textContent = 'Potensi ' + formatMetric(values.potential, 'potential') +
                ' | Existing ' + formatMetric(values.existing, 'existing') +
                ' | Penetrasi ' + formatMetric(values.penetration, 'penetration') +
                ' | ' + districtUnits.length + ' Unit Kerja terkait';
            controls.selection.append(label, title, copy);
        }

        function renderStats() {
            const filteredUnits = activeUnits();
            const potential = filteredUnits.reduce(function (sum, unit) { return sum + metricValue(unit, 'potential'); }, 0);
            const existing = filteredUnits.reduce(function (sum, unit) { return sum + metricValue(unit, 'existing'); }, 0);
            const penetration = potential > 0 ? (existing / potential) * 100 : 0;
            const districts = activeDistrictCodes().size;
            app.querySelector('[data-market-geo-stat="potential"]').textContent = formatMetric(potential, 'potential');
            app.querySelector('[data-market-geo-stat="existing"]').textContent = formatMetric(existing, 'existing');
            app.querySelector('[data-market-geo-stat="penetration"]').textContent = formatMetric(penetration, 'penetration');
            app.querySelector('[data-market-geo-stat="coverage"]').textContent = districts + ' kecamatan';
        }

        function renderRanking() {
            if (!controls.ranking) {
                return;
            }
            const ranked = activeUnits()
                .map(function (unit) {
                    const potential = metricValue(unit, 'potential');
                    const existing = metricValue(unit, 'existing');
                    return {
                        unit: unit,
                        value: potential > 0 ? (existing / potential) * 100 : 0,
                    };
                })
                .sort(function (left, right) { return right.value - left.value; })
                .slice(0, 12);
            controls.ranking.innerHTML = '';
            ranked.forEach(function (entry, index) {
                const button = document.createElement('button');
                const order = document.createElement('span');
                const name = document.createElement('span');
                const value = document.createElement('strong');
                button.type = 'button';
                button.className = 'market-geo-ranking-row';
                button.title = 'Fokus ke ' + entry.unit.name;
                order.textContent = String(index + 1);
                order.className = 'market-geo-ranking-order';
                name.textContent = entry.unit.name;
                name.className = 'market-geo-ranking-name';
                value.textContent = formatMetric(entry.value, 'penetration');
                button.append(order, name, value);
                button.addEventListener('click', function () {
                    state.branch = String(entry.unit.branch);
                    state.unit = String(entry.unit.code);
                    controls.branch.value = state.branch;
                    populateUnits();
                    controls.unit.value = state.unit;
                    renderAll();
                });
                controls.ranking.appendChild(button);
            });
            if (controls.rankingTitle) {
                controls.rankingTitle.textContent = 'Penetrasi Nasabah';
            }
            if (controls.rankingCount) {
                controls.rankingCount.textContent = ranked.length + ' unit';
            }
        }

        function renderSelectionSummary() {
            if (!controls.selection) {
                return;
            }
            const filtered = activeUnits();
            const branchLabel = state.branch === 'all'
                ? 'Seluruh Area 6'
                : (branchByKey.get(state.branch)?.label || state.branch);
            const unit = state.unit === 'all' ? null : unitByCode.get(state.unit);
            controls.selection.innerHTML = '';
            const label = document.createElement('span');
            const title = document.createElement('strong');
            const copy = document.createElement('p');
            label.className = 'market-geo-side-label';
            label.textContent = unit ? branchLabel : 'Wilayah aktif';
            title.textContent = unit ? unit.label : branchLabel;
            copy.textContent = filtered.length + ' Unit Kerja pada ' + activeDistrictCodes().size + ' kecamatan';
            controls.selection.append(label, title, copy);
        }

        function populateUnits() {
            const previous = state.unit;
            const available = units.filter(function (unit) {
                return state.branch === 'all' || String(unit.branch) === state.branch;
            });
            controls.unit.innerHTML = '';
            const allOption = document.createElement('option');
            allOption.value = 'all';
            allOption.textContent = 'Semua Unit Kerja';
            controls.unit.appendChild(allOption);
            available.forEach(function (unit) {
                const option = document.createElement('option');
                option.value = String(unit.code);
                option.textContent = unit.label;
                controls.unit.appendChild(option);
            });
            state.unit = available.some(function (unit) { return String(unit.code) === previous; }) ? previous : 'all';
            controls.unit.value = state.unit;
        }

        function renderAll() {
            renderStats();
            renderRanking();
            renderSelectionSummary();
            renderMap();
        }

        controls.branch.addEventListener('change', function () {
            state.branch = controls.branch.value || 'all';
            state.unit = 'all';
            populateUnits();
            renderAll();
        });
        controls.unit.addEventListener('change', function () {
            state.unit = controls.unit.value || 'all';
            const unit = state.unit === 'all' ? null : unitByCode.get(state.unit);
            if (unit && state.branch === 'all') {
                state.branch = String(unit.branch);
                controls.branch.value = state.branch;
                populateUnits();
                controls.unit.value = state.unit;
            }
            renderAll();
        });
        controls.reset.addEventListener('click', function () {
            state.branch = 'all';
            state.unit = 'all';
            controls.branch.value = state.branch;
            populateUnits();
            renderAll();
        });

        document.querySelectorAll('[data-market-workbook-mode-trigger]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.getAttribute('data-market-workbook-mode-trigger') === 'geography') {
                    setTimeout(function () {
                        map.invalidateSize();
                        if (geoLayer?.getBounds().isValid()) {
                            map.fitBounds(geoLayer.getBounds(), { padding: [18, 18], maxZoom: 10.5 });
                        }
                    }, 80);
                }
            });
        });

        function loadGeoData() {
            if (payload.geojson?.type === 'FeatureCollection' && Array.isArray(payload.geojson.features)) {
                return Promise.resolve(payload.geojson);
            }

            return fetch(String(payload.geojson_url || ''), {
                headers: { 'Accept': 'application/geo+json, application/json' },
                cache: 'no-store',
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            });
        }

        populateUnits();
        loadGeoData()
            .then(function (data) {
                if (data?.type !== 'FeatureCollection' || !Array.isArray(data.features)) {
                    throw new Error('Format GeoJSON tidak valid.');
                }
                geoData = data;
                renderAll();
            })
            .catch(function (error) {
                controls.loading.textContent = 'Polygon wilayah belum bisa dimuat: ' + error.message;
                controls.loading.classList.add('is-error');
            });
    });
</script>

<style>
    .market-geo-app {
        color: #172033;
        background: #ffffff;
        border: 1px solid #dbe5ef;
        border-radius: 8px;
        overflow: hidden;
    }

    .market-geo-header,
    .market-geo-toolbar,
    .market-geo-stats {
        border-bottom: 1px solid #e2e8f0;
    }

    .market-geo-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
    }

    .market-geo-eyebrow,
    .market-geo-field-label,
    .market-geo-side-label {
        display: block;
        color: #64748b;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .market-geo-header h2 {
        margin: .22rem 0 .2rem;
        color: #172033;
        font-size: 1.08rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .market-geo-header p {
        margin: 0;
        color: #64748b;
        font-size: .8rem;
    }

    .market-geo-freshness {
        display: inline-flex;
        align-items: center;
        gap: .42rem;
        color: #475569;
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .market-geo-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #0f766e;
    }

    .market-geo-toolbar {
        display: grid;
        grid-template-columns: repeat(2, minmax(200px, 1fr)) 38px;
        align-items: end;
        gap: .7rem;
        padding: .8rem 1rem;
        background: #f8fafc;
    }

    .market-geo-field {
        min-width: 0;
        margin: 0;
    }

    .market-geo-field-label {
        margin-bottom: .35rem;
    }

    .market-geo-field select {
        width: 100%;
        height: 36px;
        padding: 0 .65rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #ffffff;
        color: #1e293b;
        font-size: .76rem;
        font-weight: 700;
        box-shadow: none;
    }

    .market-geo-reset {
        width: 38px;
        height: 36px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #ffffff;
        color: #475569;
    }

    .market-geo-reset:hover,
    .market-geo-reset:focus {
        border-color: #0b5cab;
        color: #0b5cab;
    }

    .market-geo-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        background: #ffffff;
    }

    .market-geo-stat {
        min-width: 0;
        padding: .75rem 1rem;
        border-right: 1px solid #e2e8f0;
    }

    .market-geo-stat:last-child {
        border-right: 0;
    }

    .market-geo-stat span {
        display: block;
        color: #64748b;
        font-size: .7rem;
        font-weight: 700;
    }

    .market-geo-stat strong {
        display: block;
        margin-top: .18rem;
        color: #172033;
        font-size: 1rem;
        font-weight: 800;
    }

    .market-geo-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        min-height: clamp(480px, 68dvh, 720px);
    }

    .market-geo-map-shell {
        position: relative;
        min-width: 0;
        background: #eaf1f7;
    }

    .market-geo-map {
        width: 100%;
        height: 100%;
        min-height: clamp(480px, 68dvh, 720px);
        background: #eaf1f7;
    }

    .market-geo-map .leaflet-overlay-pane svg,
    .market-geo-map .leaflet-overlay-pane canvas {
        max-width: none !important;
        max-height: none !important;
    }

    .market-geo-map .leaflet-overlay-pane svg {
        overflow: visible;
    }

    .market-geo-map-state {
        position: absolute;
        inset: 0;
        z-index: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(248, 250, 252, .86);
        color: #475569;
        font-size: .8rem;
        font-weight: 800;
    }

    .market-geo-map-state.is-error {
        color: #991b1b;
    }

    .market-geo-legend {
        position: absolute;
        right: 12px;
        bottom: 18px;
        z-index: 500;
        width: 154px;
        padding: .55rem .65rem;
        border: 1px solid rgba(203, 213, 225, .92);
        border-radius: 6px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 10px 24px -20px rgba(15, 23, 42, .5);
    }

    .market-geo-legend-title {
        margin-bottom: .35rem;
        color: #334155;
        font-size: .68rem;
        font-weight: 800;
    }

    .market-geo-legend-scale {
        height: 8px;
        border-radius: 3px;
        background: linear-gradient(90deg, #f1f5f9, #cbd5e1, #94a3b8, #334155);
    }

    .market-geo-legend-labels {
        display: flex;
        justify-content: space-between;
        margin-top: .24rem;
        color: #64748b;
        font-size: .62rem;
        font-weight: 700;
    }

    .market-geo-side {
        min-width: 0;
        border-left: 1px solid #e2e8f0;
        background: #ffffff;
    }

    .market-geo-selection,
    .market-geo-ranking-head,
    .market-geo-source {
        padding: .85rem .9rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .market-geo-selection strong,
    .market-geo-ranking-head strong {
        display: block;
        margin-top: .25rem;
        color: #172033;
        font-size: .82rem;
        font-weight: 800;
    }

    .market-geo-selection p {
        margin: .24rem 0 0;
        color: #64748b;
        font-size: .7rem;
        line-height: 1.45;
    }

    .market-geo-ranking-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: .5rem;
    }

    .market-geo-ranking-head > span {
        color: #64748b;
        font-size: .68rem;
        font-weight: 700;
    }

    .market-geo-ranking {
        max-height: 405px;
        overflow-y: auto;
    }

    .market-geo-ranking-row {
        display: grid;
        grid-template-columns: 24px minmax(0, 1fr) auto;
        align-items: center;
        gap: .5rem;
        width: 100%;
        min-height: 38px;
        padding: .42rem .75rem;
        border: 0;
        border-bottom: 1px solid #edf2f7;
        background: #ffffff;
        color: #334155;
        text-align: left;
    }

    .market-geo-ranking-row:hover,
    .market-geo-ranking-row:focus {
        background: #eff6ff;
    }

    .market-geo-ranking-order {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 4px;
        background: #e8eef5;
        color: #475569;
        font-size: .64rem;
        font-weight: 800;
    }

    .market-geo-ranking-name {
        overflow: hidden;
        font-size: .69rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .market-geo-ranking-row strong {
        color: #0b5cab;
        font-size: .68rem;
        font-weight: 800;
    }

    .market-geo-source {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        border-bottom: 0;
        color: #64748b;
        font-size: .67rem;
    }

    .market-geo-source span,
    .market-geo-source a {
        display: block;
    }

    .market-geo-source a {
        margin-top: .12rem;
        color: #0b5cab;
        font-weight: 700;
    }

    .market-geo-source small {
        display: block;
        margin-top: .35rem;
        color: #64748b;
        line-height: 1.45;
    }

    .market-geo-tooltip {
        display: grid;
        gap: .12rem;
        min-width: 160px;
        max-width: 240px;
        color: #334155;
        font-size: .68rem;
    }

    .market-geo-tooltip strong {
        color: #172033;
        font-size: .78rem;
    }

    .market-geo-tooltip__metrics {
        display: grid;
        gap: .16rem;
        margin-top: .22rem;
        padding-top: .28rem;
        border-top: 1px solid #e2e8f0;
        font-weight: 700;
    }

    .market-geo-map .leaflet-control-zoom a {
        color: #0b5cab;
    }

    .market-geo-map .leaflet-control-attribution {
        color: #64748b;
        font-size: 9px;
    }

    @media (max-width: 1199.98px) {
        .market-geo-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .market-geo-field--unit {
            grid-column: span 2;
        }

        .market-geo-reset {
            position: absolute;
            right: 1.05rem;
            margin-top: .98rem;
        }
    }

    @media (max-width: 767.98px) {
        .market-geo-header {
            flex-direction: column;
        }

        .market-geo-freshness {
            white-space: normal;
        }

        .market-geo-toolbar {
            grid-template-columns: 1fr;
            padding-right: 1rem;
        }

        .market-geo-field--unit {
            grid-column: auto;
        }

        .market-geo-reset {
            position: static;
            width: 100%;
        }

        .market-geo-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .market-geo-stat:nth-child(2) {
            border-right: 0;
        }

        .market-geo-stat:nth-child(-n+2) {
            border-bottom: 1px solid #e2e8f0;
        }

        .market-geo-workspace {
            grid-template-columns: 1fr;
        }

        .market-geo-map,
        .market-geo-workspace {
            min-height: clamp(380px, 62dvh, 520px);
        }

        .market-geo-side {
            border-top: 1px solid #e2e8f0;
            border-left: 0;
        }
    }

    @media (max-width: 359.98px) {
        .market-geo-stats {
            grid-template-columns: 1fr;
        }

        .market-geo-stat {
            border-right: 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .market-geo-stat:last-child {
            border-bottom: 0;
        }

        .market-geo-map,
        .market-geo-workspace {
            min-height: 360px;
        }

        .market-geo-legend {
            right: 8px;
            bottom: 8px;
            width: min(154px, calc(100% - 16px));
        }
    }
</style>
