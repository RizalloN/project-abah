(() => {
    'use strict';
    const report = window.keragaanPdfReport;
    document.querySelectorAll('.map svg').forEach(map => {
        const selectOffice = target => {
            const code = target?.closest('[data-office-code]')?.getAttribute('data-office-code');
            map.querySelectorAll('[data-office-code]').forEach(item => {
                const selected = !!code && item.getAttribute('data-office-code') === code;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', String(selected));
            });
        };
        map.addEventListener('pointerover', event => selectOffice(event.target));
        map.addEventListener('focusin', event => selectOffice(event.target));
        map.addEventListener('click', event => selectOffice(event.target));
        map.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectOffice(event.target);
            } else if (event.key === 'Escape') selectOffice(null);
        });
    });
    const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const percentage = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = value => value == null ? '-' : number.format(value / 1000000);
    const delta = value => value == null ? '-' : (value > 0 ? '+' : '') + money(value);
    const percent = value => value == null ? '-' : `${percentage.format(value)}%`;
    const date = value => value ? new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: '2-digit' }) : '-';
    const tone = (value, lowerBetter) => value == null || value === 0 ? '' : ((lowerBetter ? value < 0 : value > 0) ? 'good' : 'bad');
    const achievementTone = value => value == null ? '' : value >= 100 ? 'good' : 'bad';
    const colors = { good: '#007a58', bad: '#ba2b2b' };
    const note = `Nominal dalam Rp Juta. - = data atau pembanding belum tersedia. DtD dibandingkan posisi ${date(report.comparison_periods.h1)}; MtD ${date(report.comparison_periods.mtd)}; MtM ${date(report.comparison_periods.mtm)}. Total tetap mencakup angka sumber lengkap, termasuk pembanding dan RKA dari baris tersembunyi. Total SML/NPL mengikuti cakupan non-commercial Dashboard Harian. Delta RKA = posisi dikurangi target; pencapaian SML/NPL = target dibagi posisi.`;
    const headings = [report.area_scope ? 'Kantor Cabang' : 'Unit Kerja', date(report.comparison_periods.ytd), date(report.comparison_periods.mtd), 'Posisi ' + date(report.period), 'Delta DtD', 'Delta MtD', 'Delta MtM', 'RKA ' + new Date(`${report.rka_period.slice(0, 10)}T12:00:00`).toLocaleDateString('id-ID', { month: 'short', year: '2-digit' }), 'Delta RKA', 'Penc. RKA'];
    const kpis = report.sections.filter(section => ['total_simpanan', 'total_os', 'total_sml_abs_non_commercial', 'total_npl_abs_non_commercial'].includes(section.key));
    const metricCells = (row, lowerBetter) => [
        [row.label, ''], [money(row.values.ytd), ''], [money(row.values.mtd), ''], [money(row.values.current), ''],
        ...['dtd', 'mtd', 'mtm'].map(key => [delta(row.deltas[key]), tone(row.deltas[key], lowerBetter)]),
        [money(row.values.rka), ''], [delta(row.deltas.rka), tone(row.deltas.rka, lowerBetter)], [percent(row.achievement), achievementTone(row.achievement)],
    ];
    const hiddenNote = section => section.hidden_current_value != null && section.hidden_current_value !== 0
        ? `Total mencakup Rp ${money(section.hidden_current_value)} juta dari baris yang tidak ditampilkan sesuai cakupan segmen.` : '';
    const element = (tag, text, className = '') => {
        const node = document.createElement(tag);
        node.textContent = text;
        node.className = className;
        return node;
    };
    document.getElementById('dataNote').textContent = note;
    for (const section of kpis) {
        const card = element('section', '', 'kpi');
        card.append(element('h3', section.label), element('strong', `Rp ${money(section.total.values.current)} Jt`));
        for (const [key, label] of Object.entries({ dtd: 'Delta DtD', mtd: 'Delta MtD', mtm: 'Delta MtM', rka: 'Delta RKA' })) {
            const line = element('p', label);
            line.append(element('span', delta(section.total.deltas[key]), tone(section.total.deltas[key], section.lower_better)));
            card.append(line);
        }
        const achievement = element('p', 'Penc. RKA');
        achievement.append(element('span', percent(section.total.achievement), achievementTone(section.total.achievement)));
        card.append(achievement);
        document.getElementById('kpis').append(card);
    }
    for (const section of report.sections) {
        const wrap = element('div', '', 'table-wrap');
        const table = document.createElement('table');
        table.append(element('caption', section.label));
        const thead = document.createElement('thead');
        const heading = document.createElement('tr');
        headings.forEach(text => { const th = element('th', text); th.scope = 'col'; heading.append(th); });
        thead.append(heading);
        table.append(thead);
        const body = document.createElement('tbody');
        [...section.rows, { ...section.total, label: 'Total ' + section.total.label, isTotal: true }].forEach(row => {
            const tr = element('tr', '', row.isTotal ? 'total' : '');
            metricCells(row, section.lower_better).forEach(([text, className]) => tr.append(element('td', text, className)));
            body.append(tr);
        });
        table.append(body);
        wrap.append(table);
        if (hiddenNote(section)) wrap.append(element('p', hiddenNote(section), 'note'));
        document.getElementById('reportTables').append(wrap);
    }

    function definition() {
        const content = [
            { columns: [{ image: report.logos.danantara, fit: [110, 35] }, { image: report.logos.bri, fit: [75, 35], alignment: 'right' }], margin: [0, 0, 0, 14] },
            { text: 'PT Bank Rakyat Indonesia (PERSERO) Tbk', fontSize: 14, bold: true, color: '#004886' },
            { text: `Performance Report ${report.scope_label} - Region 13 Malang`, fontSize: 10, bold: true, margin: [0, 5, 0, 4] },
            { text: `Performance Report data ${date(report.period)}`, color: '#526980', margin: [0, 0, 0, 10] },
            { canvas: [{ type: 'line', x1: 0, x2: 547, y1: 0, y2: 0, lineWidth: 1.5, lineColor: '#00549f' }] },
        ];
        if (report.geography.ready) {
            for (const [index, map] of report.geography.maps.entries()) {
                content.push({ table: { widths: ['*'], body: [[{ text: `Sebaran Unit Kerja ${map.label}`, bold: true, fontSize: 11, color: '#ffffff', fillColor: '#00549f', margin: [8, 7, 8, 7] }]] }, layout: 'noBorders', margin: [0, 12, 0, 8], pageBreak: index ? 'before' : undefined });
                content.push({ svg: map.svg, width: 547 });
            }
            content.push({ text: `Sumber: ${report.geography.source}.`, style: 'note' });
        } else content.push({ text: 'Peta wilayah belum tersedia untuk cakupan ini.' });
        content.push({ text: `Ringkasan KPI ${report.scope_label}`, style: 'heading', pageBreak: 'before' });
        content.push({ columns: kpis.map(section => ({
            width: '*',
            table: { widths: ['*'], dontBreakRows: true, body: [[{ stack: [
                { text: section.label, bold: true, color: '#004886', margin: [0, 0, 0, 7] },
                { text: `Rp ${money(section.total.values.current)} Jt`, fontSize: 11, bold: true, margin: [0, 0, 0, 7] },
                ...Object.entries({ dtd: 'Delta DtD', mtd: 'Delta MtD', mtm: 'Delta MtM', rka: 'Delta RKA' }).map(([key, label]) => ({ columns: [{ text: label, color: '#526980' }, { text: delta(section.total.deltas[key]), alignment: 'right', color: colors[tone(section.total.deltas[key], section.lower_better)] || '#162a43' }], margin: [0, 2, 0, 2] })),
                { columns: [{ text: 'Penc. RKA', bold: true }, { text: percent(section.total.achievement), bold: true, alignment: 'right', color: colors[achievementTone(section.total.achievement)] || '#162a43' }], margin: [0, 4, 0, 0] },
            ] }]] },
            layout: {
                hLineWidth: () => 0.6, vLineWidth: index => index === 0 ? 2.5 : 0.6,
                hLineColor: () => '#dce5f0', vLineColor: index => index === 0 ? '#00549f' : '#dce5f0',
                paddingLeft: () => 7, paddingRight: () => 7, paddingTop: () => 8, paddingBottom: () => 8,
            },
        })), columnGap: 8, margin: [0, 0, 0, 10] });
        content.push({ text: note, style: 'note', margin: [0, 0, 0, 12] });
        for (const section of report.sections) {
            const body = [
                [{ text: `${section.label}                         Nominal dalam Rp Juta`, colSpan: 10, bold: true, fontSize: 9, fillColor: '#edf4fd', color: '#004886', margin: [0, 4, 0, 4] }, ...Array.from({ length: 9 }, () => ({}))],
                headings.map(text => ({ text, fillColor: '#00549f', color: '#ffffff', bold: true, fontSize: 6.4 })),
            ];
            [...section.rows, { ...section.total, label: 'Total ' + section.total.label, isTotal: true }].forEach((row, index) => {
                body.push(metricCells(row, section.lower_better).map(([text, className], column) => ({ text, alignment: column ? 'right' : 'left', bold: row.isTotal || column === 3, color: colors[className] || '#162a43', fillColor: row.isTotal ? '#eaf3fe' : index % 2 ? '#f7faff' : '#ffffff' })));
            });
            content.push({
                table: { headerRows: 2, dontBreakRows: true, widths: [91, ...Array(9).fill('*')], body },
                layout: { hLineWidth: () => 0.3, vLineWidth: () => 0, hLineColor: () => '#dce5f0', paddingLeft: () => 3, paddingRight: () => 3, paddingTop: () => 3.5, paddingBottom: () => 3.5 },
                unbreakable: section.rows.length <= 5 || section.label === 'Recovery DH', margin: [0, 0, 0, 12], fontSize: 7.1,
            });
            if (hiddenNote(section)) content.push({ text: hiddenNote(section), style: 'note', margin: [0, -6, 0, 12] });
        }
        return {
            pageSize: 'A4', pageMargins: [24, 24, 24, 32],
            info: { title: `Performance Report ${report.scope_label} ${report.period}`, author: 'PT Bank Rakyat Indonesia (PERSERO) Tbk' },
            defaultStyle: { font: 'Roboto', fontSize: 8, color: '#162a43' },
            styles: { heading: { fontSize: 10, bold: true, color: '#004886', margin: [0, 14, 0, 9] }, note: { fontSize: 7, color: '#526980', lineHeight: 1.3 } },
            footer: (page, pages) => ({ text: `${report.scope_label} | ${date(report.period)}                                      ${page} / ${pages}`, alignment: 'center', fontSize: 7, color: '#526980', margin: [24, 10, 24, 0] }),
            content,
        };
    }
    const download = document.getElementById('downloadPdf');
    download.addEventListener('click', async () => {
        const status = document.getElementById('status');
        download.disabled = true;
        status.textContent = 'Menyiapkan PDF…';
        try {
            if (!window.pdfMake) throw new Error('Komponen PDF belum termuat. Muat ulang halaman.');
            const filename = `Performance-Report-${report.scope_label.replace(/\s+/g, '-')}-${report.period}.pdf`;
            const blob = await new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('Pembuatan PDF melewati batas waktu. Muat ulang halaman dan coba lagi.')), 60000);
                try {
                    window.pdfMake.createPdf(definition()).getBlob(result => { clearTimeout(timeout); resolve(result); });
                } catch (error) { clearTimeout(timeout); reject(error); }
            });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            status.textContent = 'PDF siap. Unduhan telah dimulai.';
        } catch (error) {
            status.textContent = 'PDF gagal dibuat. ' + error.message;
        } finally { download.disabled = false; }
    });
})();
