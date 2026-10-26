import { h } from './format.js';
import { BRANCH_LABELS } from './territory-data.js';
import { choicePanel, bindChoicePanel } from './choice-panel.js';

/** Inline choices give touch and keyboard users the same camera focus as polygon picking. */
export const bindMapControls = ({ deck, scope, index, studio, segments }) => {
    const units = index.unitsInScope(scope.key);
    const branches = scope.key === 'area6' ? Object.keys(BRANCH_LABELS) : [scope.key];
    if (segments?.available && scope.key !== 'area6') {
        const mapped = index.entityCodes(segments.entities, false, scope.key).size;
        if (mapped < segments.entities.length) {
            deck.querySelectorAll('#sg-simpanan .sg-sheet, #sg-pinjaman .sg-sheet, #sg-kualitas .sg-sheet, #sg-recovery .sg-sheet').forEach((sheet) => {
                const note = document.createElement('p');
                note.className = 'sg-note';
                note.textContent = `${mapped} dari ${segments.entities.length} unit kerja memiliki padanan wilayah pada peta. Seluruh unit kerja tetap tercakup dalam tabel dan total.`;
                sheet.append(note);
            });
        }
    }
    deck.querySelectorAll('.sg-opener [data-territory]').forEach((slot) => {
        const key = slot.dataset.territory;
        if (['marketshare', 'mapping', 'sektoral'].includes(key)) return;
        const copy = slot.closest('.sg-opener').querySelector('.sg-opener__copy');
        if (!copy || !studio.states.has(key)) return;
        const choices = new Map();
        const options = [{ value: '', label: `${scope.label}, tampilan awal` }];
        branches.forEach((branch) => {
            choices.set(`branch:${branch}`, { label: BRANCH_LABELS[branch], codes: index.codesOfBranch(branch) });
            options.push({ value: `branch:${branch}`, label: `${BRANCH_LABELS[branch]}, seluruh wilayah` });
            units.filter((unit) => unit.branch === branch).forEach((unit) => {
                const value = `unit:${unit.code}`;
                choices.set(value, { label: `Unit ${unit.name}`, codes: index.codesOfUnit(unit) });
                options.push({ value, label: `${BRANCH_LABELS[branch]} / ${unit.name}` });
            });
        });
        const field = document.createElement('fieldset');
        field.className = 'sg-map-controls';
        field.innerHTML = `<legend>Fokus peta</legend>${choicePanel({ name: `fokus-${key}`, label: 'Wilayah peta' })}<button type="button" data-map-reset aria-label="Kembali ke tampilan awal peta">Reset</button><p class="kn-sr" role="status" data-map-status></p>`;
        copy.insertBefore(field, copy.querySelector('[data-sg-caption]'));
        const status = field.querySelector('[data-map-status]');
        const focus = (value) => {
            const choice = choices.get(value);
            studio.setOverride(key, choice?.codes || null);
            status.textContent = choice ? `Peta berfokus pada ${choice.label}.` : 'Peta kembali ke tampilan awal.';
            const caption = copy.querySelector('[data-sg-caption]');
            if (caption) {
                caption.innerHTML = choice ? `<strong>${h(choice.label)}</strong>` : '';
                caption.classList.toggle('is-on', Boolean(choice));
            }
        };
        const picker = bindChoicePanel(field.querySelector('[data-choice-field]'), focus);
        picker.setOptions(options, '');
        field.querySelector('[data-map-reset]').addEventListener('click', () => { picker.setValue(''); focus(''); });
        // Polygon interaction must remain inside the selected presentation scope.
        const spec = studio.states.get(key);
        spec.allowedCodes = index.scopeCodes(scope.key);
        slot.addEventListener('territoryfocus', (event) => {
            const codes = event.detail.codes;
            picker.setValue(codes ? [...choices].find(([, choice]) => choice.codes.size === codes.size && [...codes].every((code) => choice.codes.has(code)))?.[0] || '' : '');
        });
    });
};
