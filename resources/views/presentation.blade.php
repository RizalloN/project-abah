<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kinerja Area 6 - Madiun, Magetan, Ngawi, Ponorogo</title>

  <!-- FontAwesome -->
  <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
  <!-- Google Font: Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Chart.js local asset for reliable offline/local loading -->
  <script src="{{ asset('vendor/chartjs/chart.min.js') }}"></script>

  <style>
    /* CSS Baseline */
    body, html {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      background-color: #f5f5f7;
      color: #1d1d1f;
      font-family: 'Inter', sans-serif;
      overflow: hidden;
      user-select: none;
    }

    /* --- APPLE STYLE PRESENTATION MODE --- */
    .apple-presentation-mode {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: #f5f5f7;
      box-sizing: border-box;
      padding: 5.5rem 3rem 5.5rem 3rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
    }
    .apple-presentation-mode::before, .apple-presentation-mode::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      filter: blur(120px);
      opacity: 0.12;
      z-index: 1;
      pointer-events: none;
    }
    .apple-presentation-mode::before {
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, #3b82f6 0%, transparent 70%);
      top: -200px;
      left: -200px;
      animation: ambient-pulse-1 25s infinite alternate ease-in-out;
    }
    .apple-presentation-mode::after {
      width: 700px;
      height: 700px;
      background: radial-gradient(circle, #8b5cf6 0%, transparent 70%);
      bottom: -250px;
      right: -250px;
      animation: ambient-pulse-2 30s infinite alternate ease-in-out;
    }
    @keyframes ambient-pulse-1 {
      0% { transform: translate(0, 0) scale(1); opacity: 0.08; }
      50% { transform: translate(120px, 80px) scale(1.15); opacity: 0.16; }
      100% { transform: translate(-40px, 120px) scale(0.9); opacity: 0.08; }
    }
    @keyframes ambient-pulse-2 {
      0% { transform: translate(0, 0) scale(1.1); opacity: 0.1; }
      50% { transform: translate(-100px, -80px) scale(0.85); opacity: 0.18; }
      100% { transform: translate(60px, -140px) scale(1.05); opacity: 0.1; }
    }
    .pres-top-bar {
      position: absolute;
      top: 1.5rem;
      left: 3rem;
      right: 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      z-index: 10;
    }
    .pres-bottom-bar {
      position: absolute;
      bottom: 1.5rem;
      left: 3rem;
      right: 3rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      z-index: 10;
    }
    .pres-title-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .pres-logo-img {
      height: 24px;
      filter: none;
    }
    /* Co-branding Multi-logos style */
    .pres-logos-wrapper {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      background: rgba(255, 255, 255, 0.7);
      padding: 0.35rem 0.75rem;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, 0.08);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
      margin-right: 0.5rem;
    }
    .pres-logo-brand {
      height: 20px;
      object-fit: contain;
      display: block;
    }
    .pres-logo-brand.logo-danantara {
      height: 22px;
    }
    .pres-logo-brand.logo-bri {
      height: 14px;
    }
    .pres-logo-brand.logo-asix {
      height: 16px;
    }
    .pres-logo-divider {
      width: 1px;
      height: 12px;
      background: rgba(0, 0, 0, 0.15);
    }

    /* Cover slide logos */
    .pres-cover-logos-container {
      margin-bottom: 1.5rem;
      display: flex;
    }
    .pres-cover-logos {
      display: inline-flex;
      align-items: center;
      gap: 1rem;
      background: rgba(255, 255, 255, 0.8);
      padding: 0.5rem 1rem;
      border-radius: 16px;
      border: 1px solid rgba(0, 0, 0, 0.08);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    }
    .pres-cover-logos img {
      height: 28px;
      object-fit: contain;
      display: block;
    }
    .pres-cover-logos .logo-danantara {
      height: 30px;
    }
    .pres-cover-logos .logo-bri {
      height: 20px;
    }
    .pres-cover-logos .logo-asix {
      height: 22px;
    }
    .pres-cover-logo-divider {
      width: 1px;
      height: 16px;
      background: rgba(0, 0, 0, 0.15);
    }

    /* Paginator slide counter badge */
    .pres-slide-counter-badge {
      font-size: 0.7rem;
      font-weight: 600;
      color: rgba(0, 0, 0, 0.4);
      background: rgba(0, 0, 0, 0.03);
      padding: 0.2rem 0.6rem;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, 0.04);
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-top: 0.25rem;
    }
    .pres-title-lbl {
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      color: #1d1d1f;
    }
    .pres-title-lbl span {
      color: #0071e3;
      font-weight: 400;
    }
    .pres-controls-right {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .pres-meta-chip {
      background: rgba(0, 0, 0, 0.04);
      border: 1px solid rgba(0, 0, 0, 0.06);
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      color: #1d1d1f;
      font-weight: 500;
    }
    .pres-nav-btn-back {
      background: rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(0, 0, 0, 0.08);
      color: #1d1d1f !important;
      height: 32px;
      border-radius: 20px;
      padding: 0 1rem;
      font-size: 0.75rem;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .pres-nav-btn-back:hover {
      background: rgba(0, 0, 0, 0.09);
      border-color: rgba(0, 0, 0, 0.15);
      transform: scale(1.02);
    }
    .pres-slides-container {
      position: relative;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 5;
    }
    .apple-slide {
      position: absolute;
      width: 100%;
      height: 100%;
      max-width: 1360px;
      max-height: 760px;
      opacity: 0;
      visibility: hidden;
      transform: translateX(120px) scale(0.96);
      transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
      justify-content: center;
      box-sizing: border-box;
    }
    .apple-slide.active {
      opacity: 1;
      visibility: visible;
      transform: translateX(0) scale(1);
    }
    .apple-slide.prev {
      opacity: 0;
      visibility: hidden;
      transform: translateX(-120px) scale(0.96);
    }
    .apple-slide.active .animate-fade-in {
      opacity: 1 !important;
      transform: translateY(0) !important;
    }
    .apple-slide .animate-fade-in {
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .slide-delay-1 { transition-delay: 0.1s !important; }
    .slide-delay-2 { transition-delay: 0.2s !important; }
    .slide-delay-3 { transition-delay: 0.31s !important; }
    .slide-delay-4 { transition-delay: 0.42s !important; }
    .slide-delay-5 { transition-delay: 0.53s !important; }
    .slide-delay-6 { transition-delay: 0.64s !important; }

    .pres-glass-card {
      background: rgba(255, 255, 255, 0.75);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(0, 0, 0, 0.06);
      border-radius: 20px;
      padding: 1.75rem;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.03);
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      color: #1d1d1f;
    }
    .pres-glass-card:hover {
      background: rgba(255, 255, 255, 0.95);
      border-color: rgba(59, 130, 246, 0.35);
      box-shadow: 0 20px 40px rgba(59, 130, 246, 0.06);
      transform: translateY(-2px);
    }
    .pres-glass-card-red:hover {
      border-color: rgba(239, 68, 68, 0.4) !important;
      box-shadow: 0 20px 40px rgba(239, 68, 68, 0.06) !important;
    }
    .pres-text-gradient-silver {
      background: linear-gradient(135deg, #1d1d1f 20%, #6e6e73 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .pres-text-gradient-blue {
      background: linear-gradient(135deg, #0071e3 20%, #004391 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .pres-progress-container {
      width: 100%;
      height: 8px;
      background: rgba(0, 0, 0, 0.05);
      border-radius: 4px;
      overflow: hidden;
      margin: 0.5rem 0;
    }
    .pres-progress-bar {
      height: 100%;
      border-radius: 4px;
      width: 0;
      transition: width 1.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .pres-spectrum-bar {
      width: 100%;
      height: 16px;
      background: rgba(0, 0, 0, 0.03);
      border-radius: 8px;
      display: flex;
      overflow: hidden;
      margin: 1.25rem 0;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }
    .pres-spectrum-segment {
      height: 100%;
      width: 0;
      transition: width 1.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .pres-grid-2x4 {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      grid-template-rows: repeat(2, 1fr);
      gap: 1.25rem;
      width: 100%;
      height: 100%;
    }
    .pres-paginator {
      display: flex;
      gap: 0.6rem;
      align-items: center;
    }
    .pres-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: rgba(0, 0, 0, 0.15);
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .pres-dot:hover {
      background: rgba(0, 0, 0, 0.3);
    }
    .pres-dot.active {
      background: #0071e3;
      width: 28px;
      border-radius: 5px;
      box-shadow: 0 0 10px rgba(0, 113, 227, 0.3);
    }
    .pres-nav-btn {
      background: rgba(0, 0, 0, 0.04);
      border: 1px solid rgba(0, 0, 0, 0.08);
      color: rgba(0, 0, 0, 0.65);
      width: 38px;
      height: 38px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .pres-nav-btn:hover {
      background: rgba(0, 0, 0, 0.08);
      color: #000000;
      border-color: rgba(0, 0, 0, 0.15);
      transform: translateY(-1px);
    }
    .pres-nav-buttons-container {
      display: flex;
      gap: 0.5rem;
    }
    .pres-kpi-huge-number {
      font-size: 5.5rem;
      font-weight: 850;
      line-height: 1;
      letter-spacing: -0.04em;
      margin-top: 0.5rem;
      max-width: 100%;
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-kpi-sub-trend {
      display: inline-flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.35rem;
      padding: 0.4rem 0.85rem;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 700;
      margin-top: 0.75rem;
      border: 1px solid transparent;
      max-width: 100%;
      line-height: 1.25;
      overflow-wrap: anywhere;
    }
    .pres-kpi-sub-trend.pos {
      background: rgba(16, 185, 129, 0.08);
      color: #047857;
      border-color: rgba(16, 185, 129, 0.15);
    }
    .pres-kpi-sub-trend.neg {
      background: rgba(239, 68, 68, 0.08);
      color: #b91c1c;
      border-color: rgba(239, 68, 68, 0.15);
    }
    .pres-table-dense {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
      table-layout: auto;
    }
    .pres-table-dense th {
      border-bottom: 1px solid rgba(0, 0, 0, 0.08);
      padding: 0.6rem 0.5rem;
      text-align: left;
      font-weight: 600;
      color: rgba(29, 29, 31, 0.5);
      text-transform: uppercase;
      font-size: 0.72rem;
      letter-spacing: 0.05em;
    }
    .pres-table-dense td {
      padding: 0.5rem 0.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.04);
      color: #1d1d1f;
      vertical-align: top;
    }
    .pres-table-dense tr:last-child td {
      border-bottom: none;
    }
    .pres-kts-branch-header td {
      background: rgba(0, 113, 227, 0.06) !important;
      font-weight: 850;
      color: #0071e3 !important;
      padding: 0.55rem 0.8rem !important;
      border-bottom: 2px solid rgba(0, 113, 227, 0.12) !important;
      font-size: 0.8rem !important;
    }
    .pres-kts-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.18rem 0.45rem;
      border-radius: 4px;
      font-size: 0.72rem;
      font-weight: 700;
      gap: 0.25rem;
    }
    .pres-kts-badge.badge-membaik {
      background: rgba(16, 185, 129, 0.09);
      color: #10b981;
      border: 1px solid rgba(16, 185, 129, 0.18);
    }
    .pres-kts-badge.badge-memburuk {
      background: rgba(239, 68, 68, 0.09);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.18);
    }
    .pres-grid-2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2.5rem;
      width: 100%;
    }
    .pres-splash-accent-btn {
      background: linear-gradient(135deg, #0071e3, #005bb7);
      color: #ffffff !important;
      border: none;
      padding: 0.8rem 2.2rem;
      border-radius: 30px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 8px 24px rgba(0, 113, 227, 0.25);
      margin-top: 1.5rem;
    }
    .pres-splash-accent-btn:hover {
      background: linear-gradient(135deg, #1f8bfd, #0071e3);
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 12px 32px rgba(0, 113, 227, 0.35);
    }

    /* Executive presentation polish */
    .apple-presentation-mode {
      background:
        linear-gradient(180deg, #ffffff 0%, #f7f9fc 46%, #eef3f8 100%) !important;
      padding: 5rem 3.25rem 4.75rem 3.25rem !important;
    }
    .apple-presentation-mode::before,
    .apple-presentation-mode::after {
      display: none !important;
    }
    .apple-slide {
      max-width: 1400px !important;
      max-height: 780px !important;
      transform: translateX(48px) scale(0.985) !important;
      transition: all 0.48s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .apple-slide.active {
      transform: translateX(0) scale(1) !important;
    }
    .apple-slide.prev {
      transform: translateX(-48px) scale(0.985) !important;
    }
    .apple-slide h1,
    .apple-slide h2,
    .apple-slide h3,
    .pres-kpi-huge-number {
      letter-spacing: 0 !important;
    }
    .pres-top-bar,
    .pres-bottom-bar {
      left: 2.25rem !important;
      right: 2.25rem !important;
    }
    .pres-top-bar {
      top: 1.1rem !important;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }
    .pres-bottom-bar {
      bottom: 1rem !important;
      padding-top: 0.65rem;
      border-top: 1px solid rgba(15, 23, 42, 0.08);
    }
    .pres-title-lbl,
    .pres-meta-chip,
    .pres-date-picker-select,
    .pres-nav-btn-back {
      color: #0f172a !important;
    }
    .pres-title-lbl span {
      color: #0857c3 !important;
      font-weight: 700 !important;
    }
    .pres-meta-chip,
    .pres-nav-btn-back,
    .pres-nav-btn {
      background: #ffffff !important;
      border: 1px solid rgba(15, 23, 42, 0.12) !important;
      border-radius: 8px !important;
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    }
    .pres-glass-card {
      background: #ffffff !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
      border: 1px solid rgba(15, 23, 42, 0.10) !important;
      border-radius: 8px !important;
      box-shadow: 0 16px 36px rgba(15, 23, 42, 0.07) !important;
    }
    .pres-glass-card:hover {
      transform: none !important;
      border-color: rgba(8, 87, 195, 0.24) !important;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08) !important;
    }
    .pres-text-gradient-silver,
    .pres-text-gradient-blue {
      background: none !important;
      -webkit-background-clip: initial !important;
      -webkit-text-fill-color: currentColor !important;
    }
    .pres-text-gradient-silver {
      color: #0f172a !important;
    }
    .pres-text-gradient-blue {
      color: #0857c3 !important;
    }
    .pres-kpi-huge-number {
      font-size: clamp(2.85rem, 5.6vw, 4.8rem) !important;
      color: #0857c3 !important;
      line-height: 1.02 !important;
    }
    .pres-kpi-sub-trend {
      border-radius: 8px !important;
    }
    .pres-rka-strip {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.7rem;
      margin-top: 1rem;
    }
    .pres-rka-strip > div {
      background: #f8fafc;
      border: 1px solid rgba(15, 23, 42, 0.10);
      border-radius: 8px;
      padding: 0.65rem 0.75rem;
    }
    .pres-rka-strip span {
      display: block;
      font-size: 0.66rem;
      font-weight: 800;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.02em;
    }
    .pres-rka-strip strong {
      display: block;
      margin-top: 0.16rem;
      font-size: clamp(0.82rem, 1vw, 1rem);
      color: #0f172a;
      line-height: 1.2;
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-grid-2col {
      gap: 1.6rem !important;
    }
    .pres-grid-2x4 {
      gap: 0.9rem !important;
    }
    .pres-table-dense th {
      background: #f1f5f9;
      border-bottom: 1px solid rgba(15, 23, 42, 0.10) !important;
      color: #475569 !important;
      font-size: 0.66rem !important;
      letter-spacing: 0.02em !important;
      white-space: nowrap;
    }
    .pres-table-dense td {
      border-bottom: 1px solid rgba(15, 23, 42, 0.07) !important;
      color: #0f172a !important;
      font-variant-numeric: tabular-nums;
    }
    .pres-table-dense th:not(:first-child),
    .pres-table-dense td:not(:first-child) {
      white-space: nowrap;
    }
    .pres-table-dense th:first-child,
    .pres-table-dense td:first-child {
      overflow-wrap: anywhere;
    }
    .pres-progress-container,
    .pres-spectrum-bar {
      background: #e2e8f0 !important;
      border-radius: 4px !important;
    }
    .pres-splash-accent-btn {
      background: #0857c3 !important;
      border-radius: 8px !important;
      box-shadow: 0 12px 28px rgba(8, 87, 195, 0.22) !important;
    }
    .pres-splash-accent-btn:hover {
      background: #06489f !important;
      transform: translateY(-1px) !important;
    }

    /* Dropdown design */
    .pres-date-picker-select {
      background: transparent;
      border: none;
      color: #1d1d1f;
      font-size: 0.75rem;
      font-weight: 600;
      padding-right: 1.25rem;
      cursor: pointer;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
    }

    .pres-cover-layout {
      display: grid;
      grid-template-columns: 0.9fr 1.35fr;
      gap: 1.1rem;
      align-items: stretch;
      width: 100%;
      height: 100%;
      min-height: 0;
    }
    .pres-cover-lead {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 0;
    }
    .pres-cover-eyebrow,
    .pres-section-eyebrow {
      font-size: 0.72rem;
      font-weight: 800;
      color: #0857c3;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .pres-cover-title {
      margin: 0.65rem 0 0;
      font-size: clamp(2rem, 3.5vw, 3rem);
      line-height: 1.1;
      font-weight: 900;
      color: #0f172a;
      white-space: nowrap;
    }
    .pres-cover-subtitle {
      margin: 0.85rem 0 0;
      color: #475569;
      font-size: 0.95rem;
      line-height: 1.55;
      max-width: 35rem;
    }
    .pres-cover-strip {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.7rem;
      margin-top: 1.15rem;
    }
    .pres-cover-stat {
      background: #f8fafc;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 8px;
      padding: 0.72rem 0.78rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.5rem;
    }
    .pres-mini-stat {
      background: #f8fafc;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 8px;
      padding: 0.55rem 0.72rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      justify-content: center;
      gap: 0.15rem;
      min-width: 0;
    }
    .pres-cover-stat span,
    .pres-mini-stat span {
      display: block;
      font-size: 0.65rem;
      font-weight: 800;
      color: #64748b;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
    .pres-cover-stat strong,
    .pres-mini-stat strong {
      display: block;
      margin-top: 0.24rem;
      color: #0f172a;
      font-size: clamp(0.78rem, 1.05vw, 1.05rem);
      line-height: 1.18;
      font-weight: 850;
      max-width: 100%;
      white-space: normal;
      overflow: visible;
      text-overflow: clip;
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-cover-board {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 0.75rem;
      min-height: 0;
    }
    @media (min-width: 1025px) {
      .card-span-2 {
        grid-column: span 2 !important;
      }
      .card-span-3 {
        grid-column: span 3 !important;
      }
    }
    .pres-cover-card {
      min-height: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 0.9rem !important;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .pres-cover-card:hover .pres-card-icon {
      transform: scale(1.1) rotate(5deg);
    }
    .pres-cover-card .label {
      font-size: 0.66rem;
      font-weight: 800;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .pres-cover-card .value {
      margin-top: 0.4rem;
      color: #0f172a;
      font-size: clamp(1.05rem, 1.55vw, 1.45rem);
      line-height: 1.05;
      font-weight: 900;
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-cover-card .meta {
      margin-top: 0.35rem;
      color: #64748b;
      font-size: 0.68rem;
      font-weight: 650;
      line-height: 1.35;
      overflow-wrap: anywhere;
    }
    .pres-cover-card > div:first-child > div:first-child,
    .pres-cover-stat > div {
      min-width: 0;
    }
    .pres-cover-card-chart {
      margin-top: 0.55rem;
      min-height: 58px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      gap: 0.18rem;
    }
    .pres-mini-series-svg {
      display: block;
      width: 100%;
      height: 38px;
      overflow: visible;
    }
    .pres-mini-series-labels {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.14rem;
      color: #94a3b8;
      font-size: 0.5rem;
      font-weight: 850;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .pres-mini-series-labels span {
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .pres-mini-series-empty {
      height: 38px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px dashed rgba(100, 116, 139, 0.22);
      border-radius: 8px;
      color: #94a3b8;
      font-size: 0.6rem;
      font-weight: 800;
    }
    .pres-control-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
      align-items: center;
      justify-content: space-between;
      margin: 0.85rem 0 0.8rem;
    }
    .pres-control-group {
      display: inline-flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      align-items: center;
      background: #eef3f8;
      border: 1px solid rgba(15, 23, 42, 0.1);
      border-radius: 8px;
      padding: 0.24rem;
    }
    .pres-toggle-btn {
      border: 0;
      background: transparent;
      color: #475569;
      border-radius: 6px;
      padding: 0.44rem 0.62rem;
      font-size: 0.72rem;
      line-height: 1;
      font-weight: 800;
      cursor: pointer;
      white-space: nowrap;
    }
    .pres-toggle-btn.active {
      background: #ffffff;
      color: #0857c3;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    }
    .pres-compact-select {
      height: 32px;
      border: 1px solid rgba(15, 23, 42, 0.14);
      border-radius: 8px;
      background: #ffffff;
      color: #0f172a;
      font-size: 0.72rem;
      font-weight: 750;
      padding: 0 0.65rem;
      outline: none;
      max-width: 100%;
    }
    .pres-explorer-grid {
      display: grid;
      grid-template-columns: 1fr 1.25fr;
      gap: 0.9rem;
      height: calc(100% - 6.1rem);
      min-height: 0;
    }
    .pres-explorer-side {
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 0.75rem;
      min-height: 0;
    }
    .pres-mini-stat-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.6rem;
    }
    .pres-table-scroll {
      overflow: auto;
      min-height: 0;
      max-height: 420px;
      padding-right: 0.2rem;
    }
    .pres-table-scroll table {
      min-width: 860px;
    }
    #pres-explorer-table-wrap .pres-table-dense th:first-child,
    #pres-explorer-table-wrap .pres-table-dense td:first-child,
    .pres-segment-panel .pres-table-dense th:first-child,
    .pres-segment-panel .pres-table-dense td:first-child {
      min-width: 8.5rem;
    }
    .pres-delta {
      font-weight: 850;
      white-space: nowrap;
      font-variant-numeric: tabular-nums;
    }
    .pres-delta.pos {
      color: #047857;
    }
    .pres-delta.neg {
      color: #dc2626;
    }
    .pres-chart-wrap {
      position: relative;
      width: 100%;
      height: 100%;
      min-height: 0;
    }
    .pres-chart-wrap.hidden,
    .pres-table-scroll.hidden {
      display: none;
    }
    #pres-slide-1 .pres-table-scroll {
      flex: 1;
      max-height: none;
    }
    #pres-slide-1 .pres-mini-stat-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    #pres-slide-1 .pres-mini-stat {
      min-height: 58px;
    }
    #pres-explorer-mtm-mtd,
    #pres-explorer-ytd,
    #pres-explorer-latest {
      font-size: clamp(0.72rem, 0.9vw, 0.96rem);
    }
    #pres-explorer-periods,
    #pres-seg-explorer-periods {
      max-width: min(44%, 24rem);
      white-space: normal;
      overflow-wrap: anywhere;
      line-height: 1.25;
      text-align: right;
    }
    #pres-slide-1 .pres-explorer-side .pres-glass-card {
      display: flex;
      flex-direction: column;
    }
    .pres-segment-panel {
      min-height: 0;
    }
    .pres-segment-summary-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.45rem;
      margin-bottom: 0.65rem;
    }
    .pres-segment-summary-item {
      background: linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(241, 245, 249, 0.72));
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 8px;
      padding: 0.55rem 0.65rem;
      min-width: 0;
    }
    .pres-segment-summary-item span {
      display: block;
      color: #64748b;
      font-size: 0.57rem;
      font-weight: 850;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .pres-segment-summary-item strong {
      display: block;
      margin-top: 0.2rem;
      color: #0f172a;
      font-size: clamp(0.68rem, 0.82vw, 0.86rem);
      line-height: 1.15;
      font-weight: 900;
      white-space: normal;
      overflow: visible;
      text-overflow: clip;
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-segment-panel .pres-table-scroll table {
      min-width: 900px;
    }
    .pres-digital-value {
      font-size: clamp(1rem, 1.45vw, 1.45rem) !important;
      line-height: 1.08;
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-digital-list .pres-glass-card strong {
      overflow-wrap: anywhere;
      font-variant-numeric: tabular-nums;
    }
    .pres-seg-footnote {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.66rem;
      color: #64748b;
      border-top: 1px solid rgba(15, 23, 42, 0.08);
      padding-top: 0.55rem;
      margin-top: 0.55rem;
    }
    .pres-seg-row-muted td {
      color: #64748b !important;
    }
    #pres-slide-3 {
      overflow: hidden;
    }
    .pres-timeseries-grid {
      height: calc(100% - 5.9rem);
      max-height: 560px;
      min-height: 0;
      align-items: stretch;
    }
    .pres-timeseries-card {
      display: flex;
      flex-direction: column;
      height: 100%;
      min-height: 0;
      overflow: hidden;
      padding: 1.05rem 1.2rem !important;
    }
    .pres-timeseries-card-header {
      flex: 0 0 auto;
      margin-bottom: 0.55rem !important;
    }
    .pres-timeseries-chart-box {
      position: relative;
      flex: 1 1 auto;
      height: 0;
      min-height: 0;
      overflow: hidden;
      width: 100%;
    }
    .pres-timeseries-chart-box canvas {
      display: block !important;
      width: 100% !important;
      height: 100% !important;
      max-height: 100% !important;
    }
    .pres-kts-grid {
      display: grid;
      grid-template-columns: 0.95fr 1.35fr;
      gap: 0.9rem;
      height: 100%;
      min-height: 0;
    }
    .pres-kts-summary {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.65rem;
      align-content: start;
    }
    .pres-digital-layout {
      display: grid;
      grid-template-columns: 0.95fr 1.45fr;
      gap: 0.9rem;
      height: 100%;
      min-height: 0;
    }
    .pres-digital-list {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.6rem;
      overflow: auto;
      min-height: 0;
      padding-right: 0.2rem;
    }

    /* Standalone Loader overlay */
    .dashboard-loading-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(245, 245, 247, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      z-index: 20000;
      opacity: 0; visibility: hidden;
      transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .dashboard-loading-overlay.active {
      opacity: 1; visibility: visible;
    }
    .loading-spinner-container {
      position: relative;
      width: 80px; height: 80px;
      margin-bottom: 1.5rem;
    }
    .loading-ring {
      box-sizing: border-box;
      display: block; position: absolute;
      width: 80px; height: 80px;
      border: 4px solid transparent;
      border-radius: 50%;
      border-top-color: #0071e3;
      animation: loading-spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
    }
    .loading-ring-inner {
      box-sizing: border-box;
      display: block; position: absolute;
      width: 60px; height: 60px; top: 10px; left: 10px;
      border: 4px solid transparent;
      border-radius: 50%;
      border-bottom-color: #1f8bfd;
      animation: loading-spin-reverse 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
    }
    .dashboard-loading-text {
      font-size: 1.1rem;
      font-weight: 700;
      color: #1d1d1f;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      animation: loading-pulse 1.8s ease-in-out infinite;
    }
    .dashboard-loading-sub {
      font-size: 0.72rem;
      color: rgba(0, 0, 0, 0.4);
      margin-top: 0.35rem;
    }
    @keyframes loading-spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    @keyframes loading-spin-reverse {
      0% { transform: rotate(360deg); }
      100% { transform: rotate(0deg); }
    }
    @keyframes loading-pulse {
      0%, 100% { opacity: 0.6; }
      50% { opacity: 1; }
    }

    /* ==========================================================================
       RESPONSIVE & MULTI-DEVICE SUPPORT (SMARTPHONE, TABLET, WIDESCREEN)
       ========================================================================== */

    /* 1. LAYAR LEBAR / WIDESCREEN (min-width: 1600px) */
    @media (min-width: 1600px) {
      .apple-slide {
        max-width: 1560px !important;
        max-height: 860px !important;
      }
      .pres-cover-layout {
        gap: 2rem !important;
      }
      .pres-cover-board {
        gap: 1.25rem !important;
      }
      .pres-kpi-huge-number {
        font-size: clamp(3.5rem, 6vw, 5.6rem) !important;
      }
    }

    /* 2. TABLET & PORTRAIT IPAD (max-width: 1024px) */
    @media (max-width: 1024px) {
      .apple-presentation-mode {
        padding: 4.8rem 1.5rem 4.8rem 1.5rem !important;
      }
      .apple-slide {
        max-width: none !important;
        max-height: none !important;
        height: 100% !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        justify-content: flex-start !important;
        padding: 0.75rem 0.5rem !important;
        scrollbar-width: thin;
      }
      .apple-slide::-webkit-scrollbar {
        width: 6px;
      }
      .apple-slide::-webkit-scrollbar-thumb {
        background: rgba(15, 23, 42, 0.15);
        border-radius: 4px;
      }

      /* Grids adjustment to stacked layout */
      .pres-cover-layout,
      .pres-explorer-grid,
      .pres-grid-2col,
      .pres-kts-grid,
      .pres-digital-layout {
        grid-template-columns: 1fr !important;
        gap: 1.25rem !important;
        height: auto !important;
      }

      .pres-cover-board {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.85rem !important;
      }

      .pres-digital-list {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 0.75rem !important;
      }

      .pres-timeseries-grid {
        max-height: none !important;
      }

      /* Compact text button kembali di tablet */
      .pres-back-text {
        font-size: 0 !important;
      }
      .pres-back-text::before {
        content: "Kembali";
        font-size: 0.75rem;
        font-weight: 600;
      }

      /* Hide subtitle brand in narrow screen */
      .pres-title-lbl span {
        display: none !important;
      }
    }

    /* 3. SMARTPHONE / MOBILE PORTRAIT (max-width: 640px) */
    @media (max-width: 640px) {
      .apple-presentation-mode {
        padding: 4.2rem 0.75rem 4.2rem 0.75rem !important;
      }

      .pres-top-bar {
        top: 0.75rem !important;
        left: 0.75rem !important;
        right: 0.75rem !important;
      }
      .pres-bottom-bar {
        bottom: 0.75rem !important;
        left: 0.75rem !important;
        right: 0.75rem !important;
      }

      /* Stack boards and covers */
      .pres-cover-board {
        grid-template-columns: 1fr !important;
      }
      .pres-cover-strip {
        grid-template-columns: 1fr !important;
      }
      .pres-mini-stat-grid {
        grid-template-columns: 1fr !important;
      }
      .pres-rka-strip {
        grid-template-columns: 1fr !important;
      }
      .pres-digital-list {
        grid-template-columns: 1fr !important;
      }
      .pres-segment-summary-grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }

      /* Scale typography down */
      .pres-cover-title {
        font-size: 2.1rem !important;
        white-space: normal !important;
        line-height: 1.15;
      }
      .pres-kpi-huge-number {
        font-size: 2.35rem !important;
      }
      .apple-slide h2 {
        font-size: 1.45rem !important;
      }
      .apple-slide h3 {
        font-size: 1.15rem !important;
      }

      /* Chart heights compacting */
      .pres-chart-wrap {
        height: 210px !important;
      }
      .pres-timeseries-chart-box {
        height: 210px !important;
      }
      #pres-explorer-bar-wrap {
        height: 290px !important;
      }

      /* Hide back text entirely on mobile (arrow icon only) */
      .pres-back-text {
        display: none !important;
      }
      .pres-nav-btn-back {
        padding: 0 0.55rem !important;
        height: 30px !important;
      }

      /* Make table horizontal scrolling smooth */
      .pres-table-scroll {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
      }

      /* Compact controls bar */
      .pres-control-bar {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 0.5rem !important;
      }
      .pres-control-group,
      .pres-compact-select {
        width: 100% !important;
        box-sizing: border-box;
        justify-content: center;
      }
      .pres-compact-select {
        text-align-last: center;
      }
    }

    /* Dashboard executive redesign: blue, dense, and presentation-ready. */
    :root {
      --pres-ink: #111827;
      --pres-ink-soft: #334155;
      --pres-muted: #64748b;
      --pres-paper: #ffffff;
      --pres-page: #eef6ff;
      --pres-line: #cfe0f4;
      --pres-academic: #1155c8;
      --pres-academic-dark: #0a3f9d;
      --pres-accent: #008f7a;
      --pres-gold: #f59e0b;
      --pres-blue: #0f73d8;
      --pres-cyan: #2fb8df;
      --pres-danger: #ef4444;
      --pres-shadow: 0 18px 46px rgba(12, 57, 119, 0.13);
      --pres-shadow-soft: 0 8px 22px rgba(12, 57, 119, 0.08);
    }

    body,
    html {
      background:
        linear-gradient(90deg, rgba(15, 23, 42, 0.025) 1px, transparent 1px),
        linear-gradient(180deg, rgba(15, 23, 42, 0.02) 1px, transparent 1px),
        #eef6ff !important;
      background-size: 38px 38px, 38px 38px, auto !important;
      color: var(--pres-ink) !important;
    }

    .apple-presentation-mode {
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(239, 247, 255, 0.96) 54%, rgba(226, 239, 252, 0.98) 100%) !important;
      padding: 5.2rem 2.35rem 4.7rem !important;
    }

    .apple-presentation-mode::before,
    .apple-presentation-mode::after {
      display: none !important;
    }

    .pres-slides-container {
      isolation: isolate;
    }

    .apple-slide {
      max-width: 1760px !important;
      max-height: 840px !important;
      padding: 0.15rem !important;
    }

    .apple-slide h1,
    .apple-slide h2,
    .apple-slide h3 {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif !important;
      letter-spacing: 0 !important;
      color: var(--pres-ink) !important;
    }

    .apple-slide h2 {
      font-size: clamp(1.8rem, 2.35vw, 2.55rem) !important;
      line-height: 1.06 !important;
      margin-bottom: 1rem !important;
    }

    .pres-top-bar,
    .pres-bottom-bar {
      left: 2rem !important;
      right: 2rem !important;
      background: rgba(255, 255, 255, 0.88);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(216, 224, 234, 0.94) !important;
      box-shadow: 0 10px 30px rgba(17, 24, 39, 0.06);
    }

    .pres-top-bar {
      top: 0.85rem !important;
      padding: 0.62rem 0.75rem !important;
      border-left: 4px solid var(--pres-academic) !important;
      border-radius: 8px !important;
    }

    .pres-bottom-bar {
      bottom: 0.82rem !important;
      padding: 0.52rem 0.68rem !important;
      border-radius: 8px !important;
    }

    .pres-logos-wrapper,
    .pres-cover-logos {
      background: #ffffff !important;
      border: 1px solid var(--pres-line) !important;
      border-radius: 8px !important;
      box-shadow: none !important;
    }

    .pres-title-lbl {
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 1.02rem !important;
      letter-spacing: 0 !important;
      color: var(--pres-ink) !important;
    }

    .pres-title-lbl span {
      color: var(--pres-academic) !important;
      font-family: 'Inter', sans-serif;
      font-size: 0.82rem;
      font-weight: 800 !important;
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }

    .pres-meta-chip,
    .pres-nav-btn-back,
    .pres-nav-btn,
    .pres-control-group,
    .pres-compact-select {
      border-radius: 8px !important;
      border-color: var(--pres-line) !important;
      background: #ffffff !important;
      box-shadow: 0 6px 18px rgba(17, 24, 39, 0.05) !important;
      color: var(--pres-ink) !important;
    }

    .pres-nav-btn:hover,
    .pres-nav-btn-back:hover {
      border-color: rgba(17, 85, 200, 0.36) !important;
      color: var(--pres-academic) !important;
      transform: translateY(-1px) !important;
    }

    .pres-date-picker-select {
      color: var(--pres-ink) !important;
      font-weight: 800 !important;
    }

    .pres-section-eyebrow,
    .pres-cover-eyebrow,
    .apple-slide [style*="#0071e3"],
    .apple-slide [style*="color:#0071e3"],
    .apple-slide [style*="color: #0071e3"] {
      color: var(--pres-academic) !important;
    }

    .pres-section-eyebrow,
    .pres-cover-eyebrow {
      letter-spacing: 0.12em !important;
      font-size: 0.68rem !important;
      position: relative;
      padding-left: 0.95rem;
    }

    .pres-section-eyebrow::before,
    .pres-cover-eyebrow::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      width: 0.55rem;
      height: 2px;
      background: var(--pres-gold);
      transform: translateY(-50%);
    }

    .pres-text-gradient-silver,
    .pres-text-gradient-blue {
      background: none !important;
      -webkit-background-clip: initial !important;
      -webkit-text-fill-color: currentColor !important;
      color: var(--pres-ink) !important;
    }

    .pres-glass-card {
      background: var(--pres-paper) !important;
      border: 1px solid var(--pres-line) !important;
      border-radius: 8px !important;
      box-shadow: var(--pres-shadow-soft) !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
    }

    .pres-glass-card:hover {
      transform: none !important;
      border-color: rgba(17, 85, 200, 0.26) !important;
      box-shadow: var(--pres-shadow) !important;
    }

    .pres-cover-layout {
      grid-template-columns: minmax(320px, 0.82fr) minmax(620px, 1.38fr) !important;
      gap: 1rem !important;
    }

    .pres-cover-lead {
      padding: 1.35rem 1.45rem !important;
      border-top: 5px solid var(--pres-academic) !important;
      background:
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
    }

    .pres-cover-title {
      font-size: clamp(2.15rem, 3vw, 3.35rem) !important;
      line-height: 1.02 !important;
      white-space: normal !important;
      color: var(--pres-ink) !important;
    }

    .pres-cover-subtitle {
      color: var(--pres-ink-soft) !important;
      font-size: 0.92rem !important;
      line-height: 1.58 !important;
    }

    .pres-cover-board {
      grid-template-columns: repeat(6, 1fr) !important;
      gap: 0.62rem !important;
    }

    .pres-cover-card {
      padding: 0.78rem 0.82rem !important;
      min-height: 142px !important;
      border-top: 3px solid rgba(17, 85, 200, 0.86) !important;
    }

    .pres-cover-card .label,
    .pres-cover-stat span,
    .pres-mini-stat span,
    .pres-segment-summary-item span {
      color: var(--pres-muted) !important;
      letter-spacing: 0.07em !important;
    }

    .pres-cover-card .value,
    .pres-cover-stat strong,
    .pres-mini-stat strong,
    .pres-segment-summary-item strong {
      color: var(--pres-ink) !important;
      font-variant-numeric: tabular-nums !important;
    }

    .pres-cover-card .value {
      font-size: clamp(1rem, 1.18vw, 1.32rem) !important;
    }

    .pres-cover-stat,
    .pres-mini-stat,
    .pres-rka-strip > div,
    .pres-segment-summary-item {
      background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%) !important;
      border-color: var(--pres-line) !important;
      border-radius: 8px !important;
    }

    .pres-splash-accent-btn {
      background: var(--pres-academic) !important;
      border: 1px solid var(--pres-academic-dark) !important;
      border-radius: 8px !important;
      box-shadow: 0 14px 30px rgba(17, 85, 200, 0.22) !important;
      letter-spacing: 0.02em !important;
    }

    .pres-splash-accent-btn:hover {
      background: var(--pres-academic-dark) !important;
      transform: translateY(-1px) !important;
    }

    .pres-toggle-btn {
      border-radius: 6px !important;
      color: var(--pres-ink-soft) !important;
      font-weight: 850 !important;
    }

    .pres-toggle-btn.active {
      background: var(--pres-academic) !important;
      color: #ffffff !important;
      box-shadow: 0 8px 18px rgba(17, 85, 200, 0.18) !important;
    }

    .pres-explorer-grid {
      grid-template-columns: minmax(360px, 0.78fr) minmax(700px, 1.32fr) !important;
      gap: 0.9rem !important;
      height: calc(100% - 5.4rem) !important;
    }

    .pres-grid-2col {
      gap: 1rem !important;
    }

    .pres-table-scroll {
      border: 1px solid rgba(216, 224, 234, 0.78);
      border-radius: 8px;
      background: #ffffff;
      scrollbar-width: thin;
      scrollbar-color: rgba(17, 85, 200, 0.35) transparent;
    }

    .pres-table-scroll::-webkit-scrollbar {
      width: 7px;
      height: 7px;
    }

    .pres-table-scroll::-webkit-scrollbar-thumb {
      background: rgba(17, 85, 200, 0.35);
      border-radius: 8px;
    }

    .pres-table-dense {
      border-collapse: separate !important;
      border-spacing: 0 !important;
      width: 100%;
      font-size: 0.78rem !important;
    }

    .pres-table-dense thead th {
      position: sticky;
      top: 0;
      z-index: 2;
      background: #eef3f8 !important;
      color: var(--pres-ink-soft) !important;
      border-bottom: 1px solid var(--pres-line) !important;
      padding: 0.55rem 0.65rem !important;
      text-transform: uppercase;
      letter-spacing: 0.055em !important;
      font-size: 0.63rem !important;
    }

    .pres-table-dense tbody td {
      padding: 0.54rem 0.65rem !important;
      border-bottom: 1px solid rgba(216, 224, 234, 0.68) !important;
      color: var(--pres-ink) !important;
      line-height: 1.18 !important;
      font-variant-numeric: tabular-nums !important;
    }

    .pres-table-dense tbody tr:nth-child(even) td {
      background: rgba(248, 250, 252, 0.74);
    }

    .pres-table-dense tbody tr:hover td {
      background: rgba(17, 85, 200, 0.045);
    }

    .pres-delta.pos {
      color: var(--pres-accent) !important;
    }

    .pres-delta.neg {
      color: #b91c1c !important;
    }

    .pres-kpi-huge-number {
      color: var(--pres-academic) !important;
      font-size: clamp(2.65rem, 5vw, 4.55rem) !important;
      line-height: 1 !important;
      font-variant-numeric: tabular-nums !important;
    }

    .pres-chart-wrap,
    .pres-timeseries-chart-box {
      background:
        linear-gradient(180deg, rgba(248, 250, 252, 0.55), rgba(255, 255, 255, 0.96));
      border: 1px solid rgba(216, 224, 234, 0.72);
      border-radius: 8px;
      padding: 0.35rem;
      box-sizing: border-box;
    }

    .pres-timeseries-grid {
      height: calc(100% - 5rem) !important;
      max-height: 595px !important;
      gap: 0.9rem !important;
    }

    .pres-timeseries-card {
      padding: 1rem 1rem 0.9rem !important;
    }

    .pres-timeseries-card-header h3 {
      font-size: clamp(1.05rem, 1.28vw, 1.32rem) !important;
    }

    .pres-spectrum-bar {
      border-radius: 5px !important;
      border: 1px solid rgba(216, 224, 234, 0.86);
      overflow: hidden;
    }

    .pres-kts-grid,
    .pres-digital-layout {
      gap: 0.9rem !important;
    }

    .pres-digital-list {
      grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
      gap: 0.58rem !important;
    }

    .pres-digital-list .pres-glass-card {
      border-left: 4px solid var(--pres-academic) !important;
    }

    .pres-digital-value {
      color: var(--pres-ink) !important;
    }

    .pres-paginator {
      background: rgba(255, 255, 255, 0.82);
      border: 1px solid rgba(216, 224, 234, 0.9);
      border-radius: 999px;
      padding: 0.22rem 0.38rem;
    }

    .pres-dot {
      background: #cbd5e1 !important;
      border-radius: 999px !important;
    }

    .pres-dot.active {
      background: var(--pres-academic) !important;
      width: 1.65rem !important;
      box-shadow: 0 4px 12px rgba(17, 85, 200, 0.28);
    }

    .pres-slide-counter-badge {
      color: var(--pres-muted) !important;
      background: #ffffff !important;
      border: 1px solid var(--pres-line) !important;
      border-radius: 8px !important;
    }

    .dashboard-loading-overlay {
      background: rgba(238, 246, 255, 0.92) !important;
    }

    .loading-ring {
      border-top-color: var(--pres-academic) !important;
    }

    .loading-ring-inner {
      border-bottom-color: var(--pres-gold) !important;
    }

    #loading-progress-bar {
      background: linear-gradient(90deg, var(--pres-academic), var(--pres-gold)) !important;
    }

    #loading-progress-percent {
      color: var(--pres-academic) !important;
    }

    .pres-panel-card {
      overflow: hidden !important;
      padding: 0 !important;
    }

    .pres-panel-header {
      min-height: 3rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.85rem;
      padding: 0.72rem 1rem;
      background: linear-gradient(90deg, var(--pres-academic-dark), var(--pres-academic));
      color: #ffffff;
      font-weight: 900;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      font-size: 0.78rem;
    }

    .pres-panel-header.pres-panel-header-cyan {
      background: linear-gradient(90deg, #1496c8, var(--pres-cyan));
    }

    .pres-panel-header.pres-panel-header-orange {
      background: linear-gradient(90deg, #f97316, var(--pres-gold));
    }

    .pres-panel-header span {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      min-width: 0;
    }

    .pres-panel-icon {
      width: 1.75rem;
      height: 1.75rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.18);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.24);
      flex: 0 0 auto;
    }

    .pres-panel-subtitle {
      color: rgba(255, 255, 255, 0.86);
      font-size: 0.66rem;
      font-weight: 850;
      text-align: right;
      text-transform: none;
      letter-spacing: 0.01em;
    }

    .pres-panel-body {
      padding: 1rem;
      min-height: 0;
    }

    .pres-value-bar {
      min-width: 7.5rem;
      display: grid;
      gap: 0.28rem;
      justify-items: end;
    }

    .pres-value-bar strong {
      font-size: 0.78rem;
      line-height: 1;
      font-weight: 900;
      color: inherit;
      white-space: nowrap;
    }

    .pres-value-bar-track {
      width: min(8.5rem, 100%);
      height: 0.28rem;
      overflow: hidden;
      border-radius: 999px;
      background: #e2e8f0;
    }

    .pres-value-bar-fill {
      display: block;
      height: 100%;
      min-width: 0.18rem;
      border-radius: inherit;
    }

    .pres-risk-composition-layout {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 1.5rem;
      align-items: center;
    }

    .pres-donut-shell {
      min-height: 210px;
      display: grid;
      place-items: center;
      position: relative;
      border-radius: 8px;
      background: linear-gradient(180deg, #f8fbff, #ffffff);
      border: 1px solid rgba(207, 224, 244, 0.9);
    }

    .pres-donut-canvas-wrapper {
      position: relative;
      width: 190px;
      height: 190px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .pres-donut-canvas-wrapper canvas {
      width: 100% !important;
      height: 100% !important;
      max-width: 100%;
    }

    .pres-donut-center {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      pointer-events: none;
      text-align: center;
    }

    .pres-donut-center strong {
      display: block;
      color: var(--pres-ink);
      font-size: 1.35rem;
      font-weight: 950;
      line-height: 1;
    }

    .pres-donut-center span {
      display: block;
      margin-top: 0.28rem;
      color: var(--pres-muted);
      font-size: 0.62rem;
      font-weight: 900;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .pres-risk-metric-grid {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 0.65rem;
    }

    .pres-risk-metric {
      min-height: 4.25rem;
      padding: 0.72rem;
      border-radius: 8px;
      background: #ffffff;
      border: 1px solid rgba(207, 224, 244, 0.9);
    }

    .pres-risk-metric span {
      display: block;
      color: var(--pres-muted);
      font-size: 0.62rem;
      font-weight: 900;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .pres-risk-metric strong {
      display: block;
      margin-top: 0.32rem;
      font-size: 1.02rem;
      font-weight: 950;
      font-variant-numeric: tabular-nums;
    }

    .pres-timeseries-card {
      overflow: hidden !important;
      padding: 0 !important;
    }

    .pres-timeseries-card-header {
      min-height: 3.35rem;
      padding: 0.78rem 1rem;
      background: linear-gradient(90deg, var(--pres-academic-dark), var(--pres-academic));
      color: #ffffff;
    }

    .pres-timeseries-card:nth-child(2) .pres-timeseries-card-header {
      background: linear-gradient(90deg, #1496c8, var(--pres-cyan));
    }

    .pres-timeseries-card-header span,
    .pres-timeseries-card-header h3,
    .pres-timeseries-card-header strong,
    .pres-timeseries-card-header div {
      color: #ffffff !important;
    }

    .pres-timeseries-card .pres-timeseries-chart-box {
      margin: 1rem;
      height: calc(100% - 5.4rem);
      min-height: 260px;
    }

    .pres-explorer-side .pres-mini-stat,
    .pres-segment-summary-item {
      border-top: 3px solid var(--pres-academic) !important;
    }

    .pres-explorer-side .pres-mini-stat:nth-child(2),
    .pres-segment-summary-item:nth-child(2) {
      border-top-color: var(--pres-cyan) !important;
    }

    .pres-explorer-side .pres-mini-stat:nth-child(3),
    .pres-segment-summary-item:nth-child(3) {
      border-top-color: var(--pres-gold) !important;
    }

    .pres-explorer-side .pres-mini-stat:nth-child(4),
    .pres-segment-summary-item:nth-child(4) {
      border-top-color: var(--pres-accent) !important;
    }

    @media (min-width: 1600px) {
      .apple-slide {
        max-width: 1760px !important;
        max-height: 860px !important;
      }

      .pres-cover-card {
        min-height: 160px !important;
      }
    }

    @media (max-width: 1024px) {
      .pres-top-bar,
      .pres-bottom-bar {
        left: 0.85rem !important;
        right: 0.85rem !important;
      }

      .pres-cover-layout,
      .pres-explorer-grid {
        grid-template-columns: 1fr !important;
      }

      .pres-risk-composition-layout,
      .pres-risk-metric-grid {
        grid-template-columns: 1fr !important;
      }

      .pres-cover-board {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
      }
    }

    @media (max-width: 640px) {
      .pres-cover-board,
      .pres-digital-list {
        grid-template-columns: 1fr !important;
      }

      .pres-title-lbl {
        font-size: 0.9rem !important;
      }
    }

    /* BRI corporate template skin */
    :root {
      --pres-ink: #0b1116;
      --pres-ink-soft: #2f3a44;
      --pres-muted: #5f6f7c;
      --pres-paper: #ffffff;
      --pres-page: #ffffff;
      --pres-line: #d7e5f6;
      --pres-academic: #0857c3;
      --pres-academic-dark: #06469c;
      --pres-blue: #307fe2;
      --pres-cyan: #71c5e8;
      --pres-gold: #ccad95;
      --pres-shadow: 0 10px 26px rgba(8, 87, 195, 0.10);
      --pres-shadow-soft: 0 6px 16px rgba(8, 87, 195, 0.07);
    }

    body,
    html {
      background: #ffffff !important;
      color: var(--pres-ink) !important;
      font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
    }

    .apple-presentation-mode {
      background:
        linear-gradient(90deg, rgba(8, 87, 195, 0.035) 0 1px, transparent 1px 100%),
        linear-gradient(180deg, #ffffff 0%, #ffffff 72%, #f3f8ff 100%) !important;
      background-size: 100% 100%, auto !important;
      padding: 5.15rem 3.15rem 4.55rem !important;
      font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
    }

    .pres-slides-container::before {
      display: block !important;
      content: '' !important;
      position: absolute !important;
      top: 50% !important;
      left: 50% !important;
      width: calc(100% + 1.2rem) !important;
      height: calc(100% + 1.1rem) !important;
      max-width: min(1810px, calc(100vw - 1rem)) !important;
      max-height: min(875px, calc(100vh - 3.5rem)) !important;
      transform: translate(-50%, -50%) !important;
      border: 7px solid rgba(8, 87, 195, 0.12) !important;
      border-radius: 28px !important;
      filter: none !important;
      opacity: 1 !important;
      background: transparent !important;
      pointer-events: none !important;
      z-index: 1 !important;
      animation: none !important;
      box-sizing: border-box !important;
    }

    @keyframes float-horizontal-box {
      0% {
        transform: translate(-51.2%, -50%) scale(0.996);
      }
      50% {
        transform: translate(-48.8%, -50%) scale(1.004) rotate(0.04deg);
      }
      100% {
        transform: translate(-51.2%, -50%) scale(0.996);
      }
    }

    .apple-presentation-mode::after {
      display: block !important;
      content: '' !important;
      position: absolute !important;
      left: 3.15rem !important;
      right: 3.15rem !important;
      top: 4.35rem !important;
      height: 1px !important;
      background: var(--pres-academic) !important;
      border-radius: 0 !important;
      filter: none !important;
      opacity: 0.75 !important;
      pointer-events: none !important;
      z-index: 1 !important;
    }

    .apple-slide h1,
    .apple-slide h2,
    .apple-slide h3,
    .pres-title-lbl,
    .pres-meta-chip,
    .pres-nav-btn-back,
    .pres-table-dense,
    .pres-compact-select,
    .pres-toggle-btn {
      font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
    }

    .pres-top-bar,
    .pres-bottom-bar {
      background: transparent !important;
      border: 0 !important;
      box-shadow: none !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
      left: 3.15rem !important;
      right: 3.15rem !important;
    }

    .pres-top-bar {
      top: 0.92rem !important;
      padding: 0 !important;
    }

    .pres-bottom-bar {
      bottom: 0.82rem !important;
      padding-top: 0.7rem !important;
      border-top: 1px solid rgba(8, 87, 195, 0.22) !important;
    }

    .pres-logos-wrapper,
    .pres-cover-logos {
      display: inline-flex !important;
      align-items: center !important;
      background: rgba(255, 255, 255, 0.92) !important;
      border: 1px solid rgba(8, 87, 195, 0.18) !important;
      border-radius: 12px !important;
      padding: 0.4rem 0.8rem !important;
      gap: 0.5rem !important;
      box-shadow: 0 6px 16px rgba(8, 87, 195, 0.04) !important;
    }

    .pres-logo-divider,
    .pres-cover-logo-divider {
      display: inline-block !important;
      width: 1.5px !important;
      height: 18px !important;
      background: rgba(8, 87, 195, 0.22) !important;
      margin: 0 0.4rem !important;
      align-self: center;
    }

    .pres-logo-brand.logo-danantara,
    .pres-cover-logos .logo-danantara {
      height: 28px !important;
    }

    .pres-logo-brand.logo-bri,
    .pres-cover-logos .logo-bri {
      height: 24px !important;
      width: auto !important;
    }

    .pres-title-lbl {
      color: var(--pres-academic) !important;
      font-size: 1rem !important;
      font-weight: 900 !important;
    }

    .pres-title-lbl span {
      color: #4b5563 !important;
      font-size: 0.88rem !important;
      font-weight: 500 !important;
      text-transform: none !important;
      letter-spacing: 0 !important;
    }

    .pres-meta-chip,
    .pres-nav-btn-back,
    .pres-nav-btn,
    .pres-control-group,
    .pres-compact-select,
    .pres-slide-counter-badge {
      border: 1px solid rgba(8, 87, 195, 0.22) !important;
      border-radius: 10px !important;
      background: #ffffff !important;
      box-shadow: 0 4px 12px rgba(8, 87, 195, 0.03) !important;
    }

    .pres-cover-layout {
      grid-template-columns: minmax(330px, 0.86fr) minmax(610px, 1.34fr) !important;
      align-items: stretch !important;
      gap: 1.35rem !important;
    }

    .pres-cover-lead.pres-glass-card {
      background: transparent !important;
      border: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      padding: 0 1.25rem 0 0 !important;
    }

    .pres-cover-title {
      color: var(--pres-academic) !important;
      font-size: clamp(2.35rem, 3.45vw, 4rem) !important;
      line-height: 1.03 !important;
      font-weight: 900 !important;
      padding-bottom: 0.62rem !important;
      border-bottom: 2px solid rgba(8, 87, 195, 0.72) !important;
    }

    .pres-cover-eyebrow,
    .pres-section-eyebrow {
      color: var(--pres-academic) !important;
      font-size: 0.78rem !important;
      letter-spacing: 0.08em !important;
      font-weight: 800 !important;
      padding-left: 0 !important;
    }

    .pres-cover-eyebrow::before,
    .pres-section-eyebrow::before {
      display: none !important;
    }

    .pres-cover-subtitle {
      color: #374151 !important;
      font-size: 1rem !important;
      line-height: 1.5 !important;
    }

    .pres-cover-strip {
      gap: 0.65rem !important;
    }

    .pres-cover-stat,
    .pres-mini-stat,
    .pres-segment-summary-item,
    .pres-rka-strip > div {
      border: 1px solid rgba(8, 87, 195, 0.18) !important;
      border-radius: 12px !important;
      background: #ffffff !important;
      box-shadow: 0 4px 12px rgba(8, 87, 195, 0.02) !important;
    }

    .pres-glass-card {
      border: 1.5px solid rgba(8, 87, 195, 0.18) !important;
      border-radius: 18px !important;
      background: #ffffff !important;
      box-shadow: 0 12px 30px rgba(8, 87, 195, 0.05) !important;
    }

    .pres-cover-card {
      border-top: 4px solid var(--pres-academic) !important;
    }

    .pres-panel-header,
    .pres-timeseries-card-header {
      background: var(--pres-academic) !important;
      border-top-left-radius: 17px !important;
      border-top-right-radius: 17px !important;
      border-bottom-left-radius: 0 !important;
      border-bottom-right-radius: 0 !important;
    }

    .pres-panel-header.pres-panel-header-cyan,
    .pres-timeseries-card:nth-child(2) .pres-timeseries-card-header {
      background: var(--pres-blue) !important;
      border-top-left-radius: 17px !important;
      border-top-right-radius: 17px !important;
      border-bottom-left-radius: 0 !important;
      border-bottom-right-radius: 0 !important;
    }

    .pres-panel-header.pres-panel-header-orange {
      background: var(--pres-gold) !important;
      color: var(--pres-ink) !important;
      border-top-left-radius: 17px !important;
      border-top-right-radius: 17px !important;
      border-bottom-left-radius: 0 !important;
      border-bottom-right-radius: 0 !important;
    }

    .pres-table-dense thead th {
      background: var(--pres-academic) !important;
      color: #ffffff !important;
      border-bottom-color: var(--pres-academic) !important;
    }

    .pres-table-dense tbody tr:nth-child(even) td {
      background: #f3f8ff !important;
    }

    .pres-kpi-huge-number,
    .pres-text-gradient-blue {
      color: var(--pres-academic) !important;
    }

    .pres-splash-accent-btn,
    .pres-toggle-btn.active,
    .pres-dot.active {
      background: var(--pres-academic) !important;
      border-color: var(--pres-academic) !important;
    }

    .pres-executive-grid,
    .pres-executive-scan,
    .pres-executive-edge {
      position: absolute;
      pointer-events: none;
      z-index: 0;
    }

    .pres-executive-grid {
      inset: 5.25rem 3.15rem 4.35rem 3.15rem;
      background-image:
        linear-gradient(rgba(8, 87, 195, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(8, 87, 195, 0.06) 1px, transparent 1px);
      background-size: 64px 64px;
      mask-image: linear-gradient(90deg, transparent 0%, #000 16%, #000 84%, transparent 100%);
      opacity: 0.46;
      animation: pres-grid-drift 34s linear infinite;
    }

    .pres-executive-scan {
      left: 3.15rem;
      right: 3.15rem;
      top: 4.35rem;
      height: 3px;
      background: linear-gradient(90deg, transparent, rgba(0, 174, 239, 0.9), rgba(247, 159, 31, 0.75), transparent);
      box-shadow: 0 0 22px rgba(0, 174, 239, 0.32);
      transform-origin: left center;
      animation: pres-scan-run 7.5s ease-in-out infinite;
    }

    .pres-executive-edge {
      top: 4.35rem;
      bottom: 4.35rem;
      width: 3px;
      background: linear-gradient(180deg, rgba(8, 87, 195, 0), rgba(8, 87, 195, 0.72), rgba(247, 159, 31, 0.65), rgba(8, 87, 195, 0));
      opacity: 0.7;
    }

    .pres-executive-edge.left {
      left: 3.15rem;
    }

    .pres-executive-edge.right {
      right: 3.15rem;
    }

    .pres-command-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      min-height: 34px;
      padding: 0.38rem 0.72rem;
      border: 1px solid rgba(8, 87, 195, 0.22);
      border-radius: 10px;
      background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(243,248,255,0.96));
      color: #0857c3;
      font-size: 0.72rem;
      font-weight: 850;
      letter-spacing: 0.02em;
      white-space: nowrap;
      box-shadow: 0 8px 18px rgba(8, 87, 195, 0.05);
    }

    .pres-command-chip i {
      color: #f59e0b;
      font-size: 0.72rem;
    }

    .pres-live-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.48rem;
      min-height: 34px;
      padding: 0.36rem 0.72rem;
      border: 1px solid rgba(5, 150, 105, 0.22);
      border-radius: 10px;
      background: #ffffff;
      color: #047857;
      font-size: 0.72rem;
      font-weight: 850;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.04);
    }

    .pres-live-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: #10b981;
      box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45);
      animation: pres-live-pulse 1.7s ease-out infinite;
    }

    .pres-autoplay-panel {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      min-width: 210px;
    }

    .pres-autoplay-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border: 1px solid rgba(8, 87, 195, 0.22);
      border-radius: 10px;
      background: #ffffff;
      color: #0857c3;
      box-shadow: 0 4px 12px rgba(8, 87, 195, 0.03);
      transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
    }

    .pres-autoplay-btn:hover {
      transform: translateY(-1px);
    }

    .pres-autoplay-btn.is-running {
      background: var(--pres-academic);
      color: #ffffff;
    }

    .pres-auto-meta {
      display: grid;
      gap: 0.28rem;
      flex: 1;
      min-width: 0;
    }

    .pres-auto-label {
      display: flex;
      justify-content: space-between;
      gap: 0.8rem;
      color: #64748b;
      font-size: 0.66rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .pres-auto-progress {
      height: 5px;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(8, 87, 195, 0.12);
    }

    .pres-auto-progress-fill {
      display: block;
      height: 100%;
      width: 0%;
      border-radius: inherit;
      background: linear-gradient(90deg, #0857c3, #00aeef, #f59e0b);
    }

    .pres-auto-progress-fill.is-running {
      animation: pres-autoplay-fill var(--pres-autoplay-duration, 9000ms) linear forwards;
    }

    .pres-cover-command-strip {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .pres-cover-lead::after {
      content: '';
      display: block;
      width: min(420px, 100%);
      height: 4px;
      margin-top: 1rem;
      border-radius: 999px;
      background: linear-gradient(90deg, #0857c3, #00aeef, #f59e0b);
      transform-origin: left center;
      animation: pres-accent-breathe 4.8s ease-in-out infinite;
    }

    .apple-slide.active .pres-glass-card,
    .apple-slide.active .pres-cover-card,
    .apple-slide.active .pres-panel {
      animation: pres-panel-rise 0.86s cubic-bezier(.19, 1, .22, 1) both;
    }

    @keyframes pres-grid-drift {
      0% { background-position: 0 0; }
      100% { background-position: 128px 64px; }
    }

    @keyframes pres-scan-run {
      0%, 100% { transform: scaleX(0.04); opacity: 0.18; }
      42% { transform: scaleX(1); opacity: 0.8; }
      58% { transform: scaleX(1); opacity: 0.56; }
    }

    @keyframes pres-live-pulse {
      0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.42); }
      100% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); }
    }

    @keyframes pres-autoplay-fill {
      from { width: 0%; }
      to { width: 100%; }
    }

    @keyframes pres-accent-breathe {
      0%, 100% { transform: scaleX(0.72); opacity: 0.62; }
      50% { transform: scaleX(1); opacity: 1; }
    }

    @keyframes pres-panel-rise {
      from { opacity: 0; transform: translateY(16px) scale(0.992); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    @media (max-width: 1024px) {
      .apple-presentation-mode {
        padding-left: 1.15rem !important;
        padding-right: 1.15rem !important;
      }

      .apple-presentation-mode::after {
        left: 1.15rem !important;
        right: 1.15rem !important;
      }

      .pres-top-bar,
      .pres-bottom-bar {
        left: 1.15rem !important;
        right: 1.15rem !important;
      }

      .pres-executive-grid,
      .pres-executive-scan {
        left: 1.15rem !important;
        right: 1.15rem !important;
      }

      .pres-executive-edge {
        display: none;
      }

      .pres-live-chip,
      .pres-command-chip {
        display: none;
      }

      .pres-autoplay-panel {
        min-width: 150px;
      }
    }

    /* ==========================================================================
       PPT MODE MASSIVE REDESIGN OVERRIDES (EXECUTIVE BANKING MASTERPIECE)
       ========================================================================= */

    /* 1. Dotted Grid Background Pattern & Pulse Animations */
    #apple-presentation-mode {
      background:
        radial-gradient(rgba(8, 87, 195, 0.08) 1.2px, transparent 1.2px),
        linear-gradient(180deg, #ffffff 0%, #f7f9fc 50%, #e8eff7 100%) !important;
      background-size: 24px 24px, auto !important;
      position: relative;
      overflow: hidden;
      transition: background 0.8s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    /* 2. Theme Dark Overrides for Opening (Cover) & Closing Slides */
    #apple-presentation-mode.theme-dark {
      background:
        radial-gradient(rgba(255, 255, 255, 0.04) 1.2px, transparent 1.2px),
        linear-gradient(135deg, #0A2540 0%, #001020 100%) !important;
      background-size: 24px 24px, auto !important;
      color: #ffffff !important;
    }

    #apple-presentation-mode.theme-dark::before {
      content: '';
      position: absolute;
      width: 800px;
      height: 800px;
      background: radial-gradient(circle, rgba(8, 87, 195, 0.18) 0%, transparent 70%);
      top: -200px;
      left: -200px;
      z-index: 1;
      pointer-events: none;
      display: block !important;
      animation: ambient-pulse-1 20s infinite alternate ease-in-out;
    }

    #apple-presentation-mode.theme-dark::after {
      content: '';
      position: absolute;
      width: 900px;
      height: 900px;
      background: radial-gradient(circle, rgba(139, 92, 246, 0.12) 0%, transparent 70%);
      bottom: -300px;
      right: -300px;
      z-index: 1;
      pointer-events: none;
      display: block !important;
      animation: ambient-pulse-2 25s infinite alternate ease-in-out;
    }

    /* Adjustments for top & bottom bars in dark theme */
    #apple-presentation-mode.theme-dark .pres-top-bar,
    #apple-presentation-mode.theme-dark .pres-bottom-bar {
      background: rgba(10, 37, 64, 0.82) !important;
      border-color: rgba(255, 255, 255, 0.12) !important;
      color: #ffffff !important;
      box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45) !important;
    }

    #apple-presentation-mode.theme-dark .pres-title-lbl,
    #apple-presentation-mode.theme-dark .pres-meta-chip,
    #apple-presentation-mode.theme-dark .pres-nav-btn-back,
    #apple-presentation-mode.theme-dark .pres-nav-btn,
    #apple-presentation-mode.theme-dark .pres-paginator,
    #apple-presentation-mode.theme-dark .pres-slide-counter-badge,
    #apple-presentation-mode.theme-dark .pres-autoplay-panel {
      color: #ffffff !important;
      background: rgba(255, 255, 255, 0.08) !important;
      border-color: rgba(255, 255, 255, 0.14) !important;
      box-shadow: none !important;
    }

    #apple-presentation-mode.theme-dark .pres-title-lbl span {
      color: #3b82f6 !important;
    }

    #apple-presentation-mode.theme-dark .pres-logo-divider {
      background: rgba(255, 255, 255, 0.25) !important;
    }

    #apple-presentation-mode.theme-dark .pres-logos-wrapper {
      background: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.85) !important;
      box-shadow: 0 14px 34px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
    }

    #apple-presentation-mode.theme-dark .pres-logos-wrapper .pres-logo-divider {
      background: rgba(8, 87, 195, 0.2) !important;
    }

    #apple-presentation-mode.theme-dark .pres-dot {
      background: rgba(255, 255, 255, 0.3) !important;
    }

    #apple-presentation-mode.theme-dark .pres-dot.active {
      background: #0071e3 !important;
      box-shadow: 0 0 12px rgba(0, 113, 227, 0.6);
    }

    #apple-presentation-mode.theme-dark .pres-autoplay-btn {
      color: #ffffff !important;
    }

    #apple-presentation-mode.theme-dark .pres-nav-btn-back:hover,
    #apple-presentation-mode.theme-dark .pres-nav-btn:hover {
      background: rgba(255, 255, 255, 0.16) !important;
      border-color: rgba(255, 255, 255, 0.3) !important;
      color: #3b82f6 !important;
    }

    #apple-presentation-mode.theme-dark .pres-auto-label span {
      color: rgba(255, 255, 255, 0.6) !important;
    }
    #apple-presentation-mode.theme-dark #pres-auto-state {
      color: #ffffff !important;
    }

    #apple-presentation-mode.theme-dark .pres-auto-progress {
      background: rgba(255, 255, 255, 0.15) !important;
    }

    /* 3. Framed Image Styling */
    .pres-cover-image-container {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      width: 100%;
      min-height: 0;
      padding: 0.5rem;
    }

    .pres-cover-image-frame {
      width: 100%;
      height: 100%;
      max-height: 480px;
      object-fit: cover;
      border-radius: 24px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
      transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    }

    #pres-slide-0 .pres-cover-image-frame {
      border: 8px solid #0857c3;
      box-shadow: 0 20px 40px rgba(8, 87, 195, 0.25);
    }

    #pres-slide-8 .pres-cover-image-frame {
      border: 8px solid #ffffff;
      box-shadow: 0 20px 40px rgba(255, 255, 255, 0.15);
    }

    .pres-cover-image-frame:hover {
      transform: translateY(-8px) scale(1.025);
    }

    #pres-slide-0 .pres-cover-image-frame:hover {
      box-shadow: 0 30px 60px rgba(8, 87, 195, 0.45);
    }

    #pres-slide-8 .pres-cover-image-frame:hover {
      box-shadow: 0 30px 60px rgba(255, 255, 255, 0.3);
    }

    /* 4. Timeline Progress Bar (Self-Moving Autoplay) */
    .pres-auto-progress {
      position: relative;
      height: 4px;
      background: rgba(0, 0, 0, 0.08);
      border-radius: 2px;
      overflow: hidden;
      width: 60px;
    }

    .pres-auto-progress-fill {
      position: absolute;
      top: 0;
      left: 0;
      height: 100%;
      width: 0%;
      background: #0071e3;
      border-radius: 2px;
      transition: none;
    }

    .pres-auto-progress-fill.is-running {
      width: 100%;
      transition: width var(--pres-autoplay-duration) linear !important;
    }

    #apple-presentation-mode.theme-dark .pres-auto-progress-fill {
      background: #3b82f6 !important;
    }

    /* 5. Custom 130 Tahun BRI Badge styles */
    .bri-130-badge {
      transition: transform 0.3s ease;
    }
    .bri-130-badge:hover {
      transform: scale(1.06);
    }
    #apple-presentation-mode.theme-dark .bri-130-badge text {
      fill: #0857c3 !important;
    }
    #apple-presentation-mode.theme-dark .bri-130-badge text:last-of-type {
      fill: #e61c24 !important;
    }
    #apple-presentation-mode.theme-dark .bri-130-badge path[stroke="#0857c3"] {
      stroke: #0857c3 !important;
    }

    /* 6. Tableau Table Style Polishing */
    .pres-table-dense {
      background: #ffffff;
    }

    .pres-table-dense thead th {
      background: #f1f5f9 !important;
      border-bottom: 2px solid #cbd5e1 !important;
      font-weight: 800 !important;
      color: #334155 !important;
      font-size: 0.68rem !important;
      padding: 0.65rem 0.8rem !important;
      text-transform: uppercase;
      letter-spacing: 0.04em !important;
    }

    .pres-table-dense tbody td {
      border-bottom: 1px solid #e2e8f0 !important;
      padding: 0.6rem 0.8rem !important;
      color: #1e293b !important;
      font-weight: 500;
    }

    .pres-table-dense tbody tr:nth-child(even) td {
      background: #f8fafc !important;
    }

    .pres-table-dense tbody tr:hover td {
      background: rgba(8, 87, 195, 0.06) !important;
    }

    /* Double underline for total rows */
    .pres-table-dense tbody tr[style*="background"] td,
    .pres-table-dense tbody tr.pres-kts-branch-header td {
      border-bottom: 3px double #0857c3 !important;
      font-weight: 800 !important;
    }

    /* 7. Strategic Priorities Slide Cards */
    .pres-priority-card {
      background: #ffffff;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 16px;
      padding: 1.8rem;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.03);
      display: flex;
      gap: 1.5rem;
      align-items: flex-start;
      transition: all 0.3s ease;
    }

    .pres-priority-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 35px rgba(8, 87, 195, 0.08);
      border-color: rgba(8, 87, 195, 0.25);
    }

    .pres-priority-icon-wrapper {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      font-weight: 800;
      flex-shrink: 0;
    }

    .pres-priority-icon-wrapper.casa {
      background: rgba(8, 87, 195, 0.1);
      color: #0857c3;
      border: 1px solid rgba(8, 87, 195, 0.2);
    }

    .pres-priority-icon-wrapper.quality {
      background: rgba(16, 185, 129, 0.1);
      color: #047857;
      border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .pres-priority-icon-wrapper.prod {
      background: rgba(245, 158, 11, 0.1);
      color: #b45309;
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    /* 8. Slide transition speed improvement */
    .apple-slide {
      transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.5s !important;
    }

    /* 16-page BRI executive deck system */
    .bri-deck-title-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .bri-deck-kicker {
      color: #0857c3;
      font-size: 0.78rem;
      font-weight: 900;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }

    .bri-deck-title {
      margin: 0.2rem 0 0;
      color: #0f172a;
      font-size: clamp(1.7rem, 2.45vw, 2.65rem);
      font-weight: 900;
      line-height: 1.05;
    }

    .bri-deck-subtitle {
      margin: 0.5rem 0 0;
      color: #475569;
      font-size: 0.88rem;
      font-weight: 600;
      line-height: 1.45;
      max-width: 760px;
    }

    .bri-cover-hero {
      position: relative;
      display: grid;
      grid-template-columns: minmax(360px, 0.82fr) minmax(560px, 1.18fr);
      gap: 1.35rem;
      height: 100%;
      align-items: stretch;
    }

    .bri-cover-copy {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 2.2rem;
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(239,246,255,0.92));
      border: 1px solid rgba(255, 255, 255, 0.55);
      box-shadow: 0 24px 58px rgba(0, 0, 0, 0.18);
    }

    .bri-cover-photo {
      position: relative;
      overflow: hidden;
      border-radius: 22px;
      border: 8px solid #ffffff;
      box-shadow: 0 28px 70px rgba(0, 0, 0, 0.26);
      background: #0857c3;
    }

    .bri-cover-photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transform: scale(1.01);
    }

    .bri-cover-photo::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(8, 87, 195, 0.14), transparent 38%, rgba(230, 28, 36, 0.08));
      pointer-events: none;
    }

    .bri-cover-title {
      margin: 0.45rem 0 0;
      color: #0857c3;
      font-size: clamp(2.6rem, 4.7vw, 5.4rem);
      font-weight: 950;
      line-height: 0.94;
    }

    .bri-cover-meta {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.65rem;
      margin-top: 1.2rem;
    }

    .bri-cover-meta .pres-cover-stat {
      min-height: 72px;
    }

    .bri-highlight-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.85rem;
    }

    .bri-dashboard-card {
      position: relative;
      overflow: hidden;
      border-radius: 12px;
      border: 1px solid rgba(8, 87, 195, 0.14);
      background: #ffffff;
      box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
    }

    .bri-dashboard-card .card-head {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 38px;
      padding: 0.55rem 0.85rem;
      color: #ffffff;
      background: linear-gradient(90deg, #0857c3, #307fe2);
      font-size: 0.76rem;
      font-weight: 900;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .bri-dashboard-card .card-head.cyan {
      background: linear-gradient(90deg, #307fe2, #00aeef);
    }

    .bri-dashboard-card .card-head.red {
      background: linear-gradient(90deg, #0857c3, #ef4444);
    }

    .bri-dashboard-card .metric-main {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.4rem;
      padding: 0.9rem 1rem 0.65rem;
      text-align: center;
    }

    .bri-dashboard-card .metric-main span,
    .bri-dashboard-card .metric-deltas span {
      display: block;
      color: #475569;
      font-size: 0.67rem;
      font-weight: 800;
    }

    .bri-dashboard-card .metric-main strong {
      display: block;
      margin-top: 0.18rem;
      color: #0f172a;
      font-size: 1.23rem;
      font-weight: 950;
      line-height: 1;
    }

    .bri-dashboard-card .achievement {
      padding: 0.35rem 1rem 0.75rem;
      text-align: center;
      border-top: 1px solid rgba(15, 23, 42, 0.07);
    }

    .bri-dashboard-card .achievement strong {
      display: block;
      color: #f59e0b;
      font-size: 1.55rem;
      font-weight: 950;
      line-height: 1;
    }

    .bri-dashboard-card .metric-deltas {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0;
      padding: 0.65rem 0.85rem 0.85rem;
      border-top: 1px solid rgba(15, 23, 42, 0.07);
      text-align: center;
    }

    .bri-dashboard-card .metric-deltas strong {
      color: #059669;
      font-size: 0.76rem;
      font-weight: 900;
    }

    .bri-dashboard-card .metric-deltas strong.neg {
      color: #dc2626;
    }

    .bri-panel-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
      gap: 0.9rem;
      margin-top: 0.9rem;
      min-height: 0;
    }

    .bri-blue-panel-title {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      min-height: 34px;
      padding: 0.48rem 0.8rem;
      color: #ffffff;
      background: linear-gradient(90deg, #0857c3, #307fe2);
      border-radius: 10px 10px 0 0;
      font-size: 0.76rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .bri-inner-panel {
      overflow: hidden;
      border: 1px solid rgba(8, 87, 195, 0.14);
      border-radius: 11px;
      background: #ffffff;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }

    .bri-panel-body {
      padding: 0.8rem;
    }

    .bri-product-row,
    .bri-branch-card,
    .bri-finance-card {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 10px;
      background: #ffffff;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    }

    .bri-product-row {
      display: grid;
      grid-template-columns: 140px repeat(3, minmax(0, 1fr));
      gap: 0.75rem;
      align-items: center;
      padding: 0.55rem 0.7rem;
      margin-bottom: 0.42rem;
    }

    .bri-raised-bars {
      display: flex;
      align-items: end;
      gap: 1rem;
      height: 310px;
      padding: 1.2rem 1.4rem 0.75rem;
      perspective: 900px;
    }

    .bri-raised-bar {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      gap: 0.5rem;
      height: 100%;
    }

    .bri-raised-bar .bar {
      width: 76%;
      min-height: 18px;
      border-radius: 10px 10px 4px 4px;
      background: linear-gradient(180deg, var(--bar-color), #073b8e);
      box-shadow: inset -10px 0 18px rgba(255,255,255,0.16), 10px 12px 20px rgba(8,87,195,0.18);
      transform: rotateX(7deg) rotateY(-8deg);
      transform-origin: bottom center;
    }

    .bri-raised-bar strong {
      color: #0f172a;
      font-size: 0.78rem;
      font-weight: 900;
      text-align: center;
    }

    .bri-raised-bar span {
      color: #64748b;
      font-size: 0.68rem;
      font-weight: 800;
      text-align: center;
    }

    .bri-saving-highlight-slide {
      display: flex;
      flex-direction: column;
      overflow: hidden;
      gap: 0.72rem;
    }

    .bri-saving-highlight-slide .bri-deck-title-row {
      flex: 0 0 auto;
    }

    .bri-saving-highlight-slide .bri-highlight-grid {
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 0.58rem;
      flex: 0 0 auto;
    }

    .bri-saving-highlight-slide .bri-dashboard-card .card-head {
      min-height: 32px;
      padding: 0.42rem 0.55rem;
      font-size: 0.66rem;
    }

    .bri-saving-highlight-slide .bri-dashboard-card .metric-main {
      padding: 0.58rem 0.62rem 0.42rem;
    }

    .bri-saving-highlight-slide .bri-dashboard-card .metric-main strong {
      font-size: 1rem;
      line-height: 1.12;
    }

    .bri-saving-highlight-slide .bri-dashboard-card .achievement {
      padding: 0.36rem 0.58rem 0.54rem;
    }

    .bri-saving-highlight-slide .bri-dashboard-card .achievement strong {
      font-size: 1.2rem;
      line-height: 1.05;
    }

    .bri-saving-highlight-slide .bri-panel-grid {
      flex: 1 1 auto;
      min-height: 0;
      margin-top: 0;
    }

    .bri-saving-highlight-slide .bri-inner-panel {
      display: flex;
      flex-direction: column;
      min-height: 0;
    }

    .bri-saving-highlight-slide .bri-panel-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow: hidden;
    }

    .bri-saving-highlight-slide #pres-saving-summary-table {
      overflow: auto;
    }

    .bri-raised-bars {
      display: grid;
      grid-template-columns: 1fr;
      align-content: center;
      gap: 0.72rem;
      height: 100%;
      min-height: 0;
      padding: 0.9rem 0.95rem;
      perspective: none;
    }

    .bri-raised-bar {
      display: grid;
      grid-template-columns: minmax(115px, 0.72fr) minmax(170px, 1.25fr) minmax(52px, auto);
      align-items: center;
      gap: 0.8rem;
      min-height: 58px;
      height: auto;
      padding: 0.78rem 0.88rem;
      border: 1px solid rgba(8, 87, 195, 0.12);
      border-radius: 14px;
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96)),
        radial-gradient(circle at 10% 0%, rgba(8, 87, 195, 0.12), transparent 34%);
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    }

    .bri-raised-bar .bar-meta {
      display: grid;
      gap: 0.18rem;
      min-width: 0;
    }

    .bri-raised-bar strong {
      color: #0f172a;
      font-size: 0.82rem;
      font-weight: 950;
      line-height: 1.12;
      text-align: left;
      white-space: normal;
    }

    .bri-raised-bar span {
      color: #64748b;
      font-size: 0.68rem;
      font-weight: 850;
      line-height: 1.2;
      text-align: left;
      white-space: normal;
    }

    .bri-raised-bar .bar-track {
      height: 18px;
      overflow: hidden;
      border-radius: 999px;
      background: #e5edf7;
      box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.09);
    }

    .bri-raised-bar .bar {
      width: var(--bar-width, 0%);
      height: 100% !important;
      min-height: 0;
      border-radius: inherit;
      background: linear-gradient(90deg, var(--bar-color), #307fe2);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.35), 0 8px 16px rgba(8, 87, 195, 0.16);
      transform: none;
    }

    .bri-raised-bar em {
      justify-self: end;
      color: var(--bar-color, #0857c3);
      font-size: 0.9rem;
      font-style: normal;
      font-weight: 950;
      white-space: nowrap;
    }

    .bri-donut-lite {
      --p1: 70%;
      --p2: 15%;
      --p3: 15%;
      width: 205px;
      height: 205px;
      border-radius: 50%;
      background: conic-gradient(#0857c3 0 var(--p1), #71c5e8 var(--p1) calc(var(--p1) + var(--p2)), #ef4444 calc(var(--p1) + var(--p2)) 100%);
      box-shadow: 0 18px 36px rgba(8,87,195,0.18);
      position: relative;
      margin: 0 auto;
    }

    .bri-donut-lite::after {
      content: '';
      position: absolute;
      inset: 48px;
      border-radius: inherit;
      background: #ffffff;
      box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06);
    }

    .bri-mini-stat-row {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.65rem;
    }

    .bri-branch-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.75rem;
    }

    .bri-branch-card {
      padding: 0.9rem;
    }

    .bri-branch-card h3,
    .bri-finance-card h3 {
      margin: 0;
      color: #0f172a;
      font-size: 0.95rem;
      font-weight: 900;
    }

    .bri-branch-card .metric-line,
    .bri-finance-card .metric-line {
      display: flex;
      justify-content: space-between;
      gap: 0.65rem;
      margin-top: 0.55rem;
      color: #475569;
      font-size: 0.72rem;
      font-weight: 800;
    }

    .bri-finance-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.72rem;
    }

    .bri-finance-card {
      padding: 0.9rem;
      border-top: 4px solid var(--tone, #0857c3);
    }

    .bri-finance-card strong {
      display: block;
      margin-top: 0.55rem;
      color: var(--tone, #0857c3);
      font-size: 1.25rem;
      font-weight: 950;
    }

    .bri-deep-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.9rem;
      min-height: 0;
    }

    @media (max-width: 1100px) {
      .bri-cover-hero,
      .bri-panel-grid,
      .bri-deep-grid {
        grid-template-columns: 1fr;
      }

      .bri-highlight-grid,
      .bri-branch-grid,
      .bri-finance-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    /* Presentation QC fit layer: keep every slide inside the framed viewport. */
    #apple-presentation-mode {
      --pres-stage-x: clamp(1.15rem, 2.15vw, 2.65rem);
      --pres-stage-top: clamp(4.35rem, 7.2vh, 5.05rem);
      --pres-stage-bottom: clamp(4.65rem, 7.8vh, 5.2rem);
      padding: var(--pres-stage-top) var(--pres-stage-x) var(--pres-stage-bottom) !important;
    }

    .pres-slides-container {
      width: 100% !important;
      height: calc(100vh - var(--pres-stage-top) - var(--pres-stage-bottom)) !important;
      max-width: min(100%, 1840px) !important;
      margin: 0 auto !important;
      overflow: visible !important;
      align-items: center !important;
      justify-content: center !important;
    }

    .pres-slides-container::before {
      top: 50% !important;
      left: 50% !important;
      width: calc(100% - 0.35rem) !important;
      height: calc(100% - 0.35rem) !important;
      max-width: none !important;
      max-height: none !important;
      transform: translate(-50%, -50%) !important;
      border-width: 6px !important;
      border-radius: 24px !important;
    }

    .apple-presentation-mode::after {
      left: var(--pres-stage-x) !important;
      right: var(--pres-stage-x) !important;
      top: calc(var(--pres-stage-top) - 0.72rem) !important;
    }

    .pres-executive-grid {
      inset: var(--pres-stage-top) var(--pres-stage-x) var(--pres-stage-bottom) !important;
    }

    .pres-executive-scan {
      left: var(--pres-stage-x) !important;
      right: var(--pres-stage-x) !important;
      top: calc(var(--pres-stage-top) - 0.72rem) !important;
    }

    .pres-executive-edge {
      top: calc(var(--pres-stage-top) - 0.72rem) !important;
      bottom: calc(var(--pres-stage-bottom) - 0.72rem) !important;
    }

    .pres-executive-edge.left {
      left: var(--pres-stage-x) !important;
    }

    .pres-executive-edge.right {
      right: var(--pres-stage-x) !important;
    }

    .pres-top-bar,
    .pres-bottom-bar {
      left: var(--pres-stage-x) !important;
      right: var(--pres-stage-x) !important;
    }

    .apple-slide {
      width: calc(100% - 4.35rem) !important;
      height: calc(100% - 1.15rem) !important;
      max-width: none !important;
      max-height: none !important;
      padding: 0.72rem 0.92rem !important;
      justify-content: flex-start !important;
      overflow: hidden !important;
      gap: 0.62rem;
    }

    .apple-slide.active {
      transform: translateX(0) scale(1) !important;
    }

    .apple-slide.prev {
      transform: translateX(-42px) scale(0.985) !important;
    }

    .bri-cover-hero {
      height: 100% !important;
      min-height: 0 !important;
      gap: 1rem !important;
    }

    .bri-cover-copy {
      min-height: 0 !important;
      overflow: hidden !important;
      padding: clamp(1.25rem, 2.25vh, 1.8rem) !important;
    }

    .bri-cover-title {
      font-size: clamp(2.15rem, 4.25vw, 4.55rem) !important;
      line-height: 0.96 !important;
    }

    .bri-cover-meta {
      gap: 0.52rem !important;
      margin-top: 0.86rem !important;
    }

    .bri-cover-meta .pres-cover-stat {
      min-height: 60px !important;
    }

    #pres-slide-0 .bri-cover-copy > div:last-child,
    #pres-slide-8 .bri-cover-copy > div:last-child {
      margin-top: 0.75rem !important;
      align-items: center !important;
    }

    #pres-slide-0 .pres-splash-accent-btn,
    #pres-slide-8 .pres-splash-accent-btn {
      min-height: 38px !important;
      padding: 0.62rem 1.22rem !important;
      font-size: 0.88rem !important;
      line-height: 1.08 !important;
    }

    .bri-deck-title-row {
      flex: 0 0 auto;
      margin-bottom: 0.52rem !important;
    }

    .bri-deck-title {
      font-size: clamp(1.55rem, 2.12vw, 2.32rem) !important;
      line-height: 1.02 !important;
    }

    .bri-deck-subtitle {
      margin-top: 0.35rem !important;
      font-size: 0.8rem !important;
      line-height: 1.35 !important;
    }

    .bri-highlight-grid {
      gap: 0.62rem !important;
      flex: 0 0 auto;
    }

    .bri-dashboard-card .card-head {
      min-height: 30px !important;
      padding: 0.38rem 0.65rem !important;
      font-size: 0.66rem !important;
    }

    .bri-dashboard-card .metric-main {
      padding: 0.58rem 0.82rem 0.42rem !important;
      gap: 0.3rem !important;
    }

    .bri-dashboard-card .metric-main span,
    .bri-dashboard-card .metric-deltas span {
      font-size: 0.6rem !important;
    }

    .bri-dashboard-card .metric-main strong {
      font-size: clamp(0.96rem, 1.18vw, 1.18rem) !important;
    }

    .bri-dashboard-card .achievement {
      padding: 0.28rem 0.78rem 0.48rem !important;
    }

    .bri-dashboard-card .achievement strong {
      font-size: clamp(1.08rem, 1.65vw, 1.4rem) !important;
    }

    .bri-dashboard-card .metric-deltas {
      padding: 0.48rem 0.72rem 0.58rem !important;
    }

    .bri-dashboard-card .metric-deltas strong {
      font-size: 0.66rem !important;
      overflow-wrap: anywhere;
    }

    .bri-panel-grid,
    .bri-deep-grid,
    .pres-grid-2col,
    .pres-kts-grid,
    .pres-digital-layout {
      flex: 1 1 auto;
      min-height: 0 !important;
      margin-top: 0.48rem !important;
    }

    .bri-inner-panel,
    .pres-glass-card,
    .pres-panel-card,
    .pres-timeseries-card {
      min-height: 0;
    }

    .bri-inner-panel {
      display: flex;
      flex-direction: column;
    }

    .bri-panel-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow: hidden;
      padding: 0.62rem !important;
    }

    .bri-product-row {
      grid-template-columns: minmax(120px, 0.58fr) repeat(3, minmax(0, 1fr)) !important;
      gap: 0.55rem !important;
      padding: 0.42rem 0.55rem !important;
      margin-bottom: 0.32rem !important;
    }

    .bri-donut-lite {
      width: clamp(150px, 12vw, 190px) !important;
      height: clamp(150px, 12vw, 190px) !important;
    }

    .bri-donut-lite::after {
      inset: clamp(36px, 3vw, 46px) !important;
    }

    #pres-slide-1 .bri-highlight-grid {
      max-height: 232px;
    }

    #pres-slide-1 .bri-panel-grid {
      flex: 1 1 0;
    }

    #pres-slide-1 .pres-table-scroll {
      max-height: min(136px, 18vh) !important;
      overflow: auto !important;
    }

    #pres-slide-1 #pres-loan-composition-summary {
      margin-top: 0.48rem !important;
      font-size: 0.78rem;
      line-height: 1.16;
    }

    #pres-slide-3 .bri-inner-panel,
    #pres-slide-4 .bri-panel-grid,
    #pres-slide-6 .bri-panel-grid,
    #pres-slide-10 .pres-segment-panel,
    #pres-slide-15 .pres-digital-layout {
      height: auto !important;
      flex: 1 1 auto !important;
      min-height: 0 !important;
      max-height: none !important;
    }

    #pres-slide-3 .bri-panel-body {
      height: auto !important;
    }

    #pres-slide-5 .pres-timeseries-grid,
    #pres-slide-9 .pres-explorer-grid,
    #pres-slide-12 .bri-deep-grid,
    #pres-slide-13 .pres-kts-grid,
    #pres-slide-14 .pres-digital-layout,
    #pres-slide-15 .pres-digital-layout {
      height: auto !important;
      flex: 1 1 auto !important;
      min-height: 0 !important;
    }

    #pres-slide-5 .pres-timeseries-chart-box,
    #pres-slide-9 .pres-chart-wrap,
    #pres-slide-15 .pres-chart-wrap {
      height: auto !important;
      min-height: 0 !important;
    }

    .pres-table-scroll {
      min-height: 0 !important;
      overflow: auto !important;
      scrollbar-width: thin;
    }

    .pres-table-dense th,
    .pres-table-dense td {
      padding: 0.42rem 0.52rem !important;
      font-size: 0.68rem !important;
      line-height: 1.18 !important;
    }

    .bri-branch-grid,
    .bri-finance-grid {
      flex: 1 1 auto;
      min-height: 0;
      align-content: start;
      gap: 0.56rem !important;
      overflow: hidden;
    }

    .bri-branch-card,
    .bri-finance-card {
      padding: 0.68rem !important;
      min-width: 0;
    }

    .bri-branch-card h3,
    .bri-finance-card h3 {
      font-size: 0.82rem !important;
      line-height: 1.15 !important;
    }

    .bri-branch-card .metric-line,
    .bri-finance-card .metric-line {
      margin-top: 0.36rem !important;
      font-size: 0.64rem !important;
      line-height: 1.18 !important;
    }

    .bri-finance-card strong {
      margin-top: 0.35rem !important;
      font-size: 1rem !important;
    }

    .pres-top-bar {
      top: 0.7rem !important;
    }

    .pres-bottom-bar {
      bottom: 0.65rem !important;
      padding-top: 0.46rem !important;
    }

    @media (min-width: 1600px) {
      #apple-presentation-mode {
        --pres-stage-top: 4.65rem;
        --pres-stage-bottom: 4.95rem;
        --pres-stage-x: 2.2rem;
      }
    }
  </style>
