/*
 * A-Six theme engine.
 *
 * Light is the authored design. Dark mode keeps hand-tuned tokens for the shared
 * chrome (resources/views/layouts/partials/theme.blade.php) and adapts every
 * page's own <style> blocks, inline styles and Chart.js charts by remapping
 * each colour by its role (background, text, border, shadow) in OKLCH: hue is
 * kept, lightness is remapped so surfaces stay ordered (page < card < raised).
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'asix-theme';
    var root = document.documentElement;
    var SKIP_ATTR = 'data-theme-skip';
    var NAVY_HUE = 258;

    var listeners = [];
    var processedSheets = new WeakMap();
    var collectedSheets = new WeakSet();
    var adaptedNodes = [];
    var inlineOriginals = new Map();
    var tokenSkipList = null;
    var observer = null;
    var pending = new Set();
    var scheduled = false;
    var keyframesStyle = null;

    // ---------- colour maths (sRGB <-> OKLCH) ----------

    function toLinear(c) {
        return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }

    function fromLinear(c) {
        return c <= 0.0031308 ? 12.92 * c : 1.055 * Math.pow(c, 1 / 2.4) - 0.055;
    }

    function rgbToOklch(r, g, b) {
        var lr = toLinear(r / 255), lg = toLinear(g / 255), lb = toLinear(b / 255);
        var l = Math.cbrt(0.4122214708 * lr + 0.5363325363 * lg + 0.0514459929 * lb);
        var m = Math.cbrt(0.2119034982 * lr + 0.6806995451 * lg + 0.1073969566 * lb);
        var s = Math.cbrt(0.0883024619 * lr + 0.2817188376 * lg + 0.6299787005 * lb);
        var L = 0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s;
        var A = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s;
        var B = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s;
        var C = Math.sqrt(A * A + B * B);
        var h = Math.atan2(B, A) * 180 / Math.PI;
        return { L: L, C: C, h: h < 0 ? h + 360 : h };
    }

    function oklchToRgbRaw(L, C, h) {
        var a = C * Math.cos(h * Math.PI / 180), b = C * Math.sin(h * Math.PI / 180);
        var l = Math.pow(L + 0.3963377774 * a + 0.2158037573 * b, 3);
        var m = Math.pow(L - 0.1055613458 * a - 0.0638541728 * b, 3);
        var s = Math.pow(L - 0.0894841775 * a - 1.2914855480 * b, 3);
        return [
            4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
            -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
            -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
        ];
    }

    function inGamut(rgb) {
        return rgb[0] >= -0.0005 && rgb[0] <= 1.0005 && rgb[1] >= -0.0005 && rgb[1] <= 1.0005 && rgb[2] >= -0.0005 && rgb[2] <= 1.0005;
    }

    function oklchToRgb(L, C, h) {
        L = Math.max(0, Math.min(1, L));
        var rgb = oklchToRgbRaw(L, C, h);
        if (!inGamut(rgb)) {
            var lo = 0, hi = C;
            for (var i = 0; i < 18; i++) {
                var mid = (lo + hi) / 2;
                if (inGamut(oklchToRgbRaw(L, mid, h))) { lo = mid; } else { hi = mid; }
            }
            rgb = oklchToRgbRaw(L, lo, h);
        }
        return rgb.map(function (c) {
            return Math.round(255 * fromLinear(Math.max(0, Math.min(1, c))));
        });
    }

    var NAMED = { white: [255, 255, 255], black: [0, 0, 0] };

    function parseColor(token) {
        var t = token.trim().toLowerCase();
        if (NAMED[t]) { return { r: NAMED[t][0], g: NAMED[t][1], b: NAMED[t][2], a: 1 }; }
        if (t.charAt(0) === '#') {
            var hex = t.slice(1);
            if (hex.length === 3 || hex.length === 4) {
                hex = hex.split('').map(function (c) { return c + c; }).join('');
            }
            if (hex.length !== 6 && hex.length !== 8) { return null; }
            return {
                r: parseInt(hex.slice(0, 2), 16),
                g: parseInt(hex.slice(2, 4), 16),
                b: parseInt(hex.slice(4, 6), 16),
                a: hex.length === 8 ? parseInt(hex.slice(6, 8), 16) / 255 : 1,
            };
        }
        var fn = t.match(/^(rgba?|hsla?)\(([^)]*)\)$/);
        if (!fn) { return null; }
        var parts = fn[2].replace(/\s*\/\s*/, ',').split(/[\s,]+/).filter(Boolean);
        if (parts.length < 3) { return null; }
        var alpha = parts.length > 3 ? (parts[3].indexOf('%') > -1 ? parseFloat(parts[3]) / 100 : parseFloat(parts[3])) : 1;
        if (fn[1].charAt(0) === 'r') {
            var ch = parts.slice(0, 3).map(function (p) {
                return p.indexOf('%') > -1 ? parseFloat(p) * 2.55 : parseFloat(p);
            });
            return { r: ch[0], g: ch[1], b: ch[2], a: alpha };
        }
        var hh = parseFloat(parts[0]) / 360, ss = parseFloat(parts[1]) / 100, ll = parseFloat(parts[2]) / 100;
        var q = ll < 0.5 ? ll * (1 + ss) : ll + ss - ll * ss, pp = 2 * ll - q;
        var conv = function (tt) {
            if (tt < 0) { tt += 1; }
            if (tt > 1) { tt -= 1; }
            if (tt < 1 / 6) { return pp + (q - pp) * 6 * tt; }
            if (tt < 1 / 2) { return q; }
            if (tt < 2 / 3) { return pp + (q - pp) * (2 / 3 - tt) * 6; }
            return pp;
        };
        return { r: conv(hh + 1 / 3) * 255, g: conv(hh) * 255, b: conv(hh - 1 / 3) * 255, a: alpha };
    }

    function format(rgb, a) {
        var alpha = Math.round(Math.max(0, Math.min(1, a)) * 1000) / 1000;
        return alpha >= 1
            ? 'rgb(' + rgb[0] + ', ' + rgb[1] + ', ' + rgb[2] + ')'
            : 'rgba(' + rgb[0] + ', ' + rgb[1] + ', ' + rgb[2] + ', ' + alpha + ')';
    }

    // ---------- role based mapping ----------

    function neutralHue(c) {
        // Near-neutral greys take the BRI navy hue so dark surfaces feel related.
        return c.C < 0.012 ? NAVY_HUE : c.h;
    }

    function mapBackground(c) {
        var L = c.L, C = c.C;
        if (L >= 0.8) {
            var targetL;
            if (L >= 0.995) {
                targetL = 0.252; // white card -> surface
            } else if (L >= 0.93) {
                targetL = 0.205 + (0.995 - L) * 0.36; // page washes -> page/surface-soft
            } else {
                targetL = 0.252 + (0.93 - L) * 0.5; // stronger tints -> raised
            }
            var targetC = C < 0.012 ? 0.044 : Math.min(Math.max(C * 1.8, 0.03), 0.075);
            return { L: targetL, C: targetC, h: neutralHue(c) };
        }
        if (L >= 0.55) {
            if (C >= 0.08) {
                // Saturated fills stay recognisable; pale ones darken so light text reads on them.
                return L > 0.68
                    ? { L: 0.42 + (L - 0.68) * 0.5, C: C * 0.9, h: c.h }
                    : { L: L * 0.86, C: C, h: c.h };
            }
            return { L: 0.32 + (0.8 - L) * 0.3, C: Math.max(Math.min(C, 0.05), 0.035), h: neutralHue(c) };
        }
        if (L >= 0.35) {
            return { L: L * 0.92, C: C, h: c.h };
        }
        return c; // dark brand surfaces (navy heroes) already suit a dark theme
    }

    function mapText(c) {
        var L = c.L, C = c.C;
        if (L >= 0.85) { return c; } // white text sits on coloured surfaces
        if (C >= 0.06) {
            return { L: Math.max(L, 0.74), C: Math.min(C, 0.17), h: c.h }; // status/brand text: lift for contrast
        }
        if (L <= 0.6) {
            return { L: 0.94 - L * 0.32, C: Math.min(C, 0.03), h: neutralHue(c) };
        }
        return { L: Math.max(L, 0.72), C: Math.min(C, 0.035), h: neutralHue(c) };
    }

    function mapBorder(c) {
        if (c.L >= 0.72) {
            var C = c.C >= 0.05 ? Math.min(c.C, 0.09) : Math.max(c.C, 0.04);
            return { L: 0.355 + (1 - c.L) * 0.28, C: C, h: neutralHue(c) };
        }
        if (c.L < 0.35 && c.C < 0.06) {
            return { L: 0.43, C: 0.045, h: neutralHue(c) };
        }
        return { L: Math.max(c.L, 0.5), C: c.C, h: c.h };
    }

    function mapAccent(c) {
        if (c.L >= 0.9) { return mapBackground(c); }
        if (c.L <= 0.3 && c.C < 0.06) { return mapText(c); }
        return { L: Math.min(Math.max(c.L, 0.58), 0.86), C: c.C, h: c.h };
    }

    function mapColor(token, role) {
        var p = parseColor(token);
        if (!p) { return token; }
        if (role === 'shadow') {
            var lch = rgbToOklch(p.r, p.g, p.b);
            if (lch.L >= 0.75) { return format([255, 255, 255], p.a * 0.08); } // highlights fade out
            return format([1, 6, 16], Math.min(p.a * 1.55 + 0.04, 0.62));
        }
        var c = rgbToOklch(p.r, p.g, p.b);
        var mapped;
        switch (role) {
            case 'text': mapped = mapText(c); break;
            case 'border': mapped = mapBorder(c); break;
            case 'accent': mapped = mapAccent(c); break;
            case 'keep': return token;
            default: mapped = mapBackground(c);
        }
        return format(oklchToRgb(mapped.L, mapped.C, mapped.h), p.a);
    }

    var COLOR_RE = /#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b|(?:rgba?|hsla?)\([^()]*\)|\b(?:white|black)\b/g;

    function remapValue(value, role) {
        if (!value || value.indexOf('var(') === -1 && !/#|rgb|hsl|white|black/i.test(value)) { return value; }
        // Never touch url(...) payloads (SVG data URIs, images).
        return value.split(/(url\([^)]*\))/i).map(function (segment) {
            return /^url\(/i.test(segment) ? segment : segment.replace(COLOR_RE, function (m) {
                return mapColor(m, role);
            });
        }).join('');
    }

    function roleForProperty(prop) {
        if (prop.indexOf('--') === 0) { return null; }
        if (prop === 'color' || prop === 'caret-color' || prop === '-webkit-text-fill-color' || prop === 'text-decoration-color' || prop === '-webkit-text-stroke-color') { return 'text'; }
        if (prop.indexOf('background') === 0) { return 'bg'; }
        if (prop.indexOf('border') === 0 || prop.indexOf('outline') === 0 || prop === 'column-rule-color' || prop === 'scrollbar-color') { return 'border'; }
        if (prop === 'box-shadow' || prop === 'text-shadow' || prop === 'filter' || prop === '-webkit-box-shadow') { return 'shadow'; }
        if (prop === 'fill' || prop === 'stroke' || prop === 'stop-color' || prop === 'flood-color' || prop === 'lighting-color' || prop === 'accent-color') { return 'accent'; }
        return 'skip';
    }

    function hasColor(value) {
        COLOR_RE.lastIndex = 0;
        var found = COLOR_RE.test(value);
        COLOR_RE.lastIndex = 0;
        return found;
    }

    // ---------- custom properties ----------
    // Variables defined once are resolved at every use site and mapped by the
    // role of that site (a brand blue can be a background and a text colour).
    // Variables redefined per modifier are mapped where they are defined.

    var varVotes = Object.create(null);
    var varDefs = Object.create(null);

    function voteVariables(value, role) {
        if (!role || role === 'skip' || value.indexOf('var(') === -1) { return; }
        var re = /var\(\s*(--[A-Za-z0-9_-]+)/g, m;
        while ((m = re.exec(value))) {
            var v = varVotes[m[1]] || (varVotes[m[1]] = { bg: 0, text: 0, border: 0, shadow: 0, accent: 0 });
            v[role] = (v[role] || 0) + 1;
        }
    }

    function noteVarDef(name, value) {
        var trimmed = value.trim();
        var defs = varDefs[name] || (varDefs[name] = []);
        if (defs.indexOf(trimmed) === -1) { defs.push(trimmed); }
    }

    function replaceVarCalls(value, resolver) {
        var out = '', i = 0;
        while (i < value.length) {
            var start = value.indexOf('var(', i);
            if (start === -1) { out += value.slice(i); break; }
            out += value.slice(i, start);
            var depth = 0, j = start + 3;
            for (; j < value.length; j++) {
                var ch = value.charAt(j);
                if (ch === '(') { depth++; }
                if (ch === ')') { depth--; if (depth === 0) { break; } }
            }
            var inner = value.slice(start + 4, j);
            var comma = inner.indexOf(',');
            var name = (comma === -1 ? inner : inner.slice(0, comma)).trim();
            var fallback = comma === -1 ? null : inner.slice(comma + 1).trim();
            var replacement = resolver(name, fallback);
            out += replacement === null ? value.slice(start, j + 1) : replacement;
            i = j + 1;
        }
        return out;
    }

    function resolveVars(value, depth) {
        if ((depth || 0) > 6 || value.indexOf('var(') === -1) { return value; }
        var skips = tokenSkips();
        return replaceVarCalls(value, function (name) {
            if (skips[name]) { return null; }
            var defs = varDefs[name];
            return defs && defs.length === 1 ? resolveVars(defs[0], (depth || 0) + 1) : null;
        });
    }

    function firstColor(value) {
        var first = (value.match(COLOR_RE) || [])[0];
        var p = first ? parseColor(first) : null;
        return p ? rgbToOklch(p.r, p.g, p.b) : null;
    }

    function roleForVariable(name, value) {
        var n = name.toLowerCase();
        if (/shadow|glow|elevation/.test(n)) { return 'shadow'; }
        var c = firstColor(value);
        var votes = varVotes[name];
        var roles = votes ? ['text', 'bg', 'border', 'accent', 'shadow'].filter(function (k) { return votes[k] > 0; }) : [];
        if (roles.length === 1) { return roles[0]; }
        if (roles.length > 1) {
            if (!c || c.C >= 0.06 || c.L < 0.85) { return 'keep'; } // brand colours serve several roles
            return 'bg';
        }
        if (/(^|-)(ink|text|fg|muted|label|title|heading|copy)(-|$)/.test(n)) { return 'text'; }
        if (/(^|-)(line|border|rule|divider|stroke|outline)(-|$)/.test(n)) { return 'border'; }
        if (/(^|-)(bg|surface|background|panel|card|soft|mist|tint|wash|fill|paper)(-|$)/.test(n)) { return 'bg'; }
        return c && c.L >= 0.88 ? 'bg' : 'keep';
    }

    function tokenSkips() {
        if (tokenSkipList) { return tokenSkipList; }
        tokenSkipList = Object.create(null);
        var el = document.getElementById('asix-theme-tokens');
        var text = el ? el.textContent : '';
        var re = /(--[A-Za-z0-9_-]+)\s*:/g, m;
        while ((m = re.exec(text))) { tokenSkipList[m[1]] = true; }
        return tokenSkipList;
    }

    // ---------- stylesheet adaptation ----------

    var PREFIX = ':root[data-theme="dark"]';

    function splitSelectors(selector) {
        var out = [], depth = 0, current = '';
        for (var i = 0; i < selector.length; i++) {
            var ch = selector.charAt(i);
            if (ch === '(' || ch === '[') { depth++; }
            if (ch === ')' || ch === ']') { depth--; }
            if (ch === ',' && depth === 0) { out.push(current); current = ''; continue; }
            current += ch;
        }
        out.push(current);
        return out.map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function prefixSelector(selector) {
        return splitSelectors(selector).map(function (s) {
            if (/^:root\b/.test(s)) { return PREFIX + s.slice(5); }
            if (/^html\b/i.test(s)) { return 'html[data-theme="dark"]' + s.slice(4); }
            return PREFIX + ' ' + s;
        }).join(', ');
    }

    var SHORTHANDS = ['background', 'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-color', 'outline', 'border-block', 'border-inline'];

    // Shorthands holding var() stay unparsed in CSSOM (their longhands read as
    // empty), so they have to be read explicitly.
    function entriesFor(style) {
        var list = [];
        for (var i = 0; i < style.length; i++) {
            var prop = style[i];
            var value = style.getPropertyValue(prop);
            if (value) { list.push({ prop: prop, value: value, important: !!style.getPropertyPriority(prop) }); }
        }
        SHORTHANDS.forEach(function (sh) {
            var v = style.getPropertyValue(sh);
            if (v && v.indexOf('var(') > -1) { list.push({ prop: sh, value: v, important: !!style.getPropertyPriority(sh) }); }
        });
        return list;
    }

    function collectVotes(rules) {
        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];
            if (rule.cssRules && !rule.style) { collectVotes(rule.cssRules); continue; }
            if (!rule.style) { continue; }
            entriesFor(rule.style).forEach(function (entry) {
                if (entry.prop.indexOf('--') === 0) {
                    noteVarDef(entry.prop, entry.value);
                } else {
                    voteVariables(entry.value, roleForProperty(entry.prop));
                }
            });
        }
    }

    var SHORTHAND_COLOR_PROP = {
        'border': 'border-color',
        'border-top': 'border-top-color',
        'border-right': 'border-right-color',
        'border-bottom': 'border-bottom-color',
        'border-left': 'border-left-color',
        'border-color': 'border-color',
        'outline': 'outline-color',
        'border-block': 'border-block-color',
        'border-inline': 'border-inline-color',
    };

    function splitTopLevel(value, separator) {
        var parts = [], depth = 0, current = '';
        for (var i = 0; i < value.length; i++) {
            var ch = value.charAt(i);
            if (ch === '(') { depth++; }
            if (ch === ')') { depth--; }
            if (ch === separator && depth === 0) { parts.push(current); current = ''; continue; }
            current += ch;
        }
        parts.push(current);
        return parts.map(function (x) { return x.trim(); });
    }

    function extractFunction(layer, re) {
        var m = layer.match(re);
        if (!m) { return null; }
        var start = m.index, depth = 0;
        for (var j = start; j < layer.length; j++) {
            if (layer.charAt(j) === '(') { depth++; }
            if (layer.charAt(j) === ')') { depth--; if (depth === 0) { return layer.slice(start, j + 1); } }
        }
        return null;
    }

    function findColorToken(text) {
        var mix = extractFunction(text, /color-mix\(/i);
        if (mix) { return mix; }
        var m = text.match(COLOR_RE);
        return m ? m[0] : null;
    }

    // Only colour-bearing longhands are ever written, so width, style, size and
    // position keep the cascade the light theme authored.
    function colorOutputs(prop, value) {
        if (SHORTHAND_COLOR_PROP[prop]) {
            var token = findColorToken(value);
            if (!token) { return []; }
            if (prop === 'border-color') { return [['border-color', value]]; }
            return [[SHORTHAND_COLOR_PROP[prop], token]];
        }
        if (prop === 'background') {
            var layers = splitTopLevel(value, ',');
            var images = [], color = null;
            layers.forEach(function (layer, index) {
                var image = extractFunction(layer, /(?:repeating-)?(?:linear|radial|conic)-gradient\(|url\(|image-set\(/i);
                images.push(image || 'none');
                if (index === layers.length - 1) {
                    var rest = image ? layer.replace(image, ' ') : layer;
                    color = findColorToken(rest);
                }
            });
            var out = [];
            if (images.some(function (img) { return img !== 'none' && hasColor(img); })) {
                out.push(['background-image', images.join(', ')]);
            }
            if (color) { out.push(['background-color', color]); }
            return out;
        }
        return [[prop, value]];
    }

    // A text colour taken from a variable that is redefined per scope (brand
    // blues per section) cannot be remapped once; mix it towards white at the
    // use site instead so every scope's value stays readable on dark surfaces.
    function liftScopedTextVar(value) {
        var m = value.trim().match(/^var\(\s*(--[A-Za-z0-9_-]+)\s*(?:,[^)]*)?\)$/);
        if (!m || tokenSkips()[m[1]]) { return value; }
        var defs = varDefs[m[1]];
        if (!defs || defs.length < 2) { return value; }
        var c = firstColor(defs[0]);
        if (!c || c.L >= 0.7 || roleForVariable(m[1], defs[0]) !== 'keep') { return value; }
        return 'color-mix(in oklab, ' + value.trim() + ' 52%, #ffffff)';
    }

    // Hand-tuned tokens keep their dark value, but a token tuned for text (a
    // light blue) can be used as a background somewhere; darken it at that
    // site only, and lift a dark token used as text.
    var tokenLightness = Object.create(null);

    function darkTokenLightness(name) {
        if (!(name in tokenLightness)) {
            var raw = getComputedStyle(document.documentElement).getPropertyValue(name);
            var c = raw ? firstColor(raw) : null;
            tokenLightness[name] = c ? c.L : null;
        }
        return tokenLightness[name];
    }

    function adjustTokenUse(value, role) {
        if ((role !== 'bg' && role !== 'text') || value.indexOf('var(') === -1) { return value; }
        var skips = tokenSkips();
        return replaceVarCalls(value, function (name, fallback) {
            if (!skips[name]) { return null; }
            var L = darkTokenLightness(name);
            if (L === null) { return null; }
            var call = 'var(' + name + (fallback !== null ? ', ' + fallback : '') + ')';
            if (role === 'bg' && L > 0.7) { return 'color-mix(in oklab, ' + call + ' 34%, #142238)'; }
            if (role === 'text' && L < 0.45) { return 'color-mix(in oklab, ' + call + ' 45%, #ffffff)'; }
            return null;
        });
    }

    function adaptEntry(entry) {
        var skips = tokenSkips();
        var role, value = entry.value;
        if (entry.prop.indexOf('--') === 0) {
            if (skips[entry.prop] || !hasColor(value)) { return []; }
            var defs = varDefs[entry.prop];
            if (defs && defs.length === 1 && varVotes[entry.prop]) { return []; } // mapped at use sites
            role = roleForVariable(entry.prop, value);
        } else {
            role = roleForProperty(entry.prop);
            if (!role || role === 'skip') { return []; }
            value = adjustTokenUse(resolveVars(value), role);
            if (!hasColor(value)) {
                if (role === 'text') {
                    var lifted = liftScopedTextVar(value);
                    return lifted === entry.value ? [] : colorOutputs(entry.prop, lifted);
                }
                return value === entry.value ? [] : colorOutputs(entry.prop, value);
            }
        }
        if (!role || role === 'skip' || role === 'keep') { return []; }
        var mapped = remapValue(value, role);
        if (role === 'text' && entry.prop.indexOf('--') !== 0) {
            mapped = liftScopedTextVar(mapped);
        }
        if (mapped === entry.value) { return []; }
        return colorOutputs(entry.prop, mapped);
    }

    function declarationsFor(style) {
        var out = [];
        entriesFor(style).forEach(function (entry) {
            adaptEntry(entry).forEach(function (pair) {
                out.push(pair[0] + ': ' + pair[1] + (entry.important ? ' !important' : ''));
            });
        });
        return out;
    }

    function adaptRules(rules, chunks, keyframes) {
        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];
            var type = rule.constructor && rule.constructor.name;
            if (rule.selectorText !== undefined && rule.style) {
                var decls = declarationsFor(rule.style);
                if (decls.length) {
                    chunks.push(prefixSelector(rule.selectorText) + ' { ' + decls.join('; ') + ' }');
                }
                continue;
            }
            if (type === 'CSSKeyframesRule' || rule.type === 7) {
                var frames = [];
                for (var k = 0; k < rule.cssRules.length; k++) {
                    var frame = rule.cssRules[k];
                    var d = declarationsFor(frame.style);
                    var all = [];
                    for (var q = 0; q < frame.style.length; q++) {
                        var pr = frame.style[q];
                        if (!d.some(function (x) { return x.indexOf(pr + ':') === 0; })) {
                            all.push(pr + ': ' + frame.style.getPropertyValue(pr));
                        }
                    }
                    frames.push(frame.keyText + ' { ' + all.concat(d).join('; ') + ' }');
                }
                keyframes.push('@keyframes ' + rule.name + ' { ' + frames.join(' ') + ' }');
                continue;
            }
            if (rule.cssRules && rule.conditionText !== undefined) {
                var inner = [];
                adaptRules(rule.cssRules, inner, keyframes);
                if (inner.length) {
                    var at = (type === 'CSSSupportsRule' || rule.type === 12) ? '@supports ' : (type === 'CSSContainerRule' ? '@container ' : '@media ');
                    chunks.push(at + rule.conditionText + ' { ' + inner.join('\n') + ' }');
                }
            }
        }
    }

    function adaptStyleElement(el) {
        if (!el.sheet || el.hasAttribute(SKIP_ATTR) || processedSheets.has(el)) { return; }
        var rules;
        try { rules = el.sheet.cssRules; } catch (e) { return; }
        if (!collectedSheets.has(el)) { collectVotes(rules); collectedSheets.add(el); }
        var chunks = [], keyframes = [];
        adaptRules(rules, chunks, keyframes);
        var out = document.createElement('style');
        out.setAttribute(SKIP_ATTR, '');
        out.setAttribute('data-theme-adapted', '');
        out.textContent = chunks.join('\n');
        (el.parentNode || document.head).insertBefore(out, el.nextSibling);
        processedSheets.set(el, out);
        adaptedNodes.push(out);
        if (keyframes.length) {
            if (!keyframesStyle) {
                keyframesStyle = document.createElement('style');
                keyframesStyle.setAttribute(SKIP_ATTR, '');
                keyframesStyle.setAttribute('data-theme-adapted', '');
                document.head.appendChild(keyframesStyle);
                adaptedNodes.push(keyframesStyle);
            }
            keyframesStyle.textContent += keyframes.join('\n') + '\n';
        }
    }

    // ---------- inline styles ----------

    function adaptInline(el) {
        if (el.closest && el.closest('[' + SKIP_ATTR + ']')) { return; }
        var entry = inlineOriginals.get(el);
        if (entry && entry.applied === el.getAttribute('style')) { return; }
        var original = el.getAttribute('style');
        if (!original || (!hasColor(original) && original.indexOf('var(') === -1)) { return; }
        var style = el.style;
        var changed = false;
        entriesFor(style).forEach(function (item) {
            adaptEntry(item).forEach(function (pair) {
                style.setProperty(pair[0], pair[1], item.important ? 'important' : '');
                changed = true;
            });
        });
        if (changed) {
            inlineOriginals.set(el, { original: original, applied: el.getAttribute('style') });
        }
    }

    function adaptTree(node) {
        if (!node || node.nodeType !== 1) { return; }
        if (node.tagName === 'STYLE') { adaptStyleElement(node); return; }
        if (node.hasAttribute('style')) { adaptInline(node); }
        node.querySelectorAll('style').forEach(adaptStyleElement);
        node.querySelectorAll('[style]').forEach(adaptInline);
    }

    function flush() {
        scheduled = false;
        var nodes = Array.from(pending);
        pending.clear();
        if (!isDark()) { return; }
        nodes.forEach(adaptTree);
    }

    function schedule(node) {
        pending.add(node);
        if (!scheduled) {
            scheduled = true;
            requestAnimationFrame(flush);
        }
    }

    function startObserver() {
        if (observer || !window.MutationObserver) { return; }
        observer = new MutationObserver(function (records) {
            records.forEach(function (r) {
                if (r.type === 'attributes') {
                    var entry = inlineOriginals.get(r.target);
                    if (entry && entry.applied === r.target.getAttribute('style')) { return; }
                    if (entry) { inlineOriginals.delete(r.target); }
                    schedule(r.target);
                    return;
                }
                r.addedNodes.forEach(function (n) {
                    if (n.nodeType === 1 && !n.hasAttribute(SKIP_ATTR)) { schedule(n); }
                });
            });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
    }

    // ---------- Chart.js ----------

    var CHART_TEXT_PATHS = [['ticks', 'color'], ['title', 'color'], ['pointLabels', 'color']];
    var CHART_LINE_PATHS = [['grid', 'color'], ['border', 'color'], ['angleLines', 'color'], ['grid', 'borderColor']];

    function readPath(obj, path) {
        for (var i = 0; i < path.length; i++) {
            if (!obj || typeof obj !== 'object') { return undefined; }
            obj = obj[path[i]];
        }
        return obj;
    }

    function writePath(obj, path, value) {
        for (var i = 0; i < path.length - 1; i++) {
            if (!obj || typeof obj !== 'object') { return; }
            obj = obj[path[i]];
        }
        if (obj && typeof obj === 'object') { obj[path[path.length - 1]] = value; }
    }

    function chartColorPaths(raw) {
        var paths = [];
        Object.keys(raw.scales || {}).forEach(function (id) {
            CHART_TEXT_PATHS.forEach(function (p) { paths.push({ path: ['scales', id, p[0], p[1]], role: 'text' }); });
            CHART_LINE_PATHS.forEach(function (p) { paths.push({ path: ['scales', id, p[0], p[1]], role: 'border' }); });
        });
        [['legend', 'labels', 'color', 'text'], ['title', 'color', null, 'text'], ['subtitle', 'color', null, 'text'], ['datalabels', 'color', null, 'text'],
            ['tooltip', 'backgroundColor', null, 'bg'], ['tooltip', 'titleColor', null, 'text'], ['tooltip', 'bodyColor', null, 'text'], ['tooltip', 'borderColor', null, 'border']]
            .forEach(function (d) {
                var path = ['plugins', d[0], d[1]];
                if (d[2]) { path.push(d[2]); }
                paths.push({ path: path, role: d[3] });
            });
        return paths;
    }

    // Chart.js replaces its merged option objects on every update(), so the
    // theme keeps option paths (not object references) and re-resolves them.
    function themeChart(chart, dark) {
        var config = chart && chart.config && chart.config._config;
        if (!config || !config.options) { return; }
        if (!dark) {
            var saved = chart.$asixTheme;
            if (!saved) { return; }
            saved.forEach(function (rec) { writePath(config.options, rec.path, rec.value); });
            chart.$asixTheme = null;
        } else {
            if (chart.$asixTheme) { return; }
            var records = [];
            chartColorPaths(config.options).forEach(function (entry) {
                var value = readPath(config.options, entry.path);
                if (typeof value === 'string' && hasColor(value)) {
                    records.push({ path: entry.path, role: entry.role, value: value });
                }
            });
            records.forEach(function (rec) { writePath(config.options, rec.path, remapValue(rec.value, rec.role)); });
            chart.$asixTheme = records;
        }
        try { chart.update('none'); } catch (e) { /* chart may be detached */ }
    }

    // Charts created after the theme is applied (AJAX sections) theme themselves.
    function ensureChartPlugin() {
        var Chart = window.Chart;
        if (!Chart || typeof Chart.register !== 'function' || Chart.$asixThemePlugin) { return; }
        Chart.$asixThemePlugin = true;
        Chart.register({
            id: 'asixTheme',
            afterInit: function (chart) {
                if (isDark()) {
                    window.setTimeout(function () { themeChart(chart, true); }, 0);
                }
            },
        });
    }

    function themeCharts(dark) {
        var Chart = window.Chart;
        if (!Chart || !Chart.defaults) { return; }
        ensureChartPlugin();
        var defaults = Chart.defaults;
        if (!defaults.$asixLight) {
            defaults.$asixLight = { color: defaults.color, borderColor: defaults.borderColor };
        }
        defaults.color = dark ? '#a4b6ca' : defaults.$asixLight.color;
        defaults.borderColor = dark ? 'rgba(164, 182, 202, 0.16)' : defaults.$asixLight.borderColor;
        var instances = Chart.instances ? Object.values(Chart.instances) : [];
        instances.forEach(function (chart) { themeChart(chart, dark); });
    }

    // ---------- public API ----------

    function isDark() {
        return root.getAttribute('data-theme') === 'dark';
    }

    function storedPreference() {
        try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
    }

    function followsSystem() {
        return root.getAttribute('data-theme-follow-system') === '1';
    }

    function systemPrefersDark() {
        return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    }

    function resolvedTheme() {
        var stored = storedPreference();
        if (stored === 'dark' || stored === 'light') { return stored; }
        return followsSystem() && systemPrefersDark() ? 'dark' : 'light';
    }

    function collectSheet(el) {
        if (!el.sheet || el.hasAttribute(SKIP_ATTR) || collectedSheets.has(el)) { return; }
        try { collectVotes(el.sheet.cssRules); } catch (e) { return; }
        collectedSheets.add(el);
    }

    function applyDark() {
        // Two passes: learn every variable definition first, then adapt.
        document.querySelectorAll('style').forEach(collectSheet);
        document.querySelectorAll('style').forEach(adaptStyleElement);
        document.querySelectorAll('[style]').forEach(adaptInline);
        startObserver();
        themeCharts(true);
    }

    function removeDark() {
        adaptedNodes.forEach(function (n) { if (n.parentNode) { n.parentNode.removeChild(n); } });
        adaptedNodes = [];
        keyframesStyle = null;
        processedSheets = new WeakMap();
        inlineOriginals.forEach(function (entry, el) {
            if (entry.original === null) { el.removeAttribute('style'); } else { el.setAttribute('style', entry.original); }
        });
        inlineOriginals.clear();
        themeCharts(false);
    }

    function set(theme, persist) {
        var next = theme === 'dark' ? 'dark' : 'light';
        var changed = root.getAttribute('data-theme') !== next;
        root.classList.add('theme-switching');
        root.setAttribute('data-theme', next);
        root.style.colorScheme = next;
        if (persist !== false) {
            try { localStorage.setItem(STORAGE_KEY, next); } catch (e) { /* private mode */ }
        }
        if (next === 'dark') { applyDark(); } else { removeDark(); }
        window.setTimeout(function () { root.classList.remove('theme-switching'); }, 60);
        if (changed) {
            listeners.forEach(function (cb) { try { cb(next); } catch (e) { /* listener error */ } });
            document.dispatchEvent(new CustomEvent('asix:themechange', { detail: { theme: next } }));
        }
        syncToggles();
    }

    function syncToggles() {
        var dark = isDark();
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            var label = dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
        });
    }

    window.AsixTheme = {
        get: function () { return isDark() ? 'dark' : 'light'; },
        set: function (theme) { set(theme, true); },
        toggle: function () { set(isDark() ? 'light' : 'dark', true); },
        onChange: function (cb) { if (typeof cb === 'function') { listeners.push(cb); } },
        mapColor: mapColor,
        refreshCharts: function () { themeCharts(isDark()); },
    };

    function init() {
        document.addEventListener('click', function (event) {
            var btn = event.target.closest && event.target.closest('[data-theme-toggle]');
            if (!btn) { return; }
            event.preventDefault();
            window.AsixTheme.toggle();
        });
        if (window.matchMedia) {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            var follow = function () {
                var stored = storedPreference();
                if (followsSystem() && stored !== 'dark' && stored !== 'light') { set(mq.matches ? 'dark' : 'light', false); }
            };
            if (mq.addEventListener) { mq.addEventListener('change', follow); } else if (mq.addListener) { mq.addListener(follow); }
        }
        if (resolvedTheme() === 'dark') {
            applyDark();
        }
        syncToggles();
        root.classList.remove('theme-pending');
        // Charts created after load (lazy sections) pick up the theme too.
        ensureChartPlugin();
        window.addEventListener('load', function () { ensureChartPlugin(); if (isDark()) { themeCharts(true); } });
        document.addEventListener('asix:chart-ready', function () { if (isDark()) { themeCharts(true); } });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
