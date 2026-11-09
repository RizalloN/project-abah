---
name: dembrandt
description: "Extract design tokens, color palettes, typography scales, spacing, shadows, border radii, and brand assets from any live website URL into DTCG, DESIGN.md, Tailwind v4 @theme, or shadcn/ui. Use when cloning, inspecting, or matching a live website's design system, generating an AI-ready DESIGN.md file, or auditing UI/UX consistency with Dembrandt (https://github.com/dembrandt/dembrandt)."
argument-hint: "<url> [options]"
license: MIT
metadata:
  author: dembrandt
  version: "0.39.2"
---

# Dembrandt Design System Extraction Skill

Dembrandt is an automated design system extractor and audit engine. It visits any live URL using headless Playwright, analyzes the computed DOM styles, CSS variables, and layout rules, and exports structured, machine-readable design tokens for AI agents and developers.

- **GitHub Repository**: [https://github.com/dembrandt/dembrandt](https://github.com/dembrandt/dembrandt)
- **Primary Use Cases**:
  1. Reverse-engineering a reference website's visual design.
  2. Generating an AI-optimized `DESIGN.md` context file for UI scaffolding.
  3. Generating Tailwind v4 `@theme` or `shadcn/ui` theme files directly from live DOM values.
  4. Auditing WCAG color contrast, design drift, and off-token inconsistencies.

---

## 1. Quick Execution (CLI & npx)

Dembrandt runs directly via `npx dembrandt` or global install (`npm i -g dembrandt`).

```bash
# Basic extraction to terminal summary & JSON
npx dembrandt https://example.com

# Generate DESIGN.md for AI coding agent context
npx dembrandt https://example.com --design-md

# Generate Tailwind v4 theme CSS
npx dembrandt https://example.com --tailwind ./output/theme.css

# Generate shadcn/ui theme configuration
npx dembrandt https://example.com --shadcn ./output/shadcn.css

# Export standard W3C Design Tokens (DTCG)
npx dembrandt https://example.com --dtcg

# Analyze WCAG contrast compliance
npx dembrandt https://example.com --wcag
```

---

## 2. Key Extraction Flags & Options

### A. AI Context & Theme Export
| Flag | Description | Output |
| :--- | :--- | :--- |
| `--design-md` | Generates a clean `DESIGN.md` markdown summary for AI agents | `DESIGN.md` |
| `--tailwind [path]` | Writes a Tailwind CSS v4 `@theme` block with observed tokens | `theme.css` |
| `--shadcn [path]` | Exports a `shadcn/ui` compatible CSS variable root/dark block | `shadcn.css` |
| `--dtcg` | Exports W3C DTCG format for Style Dictionary / Tokens Studio | `tokens.json` |
| `--html [path]` | Self-contained visual HTML report of all tokens and components | `report.html` |
| `--save-output` | Saves full raw extraction JSON to `./output/<domain>/` | `*.json` |

### B. Viewport & Theme Variations
| Flag | Description |
| :--- | :--- |
| `--dark-mode` | Emulates `prefers-color-scheme: dark` or toggles dark classes |
| `--mobile` | Extracts from mobile viewport (390x844 default) |
| `--screen-size <WxH>` | Set custom screen resolution, e.g. `1440x900` |
| `--slow` | 3x longer timeouts for heavy SPAs or client-rendered apps |
| `--crawl [n]` | Crawl up to N linked subpages on the same origin (default: 5) |
| `--sitemap` | Discover and extract pages from `sitemap.xml` |

### C. Authentication & Anti-Detection
| Flag | Description |
| :--- | :--- |
| `--stealth` | Enables navigator spoofing and randomized human mouse movements |
| `--cookie <string>` | Cookie header for authenticated pages (`session=...`) |
| `--header <string>` | Custom HTTP headers (`Authorization: Bearer ...`) |
| `--login` | Launches visible browser for interactive manual login |
| `--save-storage-state <p>` | Saves storage state (cookies + localStorage) for repeated headless runs |

---

## 3. Workflow: Building Interfaces from a Live Reference

When a user provides a live website URL and asks to "recreate", "match", or "build with this styling":

### Step 1: Extract the Design Tokens
Run Dembrandt with `--design-md` and `--tailwind` or `--shadcn`:
```bash
npx dembrandt <TARGET_URL> --design-md --tailwind ./resources/css/reference-theme.css --wcag
```

### Step 2: Read `DESIGN.md` into Agent Context
Inspect the generated `DESIGN.md` to extract:
1. **Primary & Accent Colors**: Base brand color, hover/active states, surface backgrounds.
2. **Typography Hierarchy**: Font families (heading, body, mono), scale (`text-xs` to `text-5xl`), font weights, line heights.
3. **Spacing & Radii**: Padding/margin rhythm (e.g., 4px, 8px, 16px, 24px) and border radius scale (`rounded-lg`, `rounded-2xl`, `rounded-full`).
4. **Shadows & Elevation**: Elevation levels, soft blurs, colored glows.

### Step 3: Scaffold Components
Map the extracted tokens into your target framework:
- In **Laravel Blade + Tailwind / Vanilla CSS**: Use the CSS variables and utility classes defined in the generated theme.
- In **shadcn / Radix**: Paste the `--shadcn` block into `app.css` / `globals.css`.

### Step 4: Audit & Drift Verification
Verify that the rebuilt interface conforms to the reference:
```bash
# Check WCAG accessibility
npx dembrandt <TARGET_URL> --wcag

# Compare extracted tokens against baseline
npx dembrandt <TARGET_URL> --compare ./baseline.json
```

---

## 4. Synergy with REA (Reverse Engineer Anything)

- **Use Dembrandt** for the **presentation layer**: CSS tokens, palette, typography, responsive behavior, component styling, and UI assets.
- **Use REA** for the **application layer**: JS bundles, network endpoints, state transitions, API payloads, and internal business logic.
- Combining both enables full-stack, clean-room reconstruction of complex web and desktop applications.