</head>
<body>

  <!-- Apple Presentation Mode Container -->
  <div class="apple-presentation-mode active" id="apple-presentation-mode">
    <div class="pres-executive-grid" aria-hidden="true"></div>
    <div class="pres-executive-scan" aria-hidden="true"></div>
    <div class="pres-executive-edge left" aria-hidden="true"></div>
    <div class="pres-executive-edge right" aria-hidden="true"></div>

    <!-- Top Bar -->
    <div class="pres-top-bar">
      <div class="pres-title-brand">
        <!-- Swap: Kembali ke Dashboard button now on the top left -->
        <a href="{{ route('dashboard', ['periode' => $selectedPeriod]) }}" class="pres-nav-btn-back" title="Kembali ke Dashboard Utama" style="margin-right: 0.25rem;">
          <i class="fas fa-arrow-left"></i> <span class="pres-back-text">Kembali ke Dashboard</span>
        </a>
        <div class="pres-logo-divider" style="height: 18px; margin: 0 0.5rem 0 0.75rem; align-self: center;"></div>
        <div class="pres-title-lbl">Kinerja Area 6 <span>- Madiun, Magetan, Ngawi, Ponorogo</span></div>
      </div>
      <div class="pres-controls-right">
        <div class="pres-live-chip" title="Data presentasi aktif dari payload dashboard">
          <span class="pres-live-dot"></span>
          <span id="pres-live-status">Live Dashboard</span>
        </div>
        <!-- co-branding Danantara & BRI appears exactly once on the top right -->
        <div class="pres-logos-wrapper">
          <img class="pres-logo-brand logo-danantara" src="{{ asset('images/danantara-logo-template.png') }}" alt="Danantara">
          <div class="pres-logo-divider"></div>
          <!-- Custom SVG 130 Tahun BRI Badge -->
          <svg class="bri-130-badge" viewBox="0 0 145 40" style="height: 20px; width: auto; display: block; overflow: visible;">
            <path d="M 14 10 L 20 6 L 20 34 M 14 34 L 26 34" fill="none" stroke="#0857c3" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M 32 6 L 48 6 L 40 19 C 46 19 50 23 52 28 C 52 33 48 35 42 35 C 36 35 34 32 34 31" fill="none" stroke="#e61c24" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M 68 6 C 58 6 56 19 56 21 C 58 23 60 35 70 35 C 80 35 82 23 82 21 C 82 19 80 6 70 6 Z" fill="none" stroke="#0857c3" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M 66 21 C 68 17 72 17 74 21 C 76 25 70 29 70 29 C 70 29 64 25 66 21 Z" fill="#e61c24" opacity="0.85"></path>
            <text x="88" y="21" font-family="'Inter', sans-serif" font-weight="900" font-size="12" fill="#0857c3">TAHUN</text>
            <text x="88" y="32" font-family="'Inter', sans-serif" font-weight="900" font-size="9" fill="#e61c24" letter-spacing="0.05em">1895-2025</text>
          </svg>
          <div class="pres-logo-divider"></div>
          <img class="pres-logo-brand logo-bri" src="{{ asset('images/bri-logo-template.png') }}" alt="BRI">
        </div>

        <!-- Sembunyikan tanggal periode selector secara visual agar JS page loader tidak error -->
        <select id="pres-periode-selector" class="pres-date-picker-select" style="display: none;">
          @foreach($periods as $p)
            <option value="{{ $p }}" {{ $p === $selectedPeriod ? 'selected' : '' }}>
              {{ \Carbon\Carbon::parse($p)->translatedFormat('d M Y') }}
            </option>
          @endforeach
        </select>
      </div>
    </div>

    <!-- Slides Viewport -->
    <div class="pres-slides-container">
      @include('presentation._executive-slides')
      @if(false)
      <!-- Slide 1: Welcome Intro -->
      <div class="apple-slide active" id="pres-slide-0">
        <div class="pres-cover-layout">
          <div class="pres-glass-card pres-cover-lead animate-fade-in slide-delay-1">
            <div>
              <div class="pres-cover-eyebrow">Micro Directorate – Micro Sales Management Group</div>
              <h1 class="pres-cover-title" style="color: #0857c3;">Materi Pendukung Asistensi</h1>
              <p class="pres-cover-subtitle" style="font-style: italic; color: #0857c3; font-weight: 700; font-size: 1.25rem; margin-top: 0.35rem; margin-bottom: 1rem;">
                Regional 6 / Madiun
              </p>
              <p style="font-size: 0.85rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem;">
                Evaluasi harian kinerja simpanan, OS non-commercial, kualitas kredit (SML, NPL, LAR), audit Kolek Tidak Sesuai (KTS), dan implementasi 8 strategi produk dana digital.
              </p>
              <div class="pres-cover-strip">
                <div class="pres-cover-stat">
                  <div>
                    <span>Periode</span>
                    <strong id="pres-cover-period">-</strong>
                  </div>
                  <i class="far fa-calendar-alt" style="color: #0857c3; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div>
                    <span>Data Loan</span>
                    <strong id="pres-cover-loan-period">-</strong>
                  </div>
                  <i class="far fa-clock" style="color: #e61c24; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div>
                    <span>Audit KTS</span>
                    <strong id="pres-cover-kts">-</strong>
                  </div>
                  <i class="fas fa-users" style="color: #0857c3; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
                <div class="pres-cover-stat">
                  <div>
                    <span>Digital Strategy</span>
                    <strong id="pres-cover-digital-count">-</strong>
                  </div>
                  <i class="fas fa-bullseye" style="color: #e61c24; font-size: 1.15rem; opacity: 0.85; flex-shrink: 0;"></i>
                </div>
              </div>
            </div>
            <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
              <button type="button" class="pres-splash-accent-btn" id="pres-start-slides-btn" style="margin-top: 0; align-self: flex-start;">
                Masuk ke Deck Kinerja <i class="fas fa-arrow-right"></i>
              </button>
              <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">PT Bank Rakyat Indonesia (Persero) Tbk.</span>
            </div>
          </div>
          <!-- Right side: Widescreen Framed Building Image -->
          <div class="pres-cover-image-container animate-fade-in slide-delay-2">
            <img class="pres-cover-image-frame" src="{{ asset('images/ppt-template/cover-base.png') }}" alt="Gedung Kantor Pusat BRI">
          </div>
          <!-- Hidden container to keep pres-cover-board for JS compatibility -->
          <div id="pres-cover-board" style="display: none;"></div>
        </div>
      </div>

      <!-- Slide 2: Dana Simpanan (DPK) -->
      <div class="apple-slide" id="pres-slide-1">
        <div class="animate-fade-in slide-delay-1">
          <div class="pres-section-eyebrow">Konsol Area 6 & Unit Cabang</div>
          <h2 class="pres-text-gradient-silver" style="font-size:2.05rem; font-weight:850; margin:0.25rem 0 0; line-height:1.12;">
            Kinerja dan Kualitas OS
          </h2>
        </div>
        <div class="pres-control-bar animate-fade-in slide-delay-2">
          <div class="pres-control-group" id="pres-metric-toggle" aria-label="Pilih metrik">
            <button type="button" class="pres-toggle-btn active" data-metric="simpanan">Simpanan</button>
            <button type="button" class="pres-toggle-btn" data-metric="os">OS</button>
            <button type="button" class="pres-toggle-btn" data-metric="sml">SML</button>
            <button type="button" class="pres-toggle-btn" data-metric="npl">NPL</button>
          </div>
          <div style="display:flex; align-items:center; gap:0.45rem;">
            <select class="pres-compact-select" id="pres-scope-select" aria-label="Pilih cabang"></select>
            <div class="pres-control-group" id="pres-view-toggle" aria-label="Pilih tampilan">
              <button type="button" class="pres-toggle-btn active" data-view="table"><i class="fas fa-table"></i></button>
              <button type="button" class="pres-toggle-btn" data-view="chart"><i class="fas fa-chart-line"></i></button>
            </div>
          </div>
        </div>
        <div class="pres-explorer-grid animate-fade-in slide-delay-3">
          <div class="pres-explorer-side">
            <div class="pres-mini-stat-grid">
              <div class="pres-mini-stat">
                <span>Angka terbaru</span>
                <strong id="pres-explorer-latest">-</strong>
              </div>
              <div class="pres-mini-stat">
                <span>Jumlah baris</span>
                <strong id="pres-explorer-count">-</strong>
              </div>
              <div class="pres-mini-stat">
                <span>YtD</span>
                <strong id="pres-explorer-ytd">-</strong>
              </div>
              <div class="pres-mini-stat">
                <span>MtM / MtD</span>
                <strong id="pres-explorer-mtm-mtd">-</strong>
              </div>
            </div>
            <div class="pres-glass-card" style="padding:1rem; min-height:0;">
              <div class="pres-section-eyebrow" style="font-size:0.66rem;">Timeseries Area 6</div>
              <div class="pres-chart-wrap" style="height:270px;">
                <canvas id="pres-explorer-chart"></canvas>
              </div>
            </div>
          </div>
          <div class="pres-glass-card" style="padding:1rem; min-height:0; display:flex; flex-direction:column;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.7rem;">
              <div>
                <div class="pres-section-eyebrow" style="font-size:0.66rem;" id="pres-explorer-caption">Area 6 Konsol</div>
                <h3 id="pres-explorer-title" style="margin:0.18rem 0 0; font-size:1.18rem; font-weight:850; color:#0f172a;">Simpanan</h3>
              </div>
              <div style="font-size:0.72rem; font-weight:750; color:#64748b;" id="pres-explorer-periods">-</div>
            </div>
            <div class="pres-table-scroll" id="pres-explorer-table-wrap">
              <table class="pres-table-dense">
                <thead id="pres-explorer-thead">
                  <tr>
                    <th>Unit/Cabang</th>
                    <th style="text-align:right;">Terbaru</th>
                    <th style="text-align:right;">YtD</th>
                    <th style="text-align:right;">MtM</th>
                    <th style="text-align:right;">MtD</th>
                    <th style="text-align:right;">Rasio</th>
                  </tr>
                </thead>
                <tbody id="pres-explorer-tbody"></tbody>
              </table>
            </div>
            <div class="pres-chart-wrap hidden" id="pres-explorer-bar-wrap" style="height:420px;">
              <canvas id="pres-explorer-bar-chart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 3: Kredit Pinjaman (OS) -->
      <div class="apple-slide" id="pres-slide-2">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          Kinerja OS
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.5rem 0; letter-spacing:-0.02em;">
          Kinerja OS per Segmentasi
        </h2>
        <div class="pres-grid-2col" style="height: calc(100% - 5.2rem); align-items:stretch;">
          <!-- Left: Big Number & KPI -->
          <div class="pres-glass-card animate-fade-in slide-delay-3" style="display:flex; flex-direction:column; justify-content:center;">
            <div style="font-size:0.85rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em;">Total OS Non Commercial</div>
            <div class="pres-text-gradient-blue pres-kpi-huge-number" id="pres-kredit-total-volume">Rp -</div>
            <div>
              <span class="pres-kpi-sub-trend pos" id="pres-kredit-total-trend">
                <i class="fas fa-arrow-up"></i> -% MtM
              </span>
            </div>
            <div class="pres-rka-strip">
              <div>
                <span>RKA OS</span>
                <strong id="pres-kredit-rka">Data belum tersedia</strong>
              </div>
              <div>
                <span>Penc. RKA</span>
                <strong id="pres-kredit-achievement">Data belum tersedia</strong>
              </div>
            </div>

            <!-- Segment summary status bar -->
            <div style="margin-top:1.6rem;">
              <div style="font-size:0.85rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem;">Kontribusi OS 4 Kantor Cabang</div>
              <table class="pres-table-dense" style="font-size:0.8rem;">
                <thead>
                  <tr>
                    <th>Kantor Cabang</th>
                    <th style="text-align:right;">Volume OS</th>
                    <th style="text-align:right;">RKA</th>
                    <th style="text-align:right;">Penc. RKA</th>
                    <th style="text-align:right;">Porsi Area 6</th>
                  </tr>
                </thead>
                <tbody id="pres-kredit-branch-shares-tbody">
                  <!-- Dynamic Branch Shares -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right: Segment performance explorer -->
          <div class="pres-glass-card pres-segment-panel animate-fade-in slide-delay-4" style="padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between; flex-grow:1; min-height:0;">
            <div style="display:flex; flex-direction:column; height:100%; min-height:0;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                <div>
                  <span style="font-size:0.75rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.05em;">OS per Segmen Bisnis</span>
                  <h3 id="pres-seg-explorer-title" style="margin:0.15rem 0 0 0; font-size:1.35rem; font-weight:850; color:#1d1d1f;" class="pres-text-gradient-blue">OS SME</h3>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                  <!-- Segment Toggler -->
                  <div class="pres-control-group" id="pres-seg-metric-toggle" style="margin-right: 0.25rem;">
                    <button type="button" class="pres-toggle-btn active" data-seg-metric="sme_os">SME</button>
                    <button type="button" class="pres-toggle-btn" data-seg-metric="micro_os">Mikro</button>
                    <button type="button" class="pres-toggle-btn" data-seg-metric="consumer_os">Konsumer</button>
                  </div>
                  <!-- Scope Dropdown -->
                  <select class="pres-compact-select" id="pres-seg-scope-select" style="min-width: 145px; font-weight: 700;" aria-label="Pilih cabang"></select>
                </div>
              </div>

              <!-- Subtitle & Comparison periods display -->
              <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.7rem; font-weight:600; color:#64748b; margin-bottom:0.6rem; padding-bottom:0.4rem; border-bottom:1px solid rgba(0,0,0,0.06);">
                <span id="pres-seg-explorer-caption" style="color:#0071e3; font-weight:800;">Area 6 Konsol</span>
                <span id="pres-seg-explorer-periods">-</span>
              </div>

              <div class="pres-segment-summary-grid" id="pres-seg-summary-grid">
                <div class="pres-segment-summary-item">
                  <span>Total Posisi</span>
                  <strong id="pres-seg-total-latest">-</strong>
                </div>
                <div class="pres-segment-summary-item">
                  <span>RKA</span>
                  <strong id="pres-seg-total-rka">-</strong>
                </div>
                <div class="pres-segment-summary-item">
                  <span>Penc. RKA</span>
                  <strong id="pres-seg-total-ach">-</strong>
                </div>
                <div class="pres-segment-summary-item">
                  <span>Jumlah Outlet</span>
                  <strong id="pres-seg-total-outlet">-</strong>
                </div>
              </div>

              <!-- Scrollable Table -->
              <div class="pres-table-scroll" style="overflow-y:auto; flex-grow:1; min-height:0;">
                <table class="pres-table-dense" style="font-size:0.78rem; width:100%; border-collapse: collapse;">
                  <thead>
                    <tr style="background: rgba(0, 113, 227, 0.04);">
                      <th id="pres-seg-first-col-label" style="padding: 0.45rem 0.55rem; border-top-left-radius: 6px; border-bottom-left-radius: 6px;">Outlet</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">Terbaru</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">YtD</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">MtM</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">MtD</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">RKA</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem;">Gap RKA</th>
                      <th style="text-align:right; padding: 0.45rem 0.55rem; border-top-right-radius: 6px; border-bottom-right-radius: 6px;">Penc. RKA</th>
                    </tr>
                  </thead>
                  <tbody id="pres-seg-explorer-tbody"></tbody>
                </table>
              </div>
            </div>
            <div class="pres-seg-footnote" id="pres-seg-footnote">
              <i class="fas fa-info-circle"></i>
              <span>RKA merujuk pada Rencana Kerja Anggaran bulanan berjalan.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 4: Tren Historis (Timeseries) -->
      <div class="apple-slide" id="pres-slide-3">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          Tren Performa Historis Harian
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.25rem 0; letter-spacing:-0.02em;">
          Tren Realisasi Simpanan, OS, SML, dan NPL
        </h2>
        <div class="pres-grid-2col pres-timeseries-grid animate-fade-in slide-delay-3">
          <!-- Left Glass Card: Simpanan vs OS -->
          <div class="pres-glass-card pres-timeseries-card">
            <div class="pres-timeseries-card-header" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size:0.8rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em;">Dana vs Kredit Area 6</span>
                <h3 style="margin:0.25rem 0 0 0; font-size:1.3rem; font-weight:800; color:#1d1d1f;">Realisasi Simpanan vs Pinjaman (OS)</h3>
              </div>
              <div style="font-size:0.8rem; color:rgba(0,0,0,0.5); font-weight:500;">
                Unit: <strong style="color:#0071e3;">Rp Juta</strong>
              </div>
            </div>
            <!-- Chart Canvas Container -->
            <div class="pres-timeseries-chart-box">
              <canvas id="pres-timeseries-chart-dana" style="width:100%; height:100%;"></canvas>
            </div>
          </div>

          <!-- Right Glass Card: OS, SML, NPL -->
          <div class="pres-glass-card pres-timeseries-card">
            <div class="pres-timeseries-card-header" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size:0.8rem; font-weight:600; color:rgba(0,0,0,0.5); text-transform:uppercase; letter-spacing:0.05em;">Kualitas Kredit Area 6</span>
                <h3 style="margin:0.25rem 0 0 0; font-size:1.3rem; font-weight:800; color:#1d1d1f;">Rasio & Tren OS, SML, dan NPL</h3>
              </div>
              <div style="font-size:0.8rem; color:rgba(0,0,0,0.5); font-weight:500;">
                Unit: <strong style="color:#0071e3;">Rp Juta</strong>
              </div>
            </div>
            <!-- Chart Canvas Container -->
            <div class="pres-timeseries-chart-box">
              <canvas id="pres-timeseries-chart-quality" style="width:100%; height:100%;"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 5: LAR & Kualitas Kredit -->
      <div class="apple-slide" id="pres-slide-4">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          SML, NPL & Risk Management
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.25rem 0; letter-spacing:-0.02em;">
          SML dan NPL per Kantor Cabang
        </h2>
        <div class="pres-glass-card pres-panel-card animate-fade-in slide-delay-3" style="margin-bottom:1.05rem;">
          <div class="pres-panel-header pres-panel-header-cyan">
            <span><span class="pres-panel-icon"><i class="fas fa-chart-pie"></i></span> Komposisi Kualitas OS</span>
            <span class="pres-panel-subtitle" id="pres-risk-subtitle">Struktur SML dan NPL Area 6</span>
          </div>
          <div class="pres-panel-body">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:0.85rem;">
              <h3 style="margin:0; font-size:1.35rem; font-weight:900;" class="pres-text-gradient-blue" id="pres-lar-share-title">Portofolio Pinjaman</h3>
              <div style="display:flex; align-items:center; gap:0.45rem;">
                <span style="font-size:0.68rem; font-weight:850; color:#64748b; text-transform:uppercase; letter-spacing:0.04em;">Tampilan</span>
                <select class="pres-compact-select" id="pres-risk-scope-select" style="min-width: 145px; font-weight: 800;" aria-label="Pilih cabang"></select>
              </div>
            </div>
            <div class="pres-risk-composition-layout">
              <div class="pres-donut-shell">
                <div class="pres-donut-canvas-wrapper">
                  <canvas id="pres-risk-composition-chart"></canvas>
                  <div class="pres-donut-center">
                    <div>
                      <strong id="pres-risk-donut-center">-</strong>
                      <span>LAR</span>
                    </div>
                  </div>
                </div>
              </div>
              <div style="min-width:0;">
                <div class="pres-risk-metric-grid">
                  <div class="pres-risk-metric">
                    <span>Lancar</span>
                    <strong style="color:#10b981;" id="pres-lar-healthy-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>Lancar Restruk</span>
                    <strong style="color:#1155c8;" id="pres-lar-restruk-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>SML</span>
                    <strong style="color:#f59e0b;" id="pres-lar-sml-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>NPL</span>
                    <strong style="color:#ef4444;" id="pres-lar-npl-val">-</strong>
                  </div>
                  <div class="pres-risk-metric">
                    <span>LAR</span>
                    <strong style="color:#1155c8;" id="pres-lar-ratio-val">-</strong>
                  </div>
                </div>
                <div class="pres-spectrum-bar" style="margin-top:0.85rem;">
                  <div class="pres-spectrum-segment" id="pres-spectrum-healthy" style="background:#10b981;"></div>
                  <div class="pres-spectrum-segment" id="pres-spectrum-restruk" style="background:#1155c8;"></div>
                  <div class="pres-spectrum-segment" id="pres-spectrum-sml" style="background:#f59e0b;"></div>
                  <div class="pres-spectrum-segment" id="pres-spectrum-npl" style="background:#ef4444;"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Comparative Quality Table (Full Width for Premium UI/UX) -->
        <div class="pres-glass-card pres-panel-card animate-fade-in slide-delay-4" style="overflow-x:auto;">
          <div class="pres-panel-header">
            <span><span class="pres-panel-icon"><i class="fas fa-table"></i></span> Komparasi Komposisi & Rasio</span>
            <span class="pres-panel-subtitle">Cabang Area 6</span>
          </div>
          <div class="pres-panel-body" style="padding:0.95rem;">
          <table class="pres-table-dense" style="font-size:0.82rem; width:100%; border-collapse: collapse;">
            <thead>
              <tr style="background: rgba(0, 113, 227, 0.04);">
                <th style="padding: 0.6rem 0.8rem; border-top-left-radius: 8px; border-bottom-left-radius: 8px; font-weight: 700;">KANTOR CABANG</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">TOTAL OS</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">SML NOMINAL</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">SML RASIO</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">NPL NOMINAL</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">NPL RASIO</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700;">LAR NOMINAL</th>
                <th style="text-align:right; padding: 0.6rem 0.8rem; font-weight: 700; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">LAR RASIO</th>
              </tr>
            </thead>
            <tbody id="pres-quality-comparison-tbody">
              <!-- Dynamic comparative rows populated by JS -->
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- Slide 6: Kolek Tidak Sesuai (KTS) -->
      <div class="apple-slide" id="pres-slide-5">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          KTS Detail & Audit Alert
        </div>
        <div class="animate-fade-in slide-delay-2" style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.9rem;">
          <h2 class="pres-text-gradient-silver" style="font-size:2.05rem; font-weight:850; margin:0; line-height:1.12;">
            KTS Membaik & Memburuk
          </h2>
          <div class="pres-control-bar" style="margin:0;">
            <div class="pres-control-group" id="pres-kts-category-toggle">
              <button type="button" class="pres-toggle-btn active" data-kts-category="membaik">Membaik</button>
              <button type="button" class="pres-toggle-btn" data-kts-category="memburuk">Memburuk</button>
            </div>
            <div class="pres-control-group" id="pres-kts-scope-toggle">
              <button type="button" class="pres-toggle-btn active" data-kts-scope="ritel">Ritel</button>
              <button type="button" class="pres-toggle-btn" data-kts-scope="micro">Micro</button>
            </div>
          </div>
        </div>
        <div class="pres-kts-grid animate-fade-in slide-delay-3">
          <div class="pres-kts-summary">
            <div class="pres-mini-stat">
              <span>Total rekening</span>
              <strong id="pres-kts-total-count">-</strong>
            </div>
            <div class="pres-mini-stat">
              <span>OS terdampak</span>
              <strong id="pres-kts-total-os">-</strong>
            </div>
            <div class="pres-mini-stat">
              <span>Periode</span>
              <strong id="pres-kts-period">-</strong>
            </div>
            <div class="pres-glass-card" style="padding:1rem;">
              <div class="pres-section-eyebrow" style="font-size:0.66rem;">Kategori</div>
              <p id="pres-kts-note" style="margin:0.45rem 0 0; color:#475569; font-size:0.82rem; line-height:1.55;">
                KTS dipisahkan berdasarkan arah selisih kolektibilitas aktual terhadap kolektibilitas seharusnya.
              </p>
            </div>
          </div>
          <div class="pres-glass-card" style="display:flex; flex-direction:column; overflow:hidden; padding:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.65rem;">
              <div>
                <div class="pres-section-eyebrow" style="font-size:0.66rem;" id="pres-kts-caption">KTS Membaik</div>
                <h3 style="margin:0.15rem 0 0; font-size:1.15rem; font-weight:850; color:#0f172a;" id="pres-kts-title">Ritel</h3>
              </div>
            </div>
            <div class="pres-table-scroll" style="max-height:450px;">
              <table class="pres-table-dense">
                <thead>
                  <tr>
                    <th style="width:45px; padding-left:1.25rem;">#</th>
                    <th>Debitur / Rekening</th>
                    <th>Unit Kerja</th>
                    <th style="text-align:center;">Kolek Aktual vs Seharusnya</th>
                    <th style="text-align:right;">Baki Debet</th>
                  </tr>
                </thead>
                <tbody id="pres-kts-tbody">
                  <!-- Dynamic rows -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 7: 8 Strategi Dana & Digital -->
      <div class="apple-slide" id="pres-slide-6">
        <div class="animate-fade-in slide-delay-1" style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:0.85rem;">
          <div>
            <div class="pres-section-eyebrow">8 Strategi Dana & Digital</div>
            <h2 class="pres-text-gradient-silver" style="font-size:2.05rem; font-weight:850; margin:0.25rem 0 0; line-height:1.12;">
              Kinerja Strategi Realtime
            </h2>
          </div>
          <div class="pres-control-group" id="pres-digital-view-toggle">
            <button type="button" class="pres-toggle-btn active" data-digital-view="table"><i class="fas fa-table"></i></button>
            <button type="button" class="pres-toggle-btn" data-digital-view="timeseries"><i class="fas fa-chart-line"></i></button>
          </div>
        </div>
        <div class="pres-digital-layout animate-fade-in slide-delay-2">
          <div class="pres-digital-list" id="pres-digital-cards-grid">
            <!-- 8 cards will be mapped dynamically here -->
          </div>
          <div class="pres-glass-card" style="padding:1rem; min-height:0;">
            <div id="pres-digital-table-wrap" class="pres-table-scroll" style="max-height:560px;">
              <table class="pres-table-dense">
                <thead>
                  <tr>
                    <th>Strategi</th>
                    <th style="text-align:right;">Angka terbaru</th>
                    <th style="text-align:right;">Volume/Pendukung</th>
                    <th style="text-align:right;">Growth</th>
                    <th>Sumber</th>
                  </tr>
                </thead>
                <tbody id="pres-digital-tbody"></tbody>
              </table>
            </div>
            <div id="pres-digital-chart-wrap" class="pres-chart-wrap hidden" style="height:560px;">
              <canvas id="pres-digital-chart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 8: Outlook & Summary -->
      <div class="apple-slide" id="pres-slide-7">
      <!-- Slide 8: Outlook & Strategic Priorities -->
      <div class="apple-slide" id="pres-slide-7">
        <div class="animate-fade-in slide-delay-1" style="font-size:0.9rem; font-weight:700; color:#0071e3; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem;">
          Arahan Eksekutif & Prioritas Kerja
        </div>
        <h2 class="animate-fade-in slide-delay-2 pres-text-gradient-silver" style="font-size:2.2rem; font-weight:800; margin:0 0 1.75rem 0; letter-spacing:-0.02em;">
          Strategi dan Target Kerja Area 6 - Madiun
        </h2>

        <!-- Widescreen Priorities Grid -->
        <div class="pres-priority-grid animate-fade-in slide-delay-3" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; width: 100%;">
          <!-- Priority 1 -->
          <div class="pres-priority-card animate-fade-in slide-delay-3">
            <div class="pres-priority-icon-wrapper casa">1</div>
            <div>
              <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:800; color:#0f172a;">Akselerasi Simpanan Berbiaya Murah (CASA)</h3>
              <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.6;">
                Fokus akuisisi mesin EDC, sebaran QRIS merchant, aktivasi Brimo massal, pengelolaan Rekening Dormant, perluasan Payroll instansi, serta pemanfaatan platform QLola korporasi secara maksimal.
              </p>
            </div>
          </div>

          <!-- Priority 2 -->
          <div class="pres-priority-card animate-fade-in slide-delay-4">
            <div class="pres-priority-icon-wrapper quality">2</div>
            <div>
              <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:800; color:#0f172a;">Penataan Kualitas Kredit Sejak Dini</h3>
              <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.6;">
                Akselerasi pipeline Whitelist Restrukturisasi, penanganan SML agresif, pencegahan dini saldo NPL baru, serta penyelarasan dan perbaikan data audit KTS secara konsisten.
              </p>
            </div>
          </div>

          <!-- Priority 3 -->
          <div class="pres-priority-card animate-fade-in slide-delay-5">
            <div class="pres-priority-icon-wrapper prod">3</div>
            <div>
              <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem; font-weight:800; color:#0f172a;">Penguatan Produktivitas Mantri & RM</h3>
              <p style="margin:0; font-size:0.88rem; color:#475569; line-height:1.6;">
                Evaluasi harian putusan kredit BOH & MBM, peningkatan sebaran digital tools untuk optimalisasi portofolio, serta pembinaan kompetensi Mantri dan RM dalam memitigasi risiko kredit.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 9: Executive Closing (Terima Kasih) -->
      <div class="apple-slide" id="pres-slide-8">
        <div class="pres-cover-layout">
          <div class="pres-glass-card pres-cover-lead animate-fade-in slide-delay-1" style="background: transparent !important; border: none !important; box-shadow: none !important; display: flex; flex-direction: column; justify-content: space-between; padding: 1rem 0 !important; border-top: none !important;">
            <div>
              <div class="pres-cover-eyebrow" style="color: #3b82f6 !important; font-weight: 800;">PT Bank Rakyat Indonesia (Persero) Tbk.</div>
              <h1 class="pres-cover-title" style="color: #ffffff !important; font-size: clamp(2.5rem, 5vw, 4.5rem) !important; margin-top: 1rem; border-bottom: 4px solid #3b82f6; display: inline-block; padding-bottom: 0.5rem; line-height: 1.1;">
                Terima kasih!
              </h1>
              <p class="pres-cover-subtitle" style="color: rgba(255, 255, 255, 0.7) !important; font-size: 1.15rem; line-height: 1.6; margin-top: 1.5rem; max-width: 32rem;">
                Presentasi laporan konsolidasi Kinerja Harian Area 6 telah selesai dirangkum. Seluruh data disajikan secara realtime dari sistem core banking dan dashboard analitik.
              </p>
            </div>
            <div style="margin-top: 2rem;">
              <button type="button" class="pres-splash-accent-btn" id="pres-finish-close-btn" style="background: #e61c24 !important; border-color: #b91c1c !important; box-shadow: 0 10px 25px rgba(230, 28, 36, 0.35) !important;">
                Tutup & Selesai <i class="fas fa-check-circle" style="margin-left: 0.35rem;"></i>
              </button>
            </div>
          </div>
          <!-- Right side: Framed Building Image with White Border -->
          <div class="pres-cover-image-container animate-fade-in slide-delay-2">
            <img class="pres-cover-image-frame" src="{{ asset('images/ppt-template/cover-base.png') }}" alt="Gedung Kantor Pusat BRI">
          </div>
        </div>
      </div>
      @endif
    </div>

    <!-- Bottom Bar -->
    <div class="pres-bottom-bar">
      <div class="pres-nav-buttons-container">
        <button type="button" class="pres-nav-btn" id="pres-prev-btn" title="Slide Sebelumnya (Left Arrow)">
          <i class="fas fa-chevron-left"></i>
        </button>
      </div>

      <!-- Page dots indicator -->
      <div style="display: flex; flex-direction: column; align-items: center; gap: 0.35rem;">
        <div class="pres-paginator" id="pres-paginator-dots">
          @for ($i = 0; $i < 16; $i++)
            <div class="pres-dot {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}"></div>
          @endfor
        </div>
        <div class="pres-slide-counter-badge" id="pres-slide-counter-badge">Slide 1 dari 16</div>
      </div>

      <div class="pres-nav-buttons-container">
        <div class="pres-autoplay-panel" aria-label="Kontrol autoplay presentasi">
          <button type="button" class="pres-autoplay-btn" id="pres-autoplay-btn" title="Putar otomatis">
            <i class="fas fa-play"></i>
          </button>
          <div class="pres-auto-meta">
            <div class="pres-auto-label">
              <span>Autoplay</span>
              <span id="pres-auto-state">OFF</span>
            </div>
            <div class="pres-auto-progress" aria-hidden="true">
              <span class="pres-auto-progress-fill" id="pres-auto-progress-fill"></span>
            </div>
          </div>
        </div>
        <button type="button" class="pres-nav-btn" id="pres-next-btn" title="Slide Selanjutnya (Right Arrow / Spacebar)">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Standalone Loader Overlay -->
  <div class="dashboard-loading-overlay active" id="dashboard-global-loader">
    <div class="loading-spinner-container">
      <div class="loading-ring"></div>
      <div class="loading-ring-inner"></div>
    </div>
    <div class="dashboard-loading-text">Memuat Data Presentasi...</div>

    <!-- Beautiful progress bar container -->
    <div style="width: 280px; height: 6px; background: rgba(0,0,0,0.06); border-radius: 4px; overflow: hidden; margin-top: 1.25rem; border: 1px solid rgba(0,0,0,0.05); position: relative;">
      <div id="loading-progress-bar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #0071e3, #1f8bfd); border-radius: 4px; transition: width 0.4s ease;"></div>
    </div>
    <div id="loading-progress-percent" style="font-size: 0.8rem; font-weight: 700; color: #0071e3; margin-top: 0.4rem;">0%</div>

    <div class="dashboard-loading-sub" id="dashboard-loading-status" style="margin-top: 0.5rem; text-align: center; font-weight: 500; min-height: 18px;">Menyiapkan slide dari data server...</div>
  </div>

  <!-- Dynamic JS Data Mapping -->
  <script>
    document.addEventListener('DOMContentLoaded', async function() {
      // Relocate loader to document body to guarantee perfect centering free of parent transforms
      const globalLoader = document.getElementById('dashboard-global-loader');
      if (globalLoader) {
        document.body.appendChild(globalLoader);
      }

      const presMode = document.getElementById('apple-presentation-mode');
      const presPrevBtn = document.getElementById('pres-prev-btn');
      const presNextBtn = document.getElementById('pres-next-btn');
      const presStartBtn = document.getElementById('pres-start-slides-btn');
      const presFinishBtn = document.getElementById('pres-finish-close-btn');
      const presDots = document.getElementById('pres-paginator-dots');
      const presPeriodeSelector = document.getElementById('pres-periode-selector');
      const presAutoplayBtn = document.getElementById('pres-autoplay-btn');
      const presAutoState = document.getElementById('pres-auto-state');
      const presAutoProgressFill = document.getElementById('pres-auto-progress-fill');
      const presLiveStatus = document.getElementById('pres-live-status');

      let currentSlideIndex = 0;
      const totalSlides = 16;
      const autoplayDelay = 9000;
      let autoplayTimer = null;
      let autoplayEnabled = false;
      let presentationData = null;
      let timeseriesChartDana = null;
      let timeseriesChartQuality = null;
      let performanceChart = null;
      let performanceBarChart = null;
      let riskCompositionChart = null;
      let digitalChart = null;
      let ktsLoadPromise = null;
      const performanceState = { metric: 'simpanan', scope: 'area6', view: 'table' };
      const segmentState = { metric: 'sme_os', scope: 'area6' };
      const riskState = { scope: 'area6' };
      const ktsState = { category: 'membaik', scope: 'ritel' };
      const digitalState = { view: 'table' };

      // Period change handler
      if (presPeriodeSelector) {
        presPeriodeSelector.addEventListener('change', function() {
          document.getElementById('dashboard-global-loader').classList.add('active');
          window.location.href = '?periode=' + this.value;
        });
      }

      // Safe number formatting helper
      const displayValue = (value, fallback = '–') => {
        if (value === null || value === undefined || value === '') return fallback;
        return String(value);
      };

      const escapeHtml = (value) => displayValue(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const formatCurrencyCompact = (value) => {
        const numeric = Number(value || 0);
        const abs = Math.abs(numeric);
        const formatter = new Intl.NumberFormat('id-ID', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });

        if (abs >= 1000000000000) return 'Rp' + formatter.format(numeric / 1000000000000) + ' T';
        if (abs >= 1000000000) return 'Rp' + formatter.format(numeric / 1000000000) + ' M';
        if (abs >= 1000000) return 'Rp' + formatter.format(numeric / 1000000) + ' Jt';
        return 'Rp' + new Intl.NumberFormat('id-ID').format(numeric);
      };

      const formatPercent = (value) => {
        const numeric = Number(value || 0);
        return new Intl.NumberFormat('id-ID', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(numeric) + '%';
      };

      const sumNumeric = (rows, key) => rows.reduce((total, row) => total + Number(row?.[key] || 0), 0);

      const colorForAchievement = (value) => {
        if (!value || value === '-') return '#64748b';
        // Clean and normalize percentage string (supports both id-ID and en-US locales)
        let clean = String(value).replace('%', '').trim();
        if (clean.includes(',') && clean.includes('.')) {
          clean = clean.replace(/\./g, '').replace(',', '.');
        } else if (clean.includes(',')) {
          clean = clean.replace(',', '.');
        }
        const numeric = parseFloat(clean);
        if (Number.isNaN(numeric)) return '#64748b';

        if (numeric >= 100) {
          return '#16a34a'; // Hijau (Premium green for >=100%)
        } else if (numeric >= 90) {
          return '#ca8a04'; // Kuning (Premium gold-yellow for >=90%)
        }
        return '#dc2626';   // Merah (Premium vibrant red for <90%)
      };

      const metricTone = (metric) => ({
        simpanan: '#1155c8',
        os: '#059669',
        sml: '#d97706',
        npl: '#dc2626'
      }[metric] || '#1155c8');

      const metricLabel = (metric) => ({
        simpanan: 'Simpanan',
        os: 'OS',
        sml: 'SML',
        npl: 'NPL'
      }[metric] || 'Simpanan');

      const parseCompactNumber = (value) => {
        const text = String(value || '').replace(/\s/g, '').replace('Rp', '').replace(/\./g, '').replace(',', '.');
        const numeric = parseFloat(text.replace(/[^0-9.-]/g, ''));
        return Number.isFinite(numeric) ? numeric : 0;
      };

      const digitalTrendNumber = (trend) => {
        const numeric = parseFloat(String(trend || '').replace('%', '').replace(/\./g, '').replace(',', '.'));
        return Number.isFinite(numeric) ? numeric : 0;
      };

      const renderCoverMiniSeries = (series) => {
        const points = Array.isArray(series?.points) ? series.points : [];
        const color = /^#[0-9a-f]{6}$/i.test(series?.tone || '') ? series.tone : '#1155c8';
        const values = points.map(point => {
          const numeric = Number(point?.value);
          return Number.isFinite(numeric) ? numeric : null;
        });
        const validValues = values.filter(value => value !== null);

        if (points.length < 4 || validValues.length < 2) {
          return `
            <div class="pres-cover-card-chart" style="position: relative;">
              <div class="pres-mini-series-empty">Data belum tersedia</div>
              <div class="pres-mini-series-labels">
                <span>YtD</span><span>MtM</span><span>MtD</span><span>Posisi</span>
              </div>
            </div>
          `;
        }

        const min = Math.min(...validValues);
        const max = Math.max(...validValues);
        const range = max - min;

        // Add padding margins so circular markers are fully inside the card container bounds
        const coords = values.map((value, index) => {
          if (value === null) return null;
          const x = points.length === 1 ? 50 : 6 + (index / (points.length - 1)) * 88;
          const y = range === 0 ? 40 : 15 + (1 - ((value - min) / range)) * 50;
          return { x, y, point: points[index] };
        });

        const pathPoints = coords
          .filter(Boolean)
          .map(item => `${item.x.toFixed(2)},${item.y.toFixed(2)}`)
          .join(' ');
        const areaCoords = coords.filter(Boolean);
        const areaPath = areaCoords.length >= 2
          ? `M ${areaCoords[0].x.toFixed(2)} 95 L ${areaCoords.map(item => `${item.x.toFixed(2)} ${item.y.toFixed(2)}`).join(' L ')} L ${areaCoords[areaCoords.length - 1].x.toFixed(2)} 95 Z`
          : '';

        // Build premium crisp HTML overlays for dots and labels to keep them HD and avoid SVG aspect ratio stretching
        const overlayItems = coords.filter(Boolean).map(item => {
          const valDisplay = item.point?.display_value || '-';
          let compactVal = valDisplay.replace('Rp', '').replace(' rek', '').replace(' strategi', '');
          return `
            <div style="position: absolute; left: ${item.x}%; top: ${item.y}%; width: 5px; height: 5px; background: #ffffff; border: 1.8px solid ${color}; border-radius: 50%; transform: translate(-50%, -50%); box-shadow: 0 1px 2px rgba(0,0,0,0.15); z-index: 2;"></div>
            <div style="position: absolute; left: ${item.x}%; top: ${item.y}%; transform: translate(-50%, -100%); margin-top: -6px; font-size: 0.6rem; font-weight: 850; color: #1e293b; font-family: 'Inter', sans-serif; white-space: nowrap; z-index: 3; text-shadow: 0 1px 2px #ffffff, 0 -1px 2px #ffffff, 1px 0 2px #ffffff, -1px 0 2px #ffffff;">
              ${escapeHtml(compactVal)}
            </div>
          `;
        }).join('');

        return `
          <div class="pres-cover-card-chart" style="position: relative; min-height: 72px;">
            <svg style="display: block; width: 100%; height: 52px; overflow: visible;" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="${escapeHtml(series?.label || 'Timeseries')}">
              <line x1="0" y1="95" x2="100" y2="95" stroke="rgba(148, 163, 184, 0.2)" stroke-width="1"></line>
              ${areaPath ? `<path d="${areaPath}" fill="${color}" opacity="0.07"></path>` : ''}
              <polyline points="${pathPoints}" fill="none" stroke="${color}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></polyline>
            </svg>
            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 52px; pointer-events: none;">
              ${overlayItems}
            </div>
            <div class="pres-mini-series-labels" style="margin-top: 4px;">
              ${points.map(point => `<span title="${escapeHtml(point?.period_label || '-')}">${escapeHtml(point?.label || '-')}</span>`).join('')}
            </div>
          </div>
        `;
      };

      const setActiveButton = (container, attr, value) => {
        if (!container) return;
        container.querySelectorAll('.pres-toggle-btn').forEach(btn => {
          btn.classList.toggle('active', btn.getAttribute(attr) === value);
        });
      };

      const slideStoryLabels = [
        'Executive opener',
        'Loan highlight',
        'Funding highlight',
        'Product performance',
        'Loan composition',
        'Trendline motion',
        'Funding mix',
        'Branch war room',
        'Profitability',
        'Performance vs RKA',
        'Segment explorer',
        'Risk radar',
        'SML deep dive',
        'NPL deep dive',
        'KTS intelligence',
        'Digital closing'
      ];

      const runCountUpAnimationsInSlide = (slideIndex) => {
        const slide = document.getElementById('pres-slide-' + slideIndex);
        if (!slide) return;

        const elements = slide.querySelectorAll('[data-raw-val]');
        elements.forEach(el => {
          const endVal = parseFloat(el.getAttribute('data-raw-val') || '0');
          if (isNaN(endVal)) return;
          const isCurrency = el.getAttribute('data-is-currency') === 'true';
          const isPercent = el.getAttribute('data-is-percent') === 'true';
          const suffix = el.getAttribute('data-suffix') || '';

          let startTimestamp = null;
          const duration = 1200;

          const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const val = progress * endVal;

            if (isCurrency) {
              el.innerHTML = formatCurrencyCompact(val);
            } else if (isPercent) {
              el.innerHTML = val.toFixed(2).replace('.', ',') + '%';
            } else {
              el.innerHTML = Math.floor(val).toLocaleString('id-ID') + suffix;
            }

            if (progress < 1) {
              window.requestAnimationFrame(step);
            }
          };
          window.requestAnimationFrame(step);
        });
      };

      const resetAutoplayProgress = () => {
        if (!presAutoProgressFill) return;
        presAutoProgressFill.classList.remove('is-running');
        presAutoProgressFill.style.width = '0%';
        void presAutoProgressFill.offsetWidth;
        if (autoplayEnabled) {
          presAutoProgressFill.style.setProperty('--pres-autoplay-duration', autoplayDelay + 'ms');
          presAutoProgressFill.classList.add('is-running');
        }
      };

      const updateAutoplayUi = () => {
        if (presAutoplayBtn) {
          presAutoplayBtn.classList.toggle('is-running', autoplayEnabled);
          presAutoplayBtn.setAttribute('title', autoplayEnabled ? 'Jeda autoplay' : 'Putar otomatis');
          presAutoplayBtn.innerHTML = autoplayEnabled ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
        }
        if (presAutoState) {
          presAutoState.textContent = autoplayEnabled ? 'ON' : 'OFF';
        }
        resetAutoplayProgress();
      };

      const scheduleAutoplay = () => {
        if (autoplayTimer !== null) {
          window.clearTimeout(autoplayTimer);
          autoplayTimer = null;
        }
        if (!autoplayEnabled) return;

        autoplayTimer = window.setTimeout(() => {
          const nextIndex = currentSlideIndex >= totalSlides - 1 ? 0 : currentSlideIndex + 1;
          showSlide(nextIndex);
          scheduleAutoplay();
        }, autoplayDelay);
      };

      const startAutoplay = () => {
        autoplayEnabled = true;
        updateAutoplayUi();
        scheduleAutoplay();
      };

      const stopAutoplay = () => {
        autoplayEnabled = false;
        if (autoplayTimer !== null) {
          window.clearTimeout(autoplayTimer);
          autoplayTimer = null;
        }
        updateAutoplayUi();
      };

      const showSlide = (index) => {
        currentSlideIndex = index;
        const slides = document.querySelectorAll('.apple-slide');
        slides.forEach((slide, idx) => {
          slide.classList.remove('active', 'prev');
          if (idx === index) {
            slide.classList.add('active');
          } else if (idx < index) {
            slide.classList.add('prev');
          }
        });

        // Toggle dark theme for cover and closing slides.
        if (presMode) {
          presMode.classList.toggle('theme-dark', index === 0 || index === 15);
        }

        // Run count-up animations on active slide elements
        runCountUpAnimationsInSlide(index);

        // Update dots
        const dots = document.querySelectorAll('.pres-dot');
        dots.forEach((dot, idx) => {
          dot.classList.toggle('active', idx === index);
        });

        // Update slide counter text
        const slideCounter = document.getElementById('pres-slide-counter-badge');
        if (slideCounter) {
          slideCounter.innerText = 'Slide ' + (index + 1) + ' dari ' + totalSlides;
        }
        if (presLiveStatus) {
          presLiveStatus.textContent = slideStoryLabels[index] || 'Live Dashboard';
        }
        if (autoplayEnabled) {
          resetAutoplayProgress();
        }

        // Trigger animations
        if (index === 5 && presentationData) {
          const tsData = presentationData.timeseries || {};
          if (tsData.available && tsData.series) {
            const canvasDana = document.getElementById('pres-timeseries-chart-dana');
            const canvasQuality = document.getElementById('pres-timeseries-chart-quality');

            const simpananSeries = tsData.series.find(s => s.key === 'simpanan_total') || {};
            const osSeries = tsData.series.find(s => s.key === 'os_total') || {};
            const smlSeries = tsData.series.find(s => s.key === 'sml_nominal') || {};
            const nplSeries = tsData.series.find(s => s.key === 'npl_nominal') || {};

            // 1. Render Simpanan vs OS Chart
            if (canvasDana) {
              const ctx = canvasDana.getContext('2d');
              if (timeseriesChartDana) {
                timeseriesChartDana.destroy();
              }

              const blueGradient = ctx.createLinearGradient(0, 0, 0, 300);
              blueGradient.addColorStop(0, 'rgba(0, 113, 227, 0.22)');
              blueGradient.addColorStop(1, 'rgba(0, 113, 227, 0.01)');

              const greenGradient = ctx.createLinearGradient(0, 0, 0, 300);
              greenGradient.addColorStop(0, 'rgba(16, 185, 129, 0.22)');
              greenGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

              timeseriesChartDana = new Chart(ctx, {
                type: 'line',
                data: {
                  labels: tsData.labels || [],
                  datasets: [
                    {
                      label: 'Total Simpanan',
                      data: simpananSeries.values || [],
                      borderColor: '#0071e3',
                      borderWidth: 3,
                      backgroundColor: blueGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#0071e3',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 2,
                      pointRadius: 4,
                      pointHoverRadius: 6
                    },
                    {
                      label: 'Total OS',
                      data: osSeries.values || [],
                      borderColor: '#10b981',
                      borderWidth: 3,
                      backgroundColor: greenGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#10b981',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 2,
                      pointRadius: 4,
                      pointHoverRadius: 6
                    }
                  ]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                    legend: {
                      position: 'top',
                      labels: {
                        font: { family: 'Inter', size: 10, weight: '600' },
                        color: '#1d1d1f',
                        usePointStyle: true,
                        padding: 8
                      }
                    },
                    tooltip: {
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      titleColor: '#1d1d1f',
                      bodyColor: '#1d1d1f',
                      borderColor: 'rgba(0,0,0,0.08)',
                      borderWidth: 1,
                      padding: 10,
                      titleFont: { family: 'Inter', weight: 'bold', size: 11 },
                      bodyFont: { family: 'Inter', size: 11 },
                      callbacks: {
                        label: function(context) {
                          let label = context.dataset.label || '';
                          if (label) label += ': ';
                          if (context.parsed.y !== null) {
                            label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' Juta';
                          }
                          return label;
                        }
                      }
                    }
                  },
                  scales: {
                    x: {
                      grid: { display: false },
                      ticks: {
                        font: { family: 'Inter', size: 8, weight: '500' },
                        color: 'rgba(0,0,0,0.5)'
                      }
                    },
                    y: {
                      grid: { color: 'rgba(0,0,0,0.04)' },
                      ticks: {
                        font: { family: 'Inter', size: 8 },
                        color: 'rgba(0,0,0,0.5)',
                        callback: function(value) {
                          return 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M';
                        }
                      }
                    }
                  }
                }
              });
            }

            // 2. Render OS, SML, NPL Chart (with dual Y axes so SML & NPL trends are clearly readable)
            if (canvasQuality) {
              const ctx = canvasQuality.getContext('2d');
              if (timeseriesChartQuality) {
                timeseriesChartQuality.destroy();
              }

              const greenGradient = ctx.createLinearGradient(0, 0, 0, 300);
              greenGradient.addColorStop(0, 'rgba(16, 185, 129, 0.15)');
              greenGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

              const orangeGradient = ctx.createLinearGradient(0, 0, 0, 300);
              orangeGradient.addColorStop(0, 'rgba(245, 158, 11, 0.15)');
              orangeGradient.addColorStop(1, 'rgba(245, 158, 11, 0.01)');

              const redGradient = ctx.createLinearGradient(0, 0, 0, 300);
              redGradient.addColorStop(0, 'rgba(239, 68, 68, 0.15)');
              redGradient.addColorStop(1, 'rgba(239, 68, 68, 0.01)');

              timeseriesChartQuality = new Chart(ctx, {
                type: 'line',
                data: {
                  labels: tsData.labels || [],
                  datasets: [
                    {
                      label: 'Total OS (Kiri)',
                      data: osSeries.values || [],
                      borderColor: '#10b981',
                      borderWidth: 2.5,
                      backgroundColor: greenGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#10b981',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 1.5,
                      pointRadius: 3.5,
                      pointHoverRadius: 5.5,
                      yAxisID: 'y'
                    },
                    {
                      label: 'Realisasi SML (Kanan)',
                      data: smlSeries.values || [],
                      borderColor: '#f59e0b',
                      borderWidth: 2.5,
                      backgroundColor: orangeGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#f59e0b',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 1.5,
                      pointRadius: 3.5,
                      pointHoverRadius: 5.5,
                      yAxisID: 'y1'
                    },
                    {
                      label: 'Realisasi NPL (Kanan)',
                      data: nplSeries.values || [],
                      borderColor: '#ef4444',
                      borderWidth: 2.5,
                      backgroundColor: redGradient,
                      fill: true,
                      tension: 0.32,
                      pointBackgroundColor: '#ef4444',
                      pointBorderColor: '#ffffff',
                      pointBorderWidth: 1.5,
                      pointRadius: 3.5,
                      pointHoverRadius: 5.5,
                      yAxisID: 'y1'
                    }
                  ]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: {
                    legend: {
                      position: 'top',
                      labels: {
                        font: { family: 'Inter', size: 10, weight: '600' },
                        color: '#1d1d1f',
                        usePointStyle: true,
                        padding: 8
                      }
                    },
                    tooltip: {
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      titleColor: '#1d1d1f',
                      bodyColor: '#1d1d1f',
                      borderColor: 'rgba(0,0,0,0.08)',
                      borderWidth: 1,
                      padding: 10,
                      titleFont: { family: 'Inter', weight: 'bold', size: 11 },
                      bodyFont: { family: 'Inter', size: 11 },
                      callbacks: {
                        label: function(context) {
                          let label = context.dataset.label || '';
                          if (label) label += ': ';
                          if (context.parsed.y !== null) {
                            label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y) + ' Juta';
                          }
                          return label;
                        }
                      }
                    }
                  },
                  scales: {
                    x: {
                      grid: { display: false },
                      ticks: {
                        font: { family: 'Inter', size: 8, weight: '500' },
                        color: 'rgba(0,0,0,0.5)'
                      }
                    },
                    y: {
                      type: 'linear',
                      display: true,
                      position: 'left',
                      grid: { color: 'rgba(0,0,0,0.04)' },
                      ticks: {
                        font: { family: 'Inter', size: 8 },
                        color: 'rgba(0,0,0,0.5)',
                        callback: function(value) {
                          return 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M';
                        }
                      }
                    },
                    y1: {
                      type: 'linear',
                      display: true,
                      position: 'right',
                      grid: { drawOnChartArea: false }, // Only keep grid lines from left axis
                      ticks: {
                        font: { family: 'Inter', size: 8 },
                        color: 'rgba(0,0,0,0.5)',
                        callback: function(value) {
                          return 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M';
                        }
                      }
                    }
                  }
                }
              });
            }
          }
        }

        if (index === 9 && presentationData) {
          renderPerformanceExplorer();
        }

        if (index === 10 && presentationData) {
          renderSegmentExplorer();
        }

        if (index === 11 && presentationData) {
          renderRiskOverview();
        }

        if (index === 14 && presentationData) {
          if (presentationData?.kts?.loading_details) {
            loadPresentationKts();
          }
          renderKts();
        }

        if (index === 15 && presentationData) {
          renderDigitalTableAndChart();
        }
      };

      const populateCover = (data) => {
        document.getElementById('pres-cover-period').textContent = data?.meta?.period_label || '-';
        document.getElementById('pres-cover-loan-period').textContent = data?.meta?.daily_loan_period_label || data?.meta?.loan_period_label || '-';

        const ktsTotal = Number(data?.kts?.ritel_total || 0) + Number(data?.kts?.micro_total || 0);
        const coverKts = document.getElementById('pres-cover-kts');
        coverKts.textContent = data?.kts?.loading_details ? 'Memuat...' : new Intl.NumberFormat('id-ID').format(ktsTotal) + ' rek';
        if (!data?.kts?.loading_details) {
          coverKts.setAttribute('data-raw-val', ktsTotal);
          coverKts.setAttribute('data-suffix', ' rek');
        }

        const digCards = data?.digital_strategy?.cards || [];
        const digCountVal = digCards.filter(card => card.available !== false).length;
        const coverDigital = document.getElementById('pres-cover-digital-count');
        coverDigital.textContent = digCountVal + ' strategi';
        coverDigital.setAttribute('data-raw-val', digCountVal);
        coverDigital.setAttribute('data-suffix', ' strategi');

        const board = document.getElementById('pres-cover-board');
        if (!board) return;
        board.innerHTML = '';

        const summaryCards = data?.summary?.cards || [];
        const cardMap = Object.fromEntries(summaryCards.map(card => [card.key, card]));
        const branches = data?.performance_overview?.branches || [];
        const topSimpanan = branches.slice().sort((a, b) => Number(b.simpanan || 0) - Number(a.simpanan || 0))[0];
        const topOs = branches.slice().sort((a, b) => Number(b.pinjaman || 0) - Number(a.pinjaman || 0))[0];
        const coverSeriesCards = data?.cover_card_timeseries?.cards || {};
        const coverCards = [
          { seriesKey: 'simpanan', label: 'Simpanan', value: cardMap.simpanan?.value || '-', meta: `${cardMap.simpanan?.trend || '0,00%'} MtM`, iconClass: 'fas fa-wallet', iconColor: '#059669', iconBg: 'rgba(5, 150, 105, 0.1)' },
          { seriesKey: 'os', label: 'OS', value: cardMap.os?.value || '-', meta: `${cardMap.os?.trend || '0,00%'} MtM`, iconClass: 'fas fa-coins', iconColor: '#2563eb', iconBg: 'rgba(37, 99, 235, 0.1)' },
          { seriesKey: 'ldr', label: 'LDR', value: cardMap.ldr?.value || '-', meta: cardMap.ldr?.meta || '-', iconClass: 'fas fa-balance-scale', iconColor: '#7c3aed', iconBg: 'rgba(124, 58, 237, 0.1)' },
          { seriesKey: 'sml', label: 'SML', value: cardMap.sml?.value || '-', meta: cardMap.sml?.ratio || '-', iconClass: 'fas fa-exclamation-triangle', iconColor: '#ea580c', iconBg: 'rgba(234, 88, 12, 0.1)' },
          { seriesKey: 'npl', label: 'NPL', value: cardMap.npl?.value || '-', meta: cardMap.npl?.ratio || '-', iconClass: 'fas fa-ban', iconColor: '#dc2626', iconBg: 'rgba(220, 38, 38, 0.1)' },
        ];

        coverCards.forEach((card, index) => {
          const div = document.createElement('div');
          div.className = 'pres-glass-card pres-cover-card';
          if (index < 3) {
            div.classList.add('card-span-2');
          } else {
            div.classList.add('card-span-3');
          }
          div.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
              <div>
                <div class="label">${escapeHtml(card.label)}</div>
                <div class="value">${escapeHtml(card.value)}</div>
              </div>
              <div style="color: ${card.iconColor}; background: ${card.iconBg}; width: 2.2rem; height: 2.2rem; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); transition: transform 0.2s ease-in-out;" class="pres-card-icon"><i class="${card.iconClass}"></i></div>
            </div>
            ${renderCoverMiniSeries(coverSeriesCards[card.seriesKey])}
            <div class="meta">${escapeHtml(card.meta)}</div>
          `;
          board.appendChild(div);
        });
      };

      const populatePerformanceControls = (data) => {
        const select = document.getElementById('pres-scope-select');
        const options = data?.performance_overview?.matrix?.scope_options || [];
        if (!select || !options.length) return;

        select.innerHTML = options.map(option => `
          <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
        `).join('');
        select.value = performanceState.scope;
      };

      const getPerformanceRows = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const rows = matrix?.rows?.[performanceState.scope] || [];
        return rows.slice().sort((a, b) => {
          const metric = performanceState.metric;
          return Number(b?.metrics?.[metric]?.latest_raw || 0) - Number(a?.metrics?.[metric]?.latest_raw || 0);
        });
      };

      const renderPerformanceExplorer = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const rows = getPerformanceRows();
        const metric = performanceState.metric;
        const scopeOption = (matrix.scope_options || []).find(option => option.key === performanceState.scope);
        const metricInfo = matrix.metrics?.[metric] || { label: metricLabel(metric) };
        const tone = metricTone(metric);
        const totals = rows.reduce((acc, row) => {
          const item = row.metrics?.[metric] || {};
          acc.latest += Number(item.latest_raw || 0);
          acc.ytd += Number(item.ytd_raw || 0);
          acc.mtm += Number(item.mtm_raw || 0);
          acc.mtd += Number(item.mtd_raw || 0);
          return acc;
        }, { latest: 0, ytd: 0, mtm: 0, mtd: 0 });

        const latestEl = document.getElementById('pres-explorer-latest');
        latestEl.textContent = formatCurrencyCompact(totals.latest);
        latestEl.setAttribute('data-raw-val', totals.latest);
        latestEl.setAttribute('data-is-currency', 'true');

        const countEl = document.getElementById('pres-explorer-count');
        const suffix = performanceState.scope === 'area6' ? ' cabang' : ' unit';
        countEl.textContent = rows.length + suffix;
        countEl.setAttribute('data-raw-val', rows.length);
        countEl.setAttribute('data-suffix', suffix);

        document.getElementById('pres-explorer-ytd').textContent = (totals.ytd >= 0 ? '+' : '-') + formatCurrencyCompact(Math.abs(totals.ytd));
        document.getElementById('pres-explorer-mtm-mtd').textContent = `${totals.mtm >= 0 ? '+' : '-'}${formatCurrencyCompact(Math.abs(totals.mtm))} / ${totals.mtd >= 0 ? '+' : '-'}${formatCurrencyCompact(Math.abs(totals.mtd))}`;
        document.getElementById('pres-explorer-caption').textContent = scopeOption?.label || 'Area 6 Konsol';
        document.getElementById('pres-explorer-title').textContent = metricInfo.label || metricLabel(metric);

        const periods = matrix.periods || {};
        document.getElementById('pres-explorer-periods').textContent = ['ytd', 'mtm', 'mtd', 'current']
          .map(key => periods[key] ? `${periods[key].label} ${periods[key].display}` : null)
          .filter(Boolean)
          .join(' | ');

        const thead = document.getElementById('pres-explorer-thead');
        const tbody = document.getElementById('pres-explorer-tbody');

        if (metric === 'simpanan' || metric === 'os') {
          thead.innerHTML = `
            <tr>
              <th style="border-top-left-radius: 6px; border-bottom-left-radius: 6px;">Unit/Cabang</th>
              <th style="text-align:right;">Terbaru</th>
              <th style="text-align:right;">YtD</th>
              <th style="text-align:right;">MtM</th>
              <th style="text-align:right;">MtD</th>
              <th style="text-align:right;">RKA</th>
              <th style="text-align:right;">Gap RKA</th>
              <th style="text-align:right; border-top-right-radius: 6px; border-bottom-right-radius: 6px;">Penc. RKA</th>
            </tr>
          `;

          const maxLatest = Math.max(...rows.map(row => Number(row.metrics?.[metric]?.latest_raw || 0)), 0);
          tbody.innerHTML = rows.map(row => {
            const item = row.metrics?.[metric] || {};
            const pencColor = colorForAchievement(item.penc_fmt);
            const barWidth = maxLatest > 0 ? Math.max(5, Math.min(100, (Number(item.latest_raw || 0) / maxLatest) * 100)) : 0;
            return `
              <tr>
                <td style="font-weight:800;">${escapeHtml(row.label || '-')}</td>
                <td style="text-align:right; font-weight:850; color:${tone};">
                  <div class="pres-value-bar">
                    <strong>${escapeHtml(item.latest || '-')}</strong>
                    <span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${barWidth}%; background:${tone};"></span></span>
                  </div>
                </td>
                <td style="text-align:right;"><span class="pres-delta ${item.ytd?.class || ''}">${escapeHtml(item.ytd?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtm?.class || ''}">${escapeHtml(item.mtm?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtd?.class || ''}">${escapeHtml(item.mtd?.value || '-')}</span></td>
                <td style="text-align:right; font-weight:800; color:#475569;">${escapeHtml(item.rka_fmt || '-')}</td>
                <td style="text-align:right;"><span class="pres-delta ${item.gap_class || ''}">${escapeHtml(item.gap_fmt || '-')}</span></td>
                <td style="text-align:right; font-weight:850; color:${pencColor};">${escapeHtml(item.penc_fmt || '-')}</td>
              </tr>
            `;
          }).join('') || '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';
        } else {
          thead.innerHTML = `
            <tr>
              <th style="border-top-left-radius: 6px; border-bottom-left-radius: 6px;">Unit/Cabang</th>
              <th style="text-align:right;">Terbaru</th>
              <th style="text-align:right;">YtD</th>
              <th style="text-align:right;">MtM</th>
              <th style="text-align:right;">MtD</th>
              <th style="text-align:right; border-top-right-radius: 6px; border-bottom-right-radius: 6px;">Rasio</th>
            </tr>
          `;

          const maxLatest = Math.max(...rows.map(row => Number(row.metrics?.[metric]?.latest_raw || 0)), 0);
          tbody.innerHTML = rows.map(row => {
            const item = row.metrics?.[metric] || {};
            const barWidth = maxLatest > 0 ? Math.max(5, Math.min(100, (Number(item.latest_raw || 0) / maxLatest) * 100)) : 0;
            return `
              <tr>
                <td style="font-weight:800;">${escapeHtml(row.label || '-')}</td>
                <td style="text-align:right; font-weight:850; color:${tone};">
                  <div class="pres-value-bar">
                    <strong>${escapeHtml(item.latest || '-')}</strong>
                    <span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${barWidth}%; background:${tone};"></span></span>
                  </div>
                </td>
                <td style="text-align:right;"><span class="pres-delta ${item.ytd?.class || ''}">${escapeHtml(item.ytd?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtm?.class || ''}">${escapeHtml(item.mtm?.value || '-')}</span></td>
                <td style="text-align:right;"><span class="pres-delta ${item.mtd?.class || ''}">${escapeHtml(item.mtd?.value || '-')}</span></td>
                <td style="text-align:right; font-weight:800;">${escapeHtml(item.ratio || '-')}</td>
              </tr>
            `;
          }).join('') || '<tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';
        }

        const labels = ['YtD', 'MtM', 'MtD', 'Posisi'];
        const totalSeries = labels.map((_, idx) => rows.reduce((sum, row) => sum + Number(row.metrics?.[metric]?.series?.[idx] || 0), 0));
        const lineCanvas = document.getElementById('pres-explorer-chart');
        if (lineCanvas) {
          if (performanceChart) performanceChart.destroy();
          performanceChart = new Chart(lineCanvas.getContext('2d'), {
            type: 'line',
            data: {
              labels,
              datasets: [{
                label: metricInfo.label || metricLabel(metric),
                data: totalSeries,
                borderColor: tone,
                backgroundColor: tone + '22',
                fill: true,
                tension: 0.28,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: tone,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b', font: { family: 'Inter', size: 10, weight: '700' } } },
                y: { grid: { color: 'rgba(15,23,42,0.07)' }, ticks: { color: '#64748b', callback: value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M' } }
              }
            }
          });
        }

        const tableWrap = document.getElementById('pres-explorer-table-wrap');
        const barWrap = document.getElementById('pres-explorer-bar-wrap');
        tableWrap.classList.toggle('hidden', performanceState.view !== 'table');
        barWrap.classList.toggle('hidden', performanceState.view !== 'chart');

        if (performanceState.view === 'chart') {
          const barCanvas = document.getElementById('pres-explorer-bar-chart');
          if (performanceBarChart) performanceBarChart.destroy();
          performanceBarChart = new Chart(barCanvas.getContext('2d'), {
            type: 'bar',
            data: {
              labels: rows.slice(0, 12).map(row => row.label),
              datasets: [{
                label: metricInfo.label || metricLabel(metric),
                data: rows.slice(0, 12).map(row => Math.round(Number(row.metrics?.[metric]?.latest_raw || 0) / 1000000)),
                backgroundColor: tone,
                borderRadius: 6
              }]
            },
            options: {
              indexAxis: 'y',
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                x: { grid: { color: 'rgba(15,23,42,0.07)' }, ticks: { color: '#64748b', callback: value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value / 1000) + ' M' } },
                y: { grid: { display: false }, ticks: { color: '#0f172a', font: { family: 'Inter', size: 10, weight: '700' } } }
              }
            }
          });
        }
      };

      const populateSegmentControls = (data) => {
        const select = document.getElementById('pres-seg-scope-select');
        const options = data?.performance_overview?.matrix?.scope_options || [];
        if (!select || !options.length) return;

        select.innerHTML = options.map(option => `
          <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
        `).join('');
        select.value = segmentState.scope;
      };

      const isRetailOutletLabel = (label) => /^(KC|KCP)\b/i.test(String(label || '').trim());
      const isMicroOutletLabel = (label) => /^UNIT\b/i.test(String(label || '').trim());
      const isRetailSegmentMetric = (metric) => metric === 'sme_os' || metric === 'consumer_os';
      const segmentMetricLabel = (metric) => ({
        sme_os: 'OS SME',
        micro_os: 'OS Mikro',
        consumer_os: 'OS Konsumer'
      }[metric] || 'OS Segmen');

      const segmentOutletLabel = (metric) => isRetailSegmentMetric(metric) ? 'KC/KCP' : 'Unit Kerja';
      const segmentFootnote = (metric) => isRetailSegmentMetric(metric)
        ? 'SME dan Konsumer hanya dihitung pada outlet ritel KC/KCP; unit mikro tidak ditampilkan.'
        : 'Mikro ditampilkan pada outlet Unit; KC/KCP hanya dipakai sebagai ringkasan saat scope Area 6.';

      const hasSegmentMetricValue = (row, metric) => {
        const item = row?.metrics?.[metric] || {};
        return ['latest_raw', 'rka_raw', 'ytd_raw', 'mtm_raw', 'mtd_raw']
          .some(key => Math.abs(Number(item?.[key] || 0)) > 0);
      };

      const rowMatchesSegmentMetric = (row, metric, scope) => {
        const label = row?.label || '';
        if (isRetailSegmentMetric(metric)) {
          return row?.type === 'Cabang Konsol' || isRetailOutletLabel(label);
        }

        if (metric === 'micro_os') {
          return scope === 'area6' ? row?.type === 'Cabang Konsol' : isMicroOutletLabel(label);
        }

        return true;
      };

      const getSegmentRows = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const metric = segmentState.metric;
        const rows = matrix?.rows?.[segmentState.scope] || [];
        let filteredRows = rows
          .filter(row => rowMatchesSegmentMetric(row, metric, segmentState.scope))
          .filter(row => hasSegmentMetricValue(row, metric));

        if (!filteredRows.length && isRetailSegmentMetric(metric) && segmentState.scope !== 'area6') {
          const selectedScope = String(segmentState.scope || '').trim().toUpperCase();
          filteredRows = (matrix?.rows?.area6 || [])
            .filter(row => String(row?.label || row?.branch || '').trim().toUpperCase() === selectedScope)
            .filter(row => hasSegmentMetricValue(row, metric));
        }

        return filteredRows.slice().sort((a, b) => {
          return Number(b?.metrics?.[metric]?.latest_raw || 0) - Number(a?.metrics?.[metric]?.latest_raw || 0);
        });
      };

      const renderSegmentExplorer = () => {
        const matrix = presentationData?.performance_overview?.matrix || {};
        const rows = getSegmentRows();
        const metric = segmentState.metric;
        const scopeOption = (matrix.scope_options || []).find(option => option.key === segmentState.scope);
        const tone = '#1155c8';

        const titleEl = document.getElementById('pres-seg-explorer-title');
        if (titleEl) titleEl.textContent = segmentMetricLabel(metric);
        document.getElementById('pres-seg-explorer-caption').textContent = scopeOption?.label || 'Area 6 Konsol';
        const firstColLabel = document.getElementById('pres-seg-first-col-label');
        if (firstColLabel) firstColLabel.textContent = segmentOutletLabel(metric);
        const footnote = document.getElementById('pres-seg-footnote');
        if (footnote) {
          footnote.innerHTML = `<i class="fas fa-info-circle"></i><span>${escapeHtml(segmentFootnote(metric))}</span>`;
        }

        const periods = matrix.periods || {};
        document.getElementById('pres-seg-explorer-periods').textContent = ['ytd', 'mtm', 'mtd', 'current']
          .map(key => periods[key] ? `${periods[key].label} ${periods[key].display}` : null)
          .filter(Boolean)
          .join(' | ');

        const totals = rows.reduce((acc, row) => {
          const item = row.metrics?.[metric] || {};
          acc.latest += Number(item.latest_raw || 0);
          acc.rka += Number(item.rka_raw || 0);
          acc.gap += Number(item.gap_raw || 0);
          return acc;
        }, { latest: 0, rka: 0, gap: 0 });
        const achievement = totals.rka > 0 ? (totals.latest / totals.rka) * 100 : null;
        const achievementText = achievement === null ? '-' : formatPercent(achievement);
        const achievementColor = colorForAchievement(achievementText);
        const latestEl = document.getElementById('pres-seg-total-latest');
        const rkaEl = document.getElementById('pres-seg-total-rka');
        const achEl = document.getElementById('pres-seg-total-ach');
        const outletEl = document.getElementById('pres-seg-total-outlet');
        if (latestEl) latestEl.textContent = formatCurrencyCompact(totals.latest);
        if (rkaEl) rkaEl.textContent = totals.rka > 0 ? formatCurrencyCompact(totals.rka) : '-';
        if (achEl) {
          achEl.textContent = achievementText;
          achEl.style.color = achievementColor;
        }
        if (outletEl) outletEl.textContent = rows.length + (isRetailSegmentMetric(metric) ? ' outlet' : (segmentState.scope === 'area6' ? ' cabang' : ' unit'));

        const tbody = document.getElementById('pres-seg-explorer-tbody');
        if (!tbody) return;

        const maxSegmentLatest = Math.max(...rows.map(row => Number(row.metrics?.[metric]?.latest_raw || 0)), 0);
        tbody.innerHTML = rows.map(row => {
          const item = row.metrics?.[metric] || {};
          const pencColor = colorForAchievement(item.penc_fmt);
          const mutedClass = Number(item.latest_raw || 0) === 0 && Number(item.rka_raw || 0) === 0 ? 'pres-seg-row-muted' : '';
          const metaLabel = row.branch && row.branch !== row.label ? `<div style="margin-top:0.12rem; color:#64748b; font-size:0.64rem; font-weight:750;">${escapeHtml(row.branch)}</div>` : '';
          const barWidth = maxSegmentLatest > 0 ? Math.max(5, Math.min(100, (Number(item.latest_raw || 0) / maxSegmentLatest) * 100)) : 0;
          return `
            <tr class="${mutedClass}">
              <td style="font-weight:800; padding: 0.48rem 0.55rem; color:#1d1d1f;">
                <div style="line-height:1.12;">${escapeHtml(row.label || '-')}</div>
                ${metaLabel}
              </td>
              <td style="text-align:right; padding: 0.48rem 0.55rem; font-weight:850; color:${tone};">
                <div class="pres-value-bar">
                  <strong>${escapeHtml(item.latest || '-')}</strong>
                  <span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${barWidth}%; background:${tone};"></span></span>
                </div>
              </td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.ytd?.class || ''}">${escapeHtml(item.ytd?.value || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.mtm?.class || ''}">${escapeHtml(item.mtm?.value || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.mtd?.class || ''}">${escapeHtml(item.mtd?.value || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem; font-weight:800; color:#475569;">${escapeHtml(item.rka_fmt || '-')}</td>
              <td style="text-align:right; padding: 0.45rem 0.55rem;"><span class="pres-delta ${item.gap_class || ''}">${escapeHtml(item.gap_fmt || '-')}</span></td>
              <td style="text-align:right; padding: 0.45rem 0.55rem; font-weight:850; color:${pencColor};">${escapeHtml(item.penc_fmt || '-')}</td>
            </tr>
          `;
        }).join('') || '<tr><td colspan="8" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';
      };

      const populateRiskControls = (data) => {
        const select = document.getElementById('pres-risk-scope-select');
        if (!select) return;

        const branches = data?.performance_overview?.branches || [];
        const options = [
          { key: 'area6', label: 'Area 6 Konsol' },
          ...branches.map(b => ({ key: b.name.toUpperCase(), label: b.name }))
        ];

        select.innerHTML = options.map(option => `
          <option value="${escapeHtml(option.key)}">${escapeHtml(option.label)}</option>
        `).join('');
        select.value = riskState.scope;
      };

      const renderRiskOverview = () => {
        if (!presentationData) return;
        const branches = presentationData?.performance_overview?.branches || [];
        const composition = presentationData?.performance_overview?.composition || {};

        let healthyPct = 0;
        let restrukPct = 0;
        let smlPct = 0;
        let nplPct = 0;
        let larPct = 0;
        let subtitle = 'Struktur SML dan NPL Area 6';

        if (riskState.scope === 'area6') {
          larPct = composition.os ? (composition.os.raw_pct || 0) : 0;
          smlPct = composition.sml ? (composition.sml.raw_pct || 0) : 0;
          nplPct = composition.npl ? (composition.npl.raw_pct || 0) : 0;
          restrukPct = Math.max(0, larPct - smlPct - nplPct);
          healthyPct = Math.max(0, 100 - larPct);
          subtitle = 'Struktur SML dan NPL Area 6';
        } else {
          const branch = branches.find(b => b.name.toUpperCase() === riskState.scope);
          if (branch) {
            larPct = branch.lar_pct || 0;
            smlPct = branch.sml_pct || 0;
            nplPct = branch.npl_pct || 0;
            restrukPct = branch.restruk_pct || Math.max(0, larPct - smlPct - nplPct);
            healthyPct = Math.max(0, 100 - larPct);
            subtitle = `Struktur SML dan NPL ${branch.name}`;
          }
        }

        document.getElementById('pres-risk-subtitle').textContent = subtitle;

        const healthyValEl = document.getElementById('pres-lar-healthy-val');
        healthyValEl.textContent = `${healthyPct.toFixed(2).replace('.', ',')}%`;
        healthyValEl.setAttribute('data-raw-val', healthyPct);
        healthyValEl.setAttribute('data-is-percent', 'true');

        const restrukValEl = document.getElementById('pres-lar-restruk-val');
        restrukValEl.textContent = `${restrukPct.toFixed(2).replace('.', ',')}%`;
        restrukValEl.setAttribute('data-raw-val', restrukPct);
        restrukValEl.setAttribute('data-is-percent', 'true');

        const smlValEl = document.getElementById('pres-lar-sml-val');
        smlValEl.textContent = `${smlPct.toFixed(2).replace('.', ',')}%`;
        smlValEl.setAttribute('data-raw-val', smlPct);
        smlValEl.setAttribute('data-is-percent', 'true');

        const nplValEl = document.getElementById('pres-lar-npl-val');
        nplValEl.textContent = `${nplPct.toFixed(2).replace('.', ',')}%`;
        nplValEl.setAttribute('data-raw-val', nplPct);
        nplValEl.setAttribute('data-is-percent', 'true');

        const ratioValEl = document.getElementById('pres-lar-ratio-val');
        ratioValEl.textContent = `${larPct.toFixed(2).replace('.', ',')}%`;
        ratioValEl.setAttribute('data-raw-val', larPct);
        ratioValEl.setAttribute('data-is-percent', 'true');

        const healthyEl = document.getElementById('pres-spectrum-healthy');
        const restrukEl = document.getElementById('pres-spectrum-restruk');
        const smlEl = document.getElementById('pres-spectrum-sml');
        const nplEl = document.getElementById('pres-spectrum-npl');

        if (healthyEl) healthyEl.style.width = healthyPct + '%';
        if (restrukEl) restrukEl.style.width = restrukPct + '%';
        if (smlEl) smlEl.style.width = smlPct + '%';
        if (nplEl) nplEl.style.width = nplPct + '%';

        const donutCenter = document.getElementById('pres-risk-donut-center');
        if (donutCenter) {
          donutCenter.textContent = `${larPct.toFixed(2).replace('.', ',')}%`;
          donutCenter.setAttribute('data-raw-val', larPct);
          donutCenter.setAttribute('data-is-percent', 'true');
        }

        const riskCanvas = document.getElementById('pres-risk-composition-chart');
        if (riskCanvas) {
          if (riskCompositionChart) riskCompositionChart.destroy();

          riskCompositionChart = new Chart(riskCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
              labels: ['Lancar', 'Lancar Restruk', 'SML', 'NPL'],
              datasets: [{
                data: [healthyPct, restrukPct, smlPct, nplPct],
                backgroundColor: ['#10b981', '#1155c8', '#f59e0b', '#ef4444'],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 4
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: true,
              cutout: '68%',
              plugins: {
                legend: { display: false },
                tooltip: {
                  backgroundColor: 'rgba(255, 255, 255, 0.96)',
                  titleColor: '#111827',
                  bodyColor: '#111827',
                  borderColor: 'rgba(207, 224, 244, 0.95)',
                  borderWidth: 1,
                  padding: 10,
                  callbacks: {
                    label: context => `${context.label}: ${formatPercent(context.parsed)}`
                  }
                }
              }
            }
          });
        }
      };

      const renderKts = () => {
        if (presentationData?.kts?.loading_details) {
          document.getElementById('pres-kts-total-count').textContent = 'Memuat...';
          document.getElementById('pres-kts-total-os').textContent = '-';
          document.getElementById('pres-kts-period').textContent = presentationData?.kts?.period_label || '-';
          document.getElementById('pres-kts-caption').textContent = ktsState.category === 'membaik' ? 'KTS Membaik' : 'KTS Memburuk';
          document.getElementById('pres-kts-title').textContent = ktsState.scope === 'ritel' ? 'Ritel' : 'Micro';
          document.getElementById('pres-kts-note').textContent = 'Detail KTS sedang dimuat setelah deck presentasi tampil agar mode PPT tidak tertahan.';
          document.getElementById('pres-kts-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">Memuat detail KTS...</td></tr>';
          return;
        }

        const payload = presentationData?.kts?.categories?.[ktsState.category]?.[ktsState.scope] || {};
        const branches = payload.branches || [];
        const countEl = document.getElementById('pres-kts-total-count');
        countEl.textContent = `${payload.total_count || 0} rek`;
        countEl.setAttribute('data-raw-val', payload.total_count || 0);
        countEl.setAttribute('data-suffix', ' rek');

        const osEl = document.getElementById('pres-kts-total-os');
        osEl.textContent = payload.total_os_fmt || formatCurrencyCompact(payload.total_os || 0);
        osEl.setAttribute('data-raw-val', payload.total_os || 0);
        osEl.setAttribute('data-is-currency', 'true');
        document.getElementById('pres-kts-period').textContent = presentationData?.kts?.period_label || '-';
        document.getElementById('pres-kts-caption').textContent = ktsState.category === 'membaik' ? 'KTS Membaik' : 'KTS Memburuk';
        document.getElementById('pres-kts-title').textContent = ktsState.scope === 'ritel' ? 'Ritel' : 'Micro';
        document.getElementById('pres-kts-note').textContent = ktsState.category === 'membaik'
          ? 'Aktual lebih baik dari kolektibilitas seharusnya berdasarkan umur tunggakan.'
          : 'Aktual lebih buruk dari kolektibilitas seharusnya berdasarkan umur tunggakan.';

        const tbody = document.getElementById('pres-kts-tbody');
        if (!branches || branches.length === 0) {
          tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#64748b;">Data belum tersedia</td></tr>';
          return;
        }

        let html = '';
        branches.forEach(branch => {
          // Render Branch Header Row
          html += `
            <tr class="pres-kts-branch-header">
              <td colspan="5">
                <i class="fas fa-university mr-2" style="color:#0071e3;"></i>
                ${escapeHtml(branch.branch_name)}
                <span style="float:right; font-weight:600; font-size:0.75rem; color:#475569;">
                  ${branch.total_count || 0} debitur &bull; ${escapeHtml(branch.total_os_fmt || 'Rp0')}
                </span>
              </td>
            </tr>
          `;

          const debiturs = branch.debiturs || [];
          if (debiturs.length === 0) {
            html += `
              <tr>
                <td colspan="5" style="text-align:center; padding:0.65rem; color:#94a3b8; font-style:italic; background:rgba(248,250,252,0.4);">
                  <i class="fas fa-check-circle text-success mr-1"></i> Tidak ada KTS (0 debitur)
                </td>
              </tr>
            `;
          } else {
            debiturs.forEach(deb => {
              const badgeClass = ktsState.category === 'membaik' ? 'badge-membaik' : 'badge-memburuk';
              html += `
                <tr>
                  <td style="font-weight:850; color:#64748b; padding-left:1.25rem;">${deb.rank}</td>
                  <td>
                    <div style="font-weight:800; color:#0f172a;">${escapeHtml(deb.nama_debitur || '-')}</div>
                    <div style="font-size:0.75rem; color:#64748b; font-family:monospace; font-weight:500;">${escapeHtml(deb.nomor_rekening || '-')}</div>
                  </td>
                  <td>
                    <div style="font-weight:600; color:#475569;">${escapeHtml(deb.unit || '-')}</div>
                  </td>
                  <td style="text-align:center;">
                    <span class="pres-kts-badge ${badgeClass}">
                      Kol ${deb.kolek_aktual} <i class="fas fa-long-arrow-alt-right mx-1"></i> Kol ${deb.kolek_seharusnya}
                    </span>
                  </td>
                  <td style="text-align:right; font-weight:850; color:#0f172a;">${escapeHtml(deb.baki_debet_fmt || 'Rp0')}</td>
                </tr>
              `;
            });
          }
        });

        tbody.innerHTML = html;
      };

      const renderDigitalTableAndChart = () => {
        const cards = presentationData?.digital_strategy?.cards || [];
        const tbody = document.getElementById('pres-digital-tbody');
        tbody.innerHTML = cards.map(card => {
          const trend = card.trend || '-';
          const trendClass = String(trend).includes('-') && card.key !== 'casa' ? 'neg' : 'pos';
          return `
            <tr>
              <td style="font-weight:850;">${escapeHtml(card.title || '-')}</td>
              <td style="text-align:right; font-weight:850;">${escapeHtml(card.current_value || '-')}</td>
              <td style="text-align:right;">${escapeHtml(card.secondary_value || '-')}</td>
              <td style="text-align:right;"><span class="pres-delta ${trendClass}">${escapeHtml(trend)}</span></td>
              <td>${escapeHtml(card.source || '-')}</td>
            </tr>
          `;
        }).join('');

        const tableWrap = document.getElementById('pres-digital-table-wrap');
        const chartWrap = document.getElementById('pres-digital-chart-wrap');
        tableWrap.classList.toggle('hidden', digitalState.view !== 'table');
        chartWrap.classList.toggle('hidden', digitalState.view !== 'timeseries');

        if (digitalState.view === 'timeseries') {
          const canvas = document.getElementById('pres-digital-chart');
          if (digitalChart) digitalChart.destroy();
          digitalChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
              labels: ['Basis', 'Posisi'],
              datasets: cards.map((card, idx) => {
                const current = parseCompactNumber(card.current_value);
                const growth = digitalTrendNumber(card.trend);
                const previous = growth === -100 ? 0 : current / (1 + (growth / 100));
                const colors = ['#1155c8', '#059669', '#f59e0b', '#dc2626', '#2f80ed', '#0f766e', '#64748b', '#2fb8df'];
                return {
                  label: card.title || card.key,
                  data: [Math.max(0, previous), current],
                  borderColor: colors[idx % colors.length],
                  backgroundColor: colors[idx % colors.length] + '1F',
                  tension: 0.25,
                  borderWidth: 2,
                  pointRadius: 3
                };
              })
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { family: 'Inter', size: 10, weight: '700' } } }
              },
              scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b' } },
                y: { grid: { color: 'rgba(15,23,42,0.07)' }, ticks: { color: '#64748b' } }
              }
            }
          });
        }
      };

      const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
      };

      const metricAggregate = (metric) => {
        const rows = presentationData?.performance_overview?.matrix?.rows?.area6 || [];
        return rows.reduce((acc, row) => {
          const item = row?.metrics?.[metric] || {};
          acc.latest += Number(item.latest_raw || 0);
          acc.ytd += Number(item.ytd_raw || 0);
          acc.mtm += Number(item.mtm_raw || 0);
          acc.mtd += Number(item.mtd_raw || 0);
          acc.rka += Number(item.rka_raw || 0);
          return acc;
        }, { latest: 0, ytd: 0, mtm: 0, mtd: 0, rka: 0 });
      };

      const signedCurrency = (value) => {
        const numeric = Number(value || 0);
        const prefix = numeric >= 0 ? '+' : '-';
        return prefix + formatCurrencyCompact(Math.abs(numeric));
      };

      const signedClass = (value) => Number(value || 0) < 0 ? 'neg' : '';

      const simpleBar = (value, max, color = '#0857c3') => {
        const width = max > 0 ? Math.max(5, Math.min(100, (Number(value || 0) / max) * 100)) : 0;
        return `<span class="pres-value-bar-track"><span class="pres-value-bar-fill" style="width:${width}%; background:${color};"></span></span>`;
      };

      const renderExecutiveLoanDashboard = (data) => {
        const summaryCards = data?.summary?.cards || [];
        const cardMap = Object.fromEntries(summaryCards.map(card => [card.key, card]));
        const os = metricAggregate('os');
        const sml = metricAggregate('sml');
        const npl = metricAggregate('npl');
        const osRaw = Number(cardMap.os?.value_raw || os.latest || 0);

        setText('pres-loan-sml-value', cardMap.sml?.value || formatCurrencyCompact(sml.latest));
        setText('pres-loan-sml-ratio', cardMap.sml?.ratio || '-');
        setText('pres-loan-sml-status', cardMap.sml?.ratio || '-');
        setText('pres-loan-npl-value', cardMap.npl?.value || formatCurrencyCompact(npl.latest));
        setText('pres-loan-npl-ratio', cardMap.npl?.ratio || '-');
        setText('pres-loan-npl-status', cardMap.npl?.ratio || '-');

        [
          ['pres-loan-os-ytd', os.ytd],
          ['pres-loan-os-mtm', os.mtm],
          ['pres-loan-os-mtd', os.mtd],
          ['pres-loan-sml-ytd', sml.ytd],
          ['pres-loan-sml-mtm', sml.mtm],
          ['pres-loan-sml-mtd', sml.mtd],
          ['pres-loan-npl-ytd', npl.ytd],
          ['pres-loan-npl-mtm', npl.mtm],
          ['pres-loan-npl-mtd', npl.mtd],
        ].forEach(([id, value]) => {
          const el = document.getElementById(id);
          if (!el) return;
          el.textContent = signedCurrency(value);
          el.classList.toggle('neg', Number(value || 0) < 0);
        });

        renderLoanProducts(data);

        const healthy = Math.max(0, osRaw - Number(cardMap.sml?.value_raw || sml.latest || 0) - Number(cardMap.npl?.value_raw || npl.latest || 0));
        const total = Math.max(1, healthy + Number(cardMap.sml?.value_raw || sml.latest || 0) + Number(cardMap.npl?.value_raw || npl.latest || 0));
        const p1 = (healthy / total) * 100;
        const p2 = (Number(cardMap.sml?.value_raw || sml.latest || 0) / total) * 100;
        const donut = document.getElementById('pres-loan-mini-donut');
        if (donut) {
          donut.style.setProperty('--p1', p1.toFixed(2) + '%');
          donut.style.setProperty('--p2', p2.toFixed(2) + '%');
        }

        const summary = document.getElementById('pres-loan-composition-summary');
        if (summary) {
          summary.innerHTML = `
            <div class="metric-line"><span>OS</span><strong>${escapeHtml(cardMap.os?.value || '-')}</strong></div>
            <div class="metric-line"><span>SML</span><strong>${escapeHtml(cardMap.sml?.value || '-')} (${escapeHtml(cardMap.sml?.ratio || '-')})</strong></div>
            <div class="metric-line"><span>NPL</span><strong>${escapeHtml(cardMap.npl?.value || '-')} (${escapeHtml(cardMap.npl?.ratio || '-')})</strong></div>
          `;
        }

        const donutBig = document.getElementById('pres-loan-composition-donut');
        if (donutBig) {
          donutBig.style.setProperty('--p1', p1.toFixed(2) + '%');
          donutBig.style.setProperty('--p2', p2.toFixed(2) + '%');
        }
        const legend = document.getElementById('pres-loan-composition-legend');
        if (legend) {
          legend.innerHTML = `
            <div class="pres-mini-stat"><span>OS Non Commercial</span><strong>${escapeHtml(cardMap.os?.value || '-')}</strong></div>
            <div class="pres-mini-stat"><span>SML</span><strong>${escapeHtml(cardMap.sml?.value || '-')}</strong></div>
            <div class="pres-mini-stat"><span>NPL</span><strong>${escapeHtml(cardMap.npl?.value || '-')}</strong></div>
            <div class="pres-mini-stat"><span>LDR</span><strong>${escapeHtml(cardMap.ldr?.value || '-')}</strong></div>
          `;
        }
      };

      const renderLoanProducts = (data) => {
        const rows = data?.loan_products?.rows || [];
        const maxOs = Math.max(...rows.map(row => Number(row.os_raw || 0)), 0);
        const rowsHtml = rows.map(row => `
          <div class="bri-product-row">
            <div style="font-weight:900; color:#0f172a;"><i class="${escapeHtml(row.icon || 'fas fa-chart-bar')}" style="color:#0857c3; margin-right:0.35rem;"></i>${escapeHtml(row.label || '-')}</div>
            <div><strong>${escapeHtml(row.os || '-')}</strong>${simpleBar(row.os_raw, maxOs, '#0857c3')}</div>
            <div><strong>${escapeHtml(row.sml || '-')}</strong>${simpleBar(row.sml_raw, Math.max(...rows.map(r => Number(r.sml_raw || 0)), 0), '#71c5e8')}</div>
            <div><strong>${escapeHtml(row.npl || '-')}</strong>${simpleBar(row.npl_raw, Math.max(...rows.map(r => Number(r.npl_raw || 0)), 0), '#ef4444')}</div>
          </div>
        `).join('');

        const compactRows = document.getElementById('pres-loan-product-rows');
        if (compactRows) compactRows.innerHTML = rowsHtml || '<div class="pres-mini-stat"><span>Produk</span><strong>Data belum tersedia</strong></div>';

        const bars = document.getElementById('pres-loan-product-bars');
        if (bars) bars.innerHTML = rowsHtml;

        const table = document.getElementById('pres-loan-product-table');
        if (table) {
          table.innerHTML = rows.map(row => `
            <tr>
              <td style="font-weight:850;">${escapeHtml(row.label || '-')}</td>
              <td style="text-align:right;">${escapeHtml(row.os || '-')}</td>
              <td style="text-align:right;">${escapeHtml(row.sml || '-')} <span style="color:#64748b;">${escapeHtml(row.sml_pct || '')}</span></td>
              <td style="text-align:right;">${escapeHtml(row.npl || '-')} <span style="color:#64748b;">${escapeHtml(row.npl_pct || '')}</span></td>
            </tr>
          `).join('') || '<tr><td colspan="4" style="text-align:center;">Data belum tersedia</td></tr>';
        }
      };

      const renderSavingsDashboard = (data) => {
        const cards = data?.savings_breakdown?.cards || [];
        const grid = document.getElementById('pres-saving-cards');
        if (grid) {
          grid.innerHTML = cards.map(card => `
            <div class="bri-dashboard-card">
              <div class="card-head" style="background:linear-gradient(90deg, ${escapeHtml(card.tone || '#0857c3')}, #307fe2);">${escapeHtml(card.label || '-')}</div>
              <div class="metric-main" style="grid-template-columns:1fr;">
                <div><span>Nominal</span><strong>${escapeHtml(card.value || '-')}</strong></div>
              </div>
              <div class="achievement"><span>Kontribusi</span><strong>${escapeHtml(card.pct || '-')}</strong></div>
            </div>
          `).join('');
        }

        const barItems = cards.filter(card => card.key !== 'total_simpanan');
        const barHtml = barItems.map(card => {
          const pct = Math.max(0, Math.min(100, Number(card.pct_raw || 0)));
          return `
          <div class="bri-raised-bar">
            <div class="bar-meta">
              <strong>${escapeHtml(card.label || '-')}</strong>
              <span>${escapeHtml(card.value || '-')}</span>
            </div>
            <div class="bar-track">
              <div class="bar" style="--bar-width:${pct.toFixed(2)}%; --bar-color:${escapeHtml(card.tone || '#0857c3')};"></div>
            </div>
            <em>${escapeHtml(card.pct || '-')}</em>
          </div>
        `;
        }).join('');
        ['pres-saving-bar-stage', 'pres-saving-composition-bars'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.innerHTML = barHtml || '<div class="pres-mini-stat"><span>Komposisi</span><strong>Data belum tersedia</strong></div>';
        });

        const tableHtml = `
          <table class="pres-table-dense">
            <thead><tr><th>Komponen</th><th style="text-align:right;">Nominal</th><th style="text-align:right;">Share</th></tr></thead>
            <tbody>
              ${cards.map(card => `<tr><td style="font-weight:850;">${escapeHtml(card.label || '-')}</td><td style="text-align:right;">${escapeHtml(card.value || '-')}</td><td style="text-align:right;">${escapeHtml(card.pct || '-')}</td></tr>`).join('')}
            </tbody>
          </table>
        `;
        ['pres-saving-summary-table', 'pres-saving-composition-table'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.innerHTML = tableHtml;
        });
      };

      const renderBranchWarRoom = (data) => {
        const branches = data?.performance_overview?.branches || [];
        const grid = document.getElementById('pres-branch-war-room');
        if (!grid) return;
        grid.innerHTML = branches.map(branch => `
          <div class="bri-branch-card">
            <h3>${escapeHtml(branch.name || '-')}</h3>
            <div class="metric-line"><span>Simpanan</span><strong>${escapeHtml(branch.simpanan_fmt || '-')}</strong></div>
            <div class="metric-line"><span>OS</span><strong>${escapeHtml(branch.pinjaman_fmt || '-')}</strong></div>
            <div class="metric-line"><span>Penc. RKA OS</span><strong>${escapeHtml(branch.pinjaman_share_fmt || '-')}</strong></div>
            <div class="metric-line"><span>SML</span><strong>${escapeHtml(branch.sml_abs_fmt || '-')} (${escapeHtml(branch.sml_pct_fmt || '-')})</strong></div>
            <div class="metric-line"><span>NPL</span><strong>${escapeHtml(branch.npl_abs_fmt || '-')} (${escapeHtml(branch.npl_pct_fmt || '-')})</strong></div>
            <div style="margin-top:0.75rem;">${simpleBar(branch.pinjaman || 0, Math.max(...branches.map(b => Number(b.pinjaman || 0)), 0), '#0857c3')}</div>
          </div>
        `).join('');
      };

      const renderFinancialHighlights = (data) => {
        const financial = data?.financial_highlights || {};
        setText('pres-financial-period', financial.period_label || '-');
        const cards = document.getElementById('pres-financial-cards');
        if (cards) {
          cards.innerHTML = (financial.cards || []).map(card => `
            <div class="bri-finance-card" style="--tone:${escapeHtml(card.tone || '#0857c3')};">
              <h3>${escapeHtml(card.label || '-')}</h3>
              <strong>${escapeHtml(card.value || '-')}</strong>
              <div class="metric-line"><span>Sumber</span><span>Almafacts</span></div>
            </div>
          `).join('');
        }
        const branches = document.getElementById('pres-financial-branches');
        if (branches) {
          const rows = financial.branches || [];
          const max = Math.max(...rows.map(row => Number(row.value_raw || 0)), 0);
          branches.innerHTML = rows.map(row => `
            <div class="metric-line" style="margin:0.5rem 0;">
              <span style="min-width:140px;">${escapeHtml(row.name || '-')}</span>
              <strong>${escapeHtml(row.value || '-')}</strong>
            </div>
            ${simpleBar(row.value_raw, max, '#0857c3')}
          `).join('') || '<div class="pres-mini-stat"><span>Financial</span><strong>Data belum tersedia</strong></div>';
        }
      };

      const renderQualityDeepDive = (data, type) => {
        const target = document.getElementById(type === 'sml' ? 'pres-sml-deep-grid' : 'pres-npl-deep-grid');
        const payload = data?.quality?.[type] || {};
        if (!target) return;
        const groups = [
          ['Ritel Nominal', payload.ritel_nominal || []],
          ['Ritel Rasio', payload.ritel_ratio || []],
          ['Micro Nominal', payload.micro_nominal || []],
          ['Micro Rasio', payload.micro_ratio || []],
        ];
        target.innerHTML = groups.map(([title, rows]) => `
          <div class="bri-inner-panel">
            <div class="bri-blue-panel-title"><i class="fas fa-ranking-star"></i> ${escapeHtml(title)}</div>
            <div class="bri-panel-body">
              <table class="pres-table-dense">
                <tbody>
                  ${(rows || []).slice(0, 8).map((row, idx) => `
                    <tr>
                      <td style="font-weight:900; width:38px;">${idx + 1}</td>
                      <td>${escapeHtml(row.label || row.name || '-')}</td>
                      <td style="text-align:right; font-weight:850;">${escapeHtml(row.value || row.value_fmt || row.amount_fmt || '-')}</td>
                    </tr>
                  `).join('') || '<tr><td style="text-align:center;">Data belum tersedia</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>
        `).join('');
      };

      const renderMicroProductivity = (data) => {
        const grid = document.getElementById('pres-micro-productivity-grid');
        if (!grid) return;
        const micro = data?.micro || {};
        const cards = [
          ['Putusan', micro?.decision?.total?.total_os_fmt || '-', (micro?.decision?.total?.total_deb || 0) + ' deb'],
          ['Mantri', micro?.mantri_productivity?.total?.realisasi_os_fmt || '-', (micro?.mantri_productivity?.total?.jumlah_mantri || 0) + ' mantri'],
          ['RM KUR Mikro', micro?.rm_kur_micro?.total?.realisasi_os_fmt || '-', (micro?.rm_kur_micro?.total?.realisasi_deb || 0) + ' deb'],
        ];
        grid.innerHTML = cards.map(([label, value, meta]) => `
          <div class="pres-mini-stat" style="margin-top:0.65rem;">
            <span>${escapeHtml(label)}</span>
            <strong>${escapeHtml(value)}</strong>
            <small style="color:#64748b; font-weight:800;">${escapeHtml(meta)}</small>
          </div>
        `).join('');
      };

      const populatePresentationData = (data) => {
        presentationData = data;

        populateCover(data);
        populatePerformanceControls(data);
        renderExecutiveLoanDashboard(data);
        renderSavingsDashboard(data);
        renderBranchWarRoom(data);
        renderFinancialHighlights(data);
        renderQualityDeepDive(data, 'sml');
        renderQualityDeepDive(data, 'npl');
        renderMicroProductivity(data);

        // Shared branch data
        const simpCard = data.summary.cards.find(c => c.key === 'simpanan') || {};
        const branches = data.performance_overview.branches || [];
        const dpkTarget = sumNumeric(branches, 'simpanan_target');
        const dpkRaw = Number(simpCard.value_raw || 0);

        // Slide 3: Kredit Performance
        const osCard = data.summary.cards.find(c => c.key === 'os') || {};
        const totalVolumeEl = document.getElementById('pres-kredit-total-volume');
        totalVolumeEl.textContent = osCard.value || 'Rp -';
        totalVolumeEl.setAttribute('data-raw-val', osCard.value_raw || 0);
        totalVolumeEl.setAttribute('data-is-currency', 'true');
        const loanTrendEl = document.getElementById('pres-kredit-total-trend');
        loanTrendEl.className = 'pres-kpi-sub-trend ' + (osCard.trend && osCard.trend.startsWith('-') ? 'neg' : 'pos');
        loanTrendEl.innerHTML = `<i class="fas ${osCard.trend && osCard.trend.startsWith('-') ? 'fa-arrow-down' : 'fa-arrow-up'} mr-1"></i> ${osCard.trend || '0%'} MtM`;

        const kreditTarget = sumNumeric(branches, 'pinjaman_target');
        const kreditRaw = Number(osCard.value_raw || 0);
        document.getElementById('pres-kredit-rka').textContent = kreditTarget > 0 ? formatCurrencyCompact(kreditTarget) : 'Data belum tersedia';
        document.getElementById('pres-kredit-achievement').textContent = kreditTarget > 0 && kreditRaw > 0 ? formatPercent((kreditRaw / kreditTarget) * 100) : 'Data belum tersedia';

        // Kredit Branch Table Shares
        const kreditBranchSharesTbody = document.getElementById('pres-kredit-branch-shares-tbody');
        kreditBranchSharesTbody.innerHTML = '';
        branches.forEach(b => {
          const tr = document.createElement('tr');
          const achievementColor = colorForAchievement(b.pinjaman_share_fmt);
          tr.innerHTML = `
            <td style="font-weight:700;">${escapeHtml(String(b.name || '-').toUpperCase())}</td>
            <td style="text-align:right; font-weight:600;">${escapeHtml(displayValue(b.pinjaman_fmt))}</td>
            <td style="text-align:right; font-weight:600; color:#475569;">${escapeHtml(displayValue(b.pinjaman_target_fmt))}</td>
            <td style="text-align:right; font-weight:800; color:${achievementColor};">${escapeHtml(displayValue(b.pinjaman_share_fmt))}</td>
            <td style="text-align:right; font-weight:700; color:#0857c3;">${escapeHtml(displayValue(b.pinjaman_contribution_pct_fmt))}</td>
          `;
          kreditBranchSharesTbody.appendChild(tr);
        });

        // Slide 3: Segment outstanding (OS) interactive workspace
        populateSegmentControls(data);
        renderSegmentExplorer();

        // Slide 5: Risk metrics (SML, NPL, LAR) workspace
        populateRiskControls(data);
        renderRiskOverview();

        // Comparative Quality Table
        const qualityComparisonTbody = document.getElementById('pres-quality-comparison-tbody');
        qualityComparisonTbody.innerHTML = '';

        let totalOsArea = 0;
        let totalSmlArea = 0;
        let totalNplArea = 0;
        let totalLarArea = 0;

        const qualityBranches = data.performance_overview.branches || [];
        qualityBranches.forEach(b => {
          const osVal = b.pinjaman || 0;
          const smlVal = b.sml_abs || 0;
          const nplVal = b.npl_abs || 0;
          const larVal = b.lar_abs || (smlVal + nplVal);
          const larPctVal = b.lar_pct || (osVal > 0 ? (larVal / osVal) * 100 : 0);

          totalOsArea += osVal;
          totalSmlArea += smlVal;
          totalNplArea += nplVal;
          totalLarArea += larVal;

          const tr = document.createElement('tr');
          tr.style.borderBottom = '1px solid rgba(0, 0, 0, 0.05)';
          tr.innerHTML = `
            <td style="font-weight:700; padding: 0.65rem 0.8rem; color: #1d1d1f;">${escapeHtml(displayValue(b.name))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:600; color: #1d1d1f;">${escapeHtml(displayValue(b.pinjaman_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:500; color: #b45309;">${escapeHtml(displayValue(b.sml_abs_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:700; color: #b45309;">${escapeHtml(displayValue(b.sml_pct_fmt, '0,00%'))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:500; color: #ef4444;">${escapeHtml(displayValue(b.npl_abs_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:700; color: #ef4444;">${escapeHtml(displayValue(b.npl_pct_fmt, '0,00%'))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:500; color: #0071e3;">${escapeHtml(displayValue(b.lar_abs_fmt))}</td>
            <td style="text-align:right; padding: 0.65rem 0.8rem; font-weight:700; color: #0071e3;">${escapeHtml(displayValue(b.lar_pct_fmt, '0,00%'))}</td>
          `;
          qualityComparisonTbody.appendChild(tr);
        });

        // Add a total row at the bottom!
        const totalLarPctVal = totalOsArea > 0 ? (totalLarArea / totalOsArea) * 100 : 0;
        const totalSmlPctVal = totalOsArea > 0 ? (totalSmlArea / totalOsArea) * 100 : 0;
        const totalNplPctVal = totalOsArea > 0 ? (totalNplArea / totalOsArea) * 100 : 0;

        const totalTr = document.createElement('tr');
        totalTr.style.background = 'rgba(0, 113, 227, 0.05)';
        totalTr.style.fontWeight = 'bold';
        totalTr.innerHTML = `
          <td style="padding: 0.7rem 0.8rem; color: #0071e3; font-weight:800; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">TOTAL AREA 6</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #1d1d1f;">${formatCurrencyCompact(totalOsArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #b45309;">${formatCurrencyCompact(totalSmlArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #b45309;">${totalSmlPctVal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #ef4444;">${formatCurrencyCompact(totalNplArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #ef4444;">${totalNplPctVal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #0071e3;">${formatCurrencyCompact(totalLarArea)}</td>
          <td style="text-align:right; padding: 0.7rem 0.8rem; font-weight:800; color: #0071e3; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">${totalLarPctVal.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}%</td>
        `;
        qualityComparisonTbody.appendChild(totalTr);

        // Slide 5: KTS
        renderKts();
        if (data?.kts?.loading_details) {
          window.setTimeout(loadPresentationKts, 500);
        }

        // Slide 6: 8 Digital Strategy Grid
        const digGrid = document.getElementById('pres-digital-cards-grid');
        digGrid.innerHTML = '';
        const digCards = data.digital_strategy.cards || [];
        digCards.forEach(c => {
          const cardDiv = document.createElement('div');
          cardDiv.className = 'pres-glass-card';
          cardDiv.style.display = 'flex';
          cardDiv.style.flexDirection = 'column';
          cardDiv.style.justifyContent = 'space-between';
          cardDiv.style.padding = '1.25rem';

          const isCasa = c.key === 'casa';
          const trendIsNeg = c.trend && c.trend.includes('-') && !isCasa;
          const trendColor = trendIsNeg ? '#ef4444' : '#047857';

          cardDiv.innerHTML = `
            <div>
              <div style="font-size:0.7rem; font-weight:700; color:rgba(0,0,0,0.4); text-transform:uppercase; letter-spacing:0.05em; display:flex; justify-content:space-between; align-items:center;">
                <span>${c.title}</span>
                <i class="fas fa-chart-line" style="color:rgba(0,0,0,0.25);"></i>
              </div>
              <div style="font-weight:800; margin:0.35rem 0;" class="pres-text-gradient-silver pres-digital-value">${c.current_value || '–'}</div>
              ${c.secondary_value && c.secondary_value !== '-' ? `<div style="font-size:0.72rem; color:rgba(0,0,0,0.55); font-weight:500;">Vol: <strong>${c.secondary_value}</strong></div>` : ''}
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid rgba(0,0,0,0.05); padding-top:0.5rem; margin-top:0.5rem; font-size:0.72rem;">
              <span style="color:rgba(0,0,0,0.4); font-size:0.68rem; font-weight:500;">Growth</span>
              <span style="color:${trendColor}; font-weight:700;">${c.trend || '–'}</span>
            </div>
          `;
          digGrid.appendChild(cardDiv);
        });
        renderDigitalTableAndChart();
      };

      // Load Presentation Data
      const selectedPeriod = "{{ $selectedPeriod }}";
      const presentationDataUrl = "{{ route('dashboard.presentation-data') }}";
      const presentationKtsDataUrl = "{{ route('dashboard.presentation-kts-data') }}";
      const serverPresentationData = @json($presentationPayload ?? null);

      const updateCoverKtsTotals = () => {
        if (!presentationData?.kts || presentationData.kts.loading_details) return;

        const totalKts = Number(presentationData?.kts?.ritel_total || 0) + Number(presentationData?.kts?.micro_total || 0);
        const totalMembaik = Number(presentationData?.kts?.categories?.membaik?.ritel?.total_count || 0)
          + Number(presentationData?.kts?.categories?.membaik?.micro?.total_count || 0);
        const totalMemburuk = Number(presentationData?.kts?.categories?.memburuk?.ritel?.total_count || 0)
          + Number(presentationData?.kts?.categories?.memburuk?.micro?.total_count || 0);

        const coverKts = document.getElementById('pres-cover-kts');
        if (coverKts) coverKts.textContent = new Intl.NumberFormat('id-ID').format(totalKts) + ' rek';

        const coverCards = document.querySelectorAll('#pres-cover-board .pres-cover-card');
        if (coverCards[7]) {
          const value = coverCards[7].querySelector('.value');
          if (value) value.textContent = new Intl.NumberFormat('id-ID').format(totalMembaik) + ' rek';
        }
        if (coverCards[8]) {
          const value = coverCards[8].querySelector('.value');
          if (value) value.textContent = new Intl.NumberFormat('id-ID').format(totalMemburuk) + ' rek';
        }
      };

      const loadPresentationKts = () => {
        if (!presentationData || !presentationData?.kts?.loading_details) {
          return Promise.resolve(presentationData?.kts || null);
        }

        if (ktsLoadPromise) return ktsLoadPromise;

        const url = new URL(presentationKtsDataUrl, window.location.origin);
        const ktsPeriod = presentationData?.meta?.daily_loan_period || selectedPeriod;
        if (ktsPeriod) {
          url.searchParams.set('periode', ktsPeriod);
        }

        ktsLoadPromise = fetch(url.toString(), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        })
          .then(response => {
            if (!response.ok) {
              throw new Error(`Failed to load KTS data (${response.status})`);
            }
            return response.json();
          })
          .then(data => {
            presentationData.kts = data?.kts || data || presentationData.kts;
            updateCoverKtsTotals();
            renderKts();
            return presentationData.kts;
          })
          .catch(err => {
            console.error(err);
            document.getElementById('pres-kts-note').textContent = 'Detail KTS belum berhasil dimuat. Data utama presentasi tetap dapat digunakan.';
            document.getElementById('pres-kts-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:2rem; color:#ef4444;">Gagal memuat detail KTS</td></tr>';
            ktsLoadPromise = null;
            return null;
          });

        return ktsLoadPromise;
      };

      const loadPresentation = async () => {
        const url = new URL(presentationDataUrl, window.location.origin);
        if (selectedPeriod) {
          url.searchParams.set('periode', selectedPeriod);
        }

        const progressBar = document.getElementById('loading-progress-bar');
        const progressPercent = document.getElementById('loading-progress-percent');
        const progressStatus = document.getElementById('dashboard-loading-status');

        let currentProgress = 0;
        let progressTimer = null;

        const setProgress = (value, text = null) => {
          currentProgress = Math.max(currentProgress, Math.min(100, value));
          if (progressBar) progressBar.style.width = currentProgress + '%';
          if (progressPercent) progressPercent.textContent = Math.round(currentProgress) + '%';
          if (text && progressStatus) progressStatus.textContent = text;
        };

        const startFastProgress = () => {
          setProgress(18, "Mengambil data presentasi...");
          progressTimer = window.setInterval(() => {
            if (currentProgress < 78) {
              setProgress(currentProgress + Math.max(1.5, (78 - currentProgress) * 0.12));
            }
          }, 120);
        };

        const stopFastProgress = () => {
          if (progressTimer !== null) {
            window.clearInterval(progressTimer);
            progressTimer = null;
          }
        };

        const finishLoading = () => {
          const loader = document.getElementById('dashboard-global-loader');
          if (loader) {
            loader.classList.remove('active');
          }
          showSlide(0);
        };

        let timeoutId = null;
        try {
          if (serverPresentationData) {
            setProgress(84, "Cache presentasi siap, merender slide...");
            await new Promise(resolve => requestAnimationFrame(resolve));
            populatePresentationData(serverPresentationData);
            setProgress(100, 'Selesai!');
            requestAnimationFrame(finishLoading);

            return;
          }

          startFastProgress();
          const controller = new AbortController();
          timeoutId = window.setTimeout(() => controller.abort(), 30000);
          const response = await fetch(url.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
          });
          window.clearTimeout(timeoutId);
          timeoutId = null;

          if (!response.ok) {
            throw new Error(`Failed to load presentasi data (${response.status})`);
          }

          stopFastProgress();
          setProgress(82, "Payload diterima, menyiapkan slide...");
          const data = await response.json();

          setProgress(94, "Merender slide presentasi...");
          await new Promise(resolve => requestAnimationFrame(resolve));
          populatePresentationData(data);

          setProgress(100, 'Selesai!');
          requestAnimationFrame(finishLoading);

        } catch (err) {
          stopFastProgress();
          const loader = document.getElementById('dashboard-global-loader');
          if (loader) loader.classList.remove('active');
          console.error(err);
          const message = err.name === 'AbortError'
            ? 'Request data presentasi melewati 30 detik. Silakan coba lagi setelah cache dashboard selesai terbentuk.'
            : err.message;
          alert('Gagal mengambil data presentasi: ' + message);
        } finally {
          stopFastProgress();
          if (timeoutId !== null) {
            window.clearTimeout(timeoutId);
          }
        }
      };

      // Initialize
      await loadPresentation();

      const metricToggle = document.getElementById('pres-metric-toggle');
      if (metricToggle) {
        metricToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-metric]');
          if (!btn) return;
          performanceState.metric = btn.getAttribute('data-metric');
          setActiveButton(metricToggle, 'data-metric', performanceState.metric);
          renderPerformanceExplorer();
        });
      }

      const scopeSelect = document.getElementById('pres-scope-select');
      if (scopeSelect) {
        scopeSelect.addEventListener('change', () => {
          performanceState.scope = scopeSelect.value || 'area6';
          renderPerformanceExplorer();
        });
      }

      const viewToggle = document.getElementById('pres-view-toggle');
      if (viewToggle) {
        viewToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-view]');
          if (!btn) return;
          performanceState.view = btn.getAttribute('data-view');
          setActiveButton(viewToggle, 'data-view', performanceState.view);
          renderPerformanceExplorer();
        });
      }

      const segMetricToggle = document.getElementById('pres-seg-metric-toggle');
      if (segMetricToggle) {
        segMetricToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-seg-metric]');
          if (!btn) return;
          segmentState.metric = btn.getAttribute('data-seg-metric');
          setActiveButton(segMetricToggle, 'data-seg-metric', segmentState.metric);

          // Update Title
          const titleEl = document.getElementById('pres-seg-explorer-title');
          if (segmentState.metric === 'sme_os') titleEl.textContent = 'OS SME';
          else if (segmentState.metric === 'consumer_os') titleEl.textContent = 'OS KONSUMER';
          else if (segmentState.metric === 'micro_os') titleEl.textContent = 'OS MIKRO';

          renderSegmentExplorer();
        });
      }

      const segScopeSelect = document.getElementById('pres-seg-scope-select');
      if (segScopeSelect) {
        segScopeSelect.addEventListener('change', () => {
          segmentState.scope = segScopeSelect.value || 'area6';
          renderSegmentExplorer();
        });
      }

      const riskScopeSelect = document.getElementById('pres-risk-scope-select');
      if (riskScopeSelect) {
        riskScopeSelect.addEventListener('change', () => {
          riskState.scope = riskScopeSelect.value || 'area6';
          renderRiskOverview();
        });
      }

      const ktsCategoryToggle = document.getElementById('pres-kts-category-toggle');
      if (ktsCategoryToggle) {
        ktsCategoryToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-kts-category]');
          if (!btn) return;
          ktsState.category = btn.getAttribute('data-kts-category');
          setActiveButton(ktsCategoryToggle, 'data-kts-category', ktsState.category);
          renderKts();
        });
      }

      const ktsScopeToggle = document.getElementById('pres-kts-scope-toggle');
      if (ktsScopeToggle) {
        ktsScopeToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-kts-scope]');
          if (!btn) return;
          ktsState.scope = btn.getAttribute('data-kts-scope');
          setActiveButton(ktsScopeToggle, 'data-kts-scope', ktsState.scope);
          renderKts();
        });
      }

      const digitalViewToggle = document.getElementById('pres-digital-view-toggle');
      if (digitalViewToggle) {
        digitalViewToggle.addEventListener('click', (e) => {
          const btn = e.target.closest('[data-digital-view]');
          if (!btn) return;
          digitalState.view = btn.getAttribute('data-digital-view');
          setActiveButton(digitalViewToggle, 'data-digital-view', digitalState.view);
          renderDigitalTableAndChart();
        });
      }

      // Start slideshow trigger
      if (presStartBtn) {
        presStartBtn.addEventListener('click', () => {
          showSlide(1);
          startAutoplay();
        });
      }

      if (presFinishBtn) {
        presFinishBtn.addEventListener('click', () => {
          stopAutoplay();
          window.location.href = "{{ route('dashboard', ['periode' => $selectedPeriod]) }}";
        });
      }

      if (presAutoplayBtn) {
        presAutoplayBtn.addEventListener('click', () => {
          if (autoplayEnabled) {
            stopAutoplay();
          } else {
            startAutoplay();
          }
        });
      }

      // Navigations
      if (presPrevBtn) {
        presPrevBtn.addEventListener('click', () => {
          if (currentSlideIndex > 0) {
            showSlide(currentSlideIndex - 1);
            scheduleAutoplay();
          }
        });
      }

      if (presNextBtn) {
        presNextBtn.addEventListener('click', () => {
          if (currentSlideIndex < totalSlides - 1) {
            showSlide(currentSlideIndex + 1);
            scheduleAutoplay();
          }
        });
      }

      // Dots
      if (presDots) {
        presDots.addEventListener('click', (e) => {
          const dot = e.target.closest('.pres-dot');
          if (dot) {
            const idx = parseInt(dot.getAttribute('data-index'));
            if (!isNaN(idx)) {
              showSlide(idx);
              scheduleAutoplay();
            }
          }
        });
      }

      // Keyboard navigation
      document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight' || e.key === ' ') {
          e.preventDefault();
          if (currentSlideIndex < totalSlides - 1) {
            showSlide(currentSlideIndex + 1);
            scheduleAutoplay();
          }
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          if (currentSlideIndex > 0) {
            showSlide(currentSlideIndex - 1);
            scheduleAutoplay();
          }
        } else if (e.key.toLowerCase() === 'a') {
          e.preventDefault();
          if (autoplayEnabled) {
            stopAutoplay();
          } else {
            startAutoplay();
          }
        } else if (e.key === 'Escape') {
          e.preventDefault();
          stopAutoplay();
          // Redirect back to dashboard
          window.location.href = "{{ route('dashboard', ['periode' => $selectedPeriod]) }}";
        }
      });
    });
  </script>
</body>
</html>
