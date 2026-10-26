/*
 * The grammar every segment shares: a pinned opener (the territory relief beside
 * one statement and its figure) and the sheet of tiles that slides over it.
 * Anchors alternate so consecutive segments never stand on the same side.
 */

import { h } from './format.js';

/**
 * key, group, title, figure { value, label, count }, chips (html), lede, note, aside (html),
 * ground: light | dark, anchor: lead | trail | split, pose (territory camera), span (vh), sheet (html)
 */
export const chapter = ({ key, group, title, figure, chips = '', lede = '', note = '', aside = '', ground = 'light', anchor = 'lead', pose = 'lead', span = 230, sheet = '', territory = true }) => `
    <section class="sg-chapter sg-ground--${ground} sg-chapter--${h(key)}" id="sg-${h(key)}" data-sg-segment="${h(key)}" aria-labelledby="sg-${h(key)}-title">
        <div class="sg-opener sg-opener--${anchor}" data-sg-opener data-territory-track="pin" style="--span:${span}vh">
            <div class="sg-opener__sticky">
                ${territory ? `<div class="sg-opener__stage" data-territory="${h(key)}" data-territory-pose="${h(pose)}" aria-hidden="true"><div class="sg-poster" data-territory-poster></div></div>` : ''}
                <div class="sg-opener__copy">
                    <p class="sg-kicker">${h(group)}</p>
                    <h2 class="sg-opener__title" id="sg-${h(key)}-title" data-sg-lines>${h(title)}</h2>
                    ${figure ? `<p class="sg-figure"><span class="sg-figure__value" ${figure.count === false ? '' : 'data-sg-count'}>${h(figure.value)}</span></p><p class="sg-figure__label">${h(figure.label)}</p>` : ''}
                    ${chips ? `<p class="sg-deltas">${chips}</p>` : ''}
                    ${lede ? `<p class="sg-opener__lede">${h(lede)}</p>` : ''}
                    ${aside}
                    ${note ? `<p class="sg-opener__note">${h(note)}</p>` : ''}
                    <div class="sg-caption" data-sg-caption aria-live="polite"></div>
                </div>
            </div>
        </div>
        <div class="sg-sheet">${sheet}</div>
    </section>`;

/** A titled block inside a sheet. */
export const block = ({ title, lede = '', body, className = '' }) => `
    <div class="sg-block ${h(className)}">
        <header class="sg-block__head" data-sg-rv>
            <h3 class="sg-block__title">${h(title)}</h3>
            ${lede ? `<p class="sg-block__lede">${h(lede)}</p>` : ''}
        </header>
        ${body}
    </div>`;

/** A surface tile (rises from depth, tilts toward the pointer). */
export const tile = ({ title = '', meta = '', body, className = '', wide = false }) => `
    <section class="sg-tile${wide ? ' sg-tile--wide' : ''} ${h(className)}">
        ${title ? `<header class="sg-tile__head"><h3>${h(title)}</h3>${meta ? `<span>${h(meta)}</span>` : ''}</header>` : ''}
        ${body}
    </section>`;

/** Spec row of figures above the tiles. */
export const specs = (items) => `<dl class="sg-specs" data-sg-rv>${items.map((item) => `
    <div class="${item.tone ? `is-${h(item.tone)}` : ''}"><dt>${h(item.label)}</dt><dd data-sg-count>${h(item.value)}</dd>${item.note ? `<small>${h(item.note)}</small>` : ''}</div>`).join('')}</dl>`;

export const empty = (text) => `<p class="sg-empty">${h(text)}</p>`;
