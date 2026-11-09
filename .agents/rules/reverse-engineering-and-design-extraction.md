# Reverse Engineering & Design System Extraction Rule (Dembrandt & REA)

Aturan ini memandu agen AI saat diminta menganalisis, meniru, mereverse-engineer, atau membangun ulang aplikasi, antarmuka web, binary, atau API dari sistem eksternal.

---

## 1. Ekstraksi Visual & Design System via Dembrandt

Gunakan skill [`dembrandt`](../skills/dembrandt/SKILL.md) ([dembrandt/dembrandt](https://github.com/dembrandt/dembrandt)) ketika:
- User meminta meniru atau mencocokkan gaya desain suatu website referensi (`"buat tampilannya mirip https://..."`).
- Dibutuhkan file konteks desain AI (`DESIGN.md`) yang merangkum palet warna terhitung (computed styles), tipografi, spacing, border-radius, dan shadow.
- Ingin menghasilkan token Tailwind v4 (`@theme`) atau variabel tema `shadcn/ui` dari live DOM.
- Melakukan audit konsistensi visual atau kepatuhan kontras WCAG.

### Perintah Kunci:
```bash
# Hasilkan DESIGN.md untuk konteks AI
npx dembrandt <URL> --design-md

# Hasilkan konfigurasi tema Tailwind v4
npx dembrandt <URL> --tailwind ./resources/css/theme.css

# Hasilkan blok tema shadcn/ui
npx dembrandt <URL> --shadcn ./resources/css/shadcn.css

# Audit aksesibilitas warna
npx dembrandt <URL> --wcag
```

---

## 2. Reverse Engineering & Analisis Aplikasi via REA

Gunakan skill [`rea`](../skills/rea/SKILL.md) ([morluto/rea](https://github.com/morluto/rea)) ketika:
- User meminta membongkar cara kerja aplikasi, endpoint backend, atau struktur bundle JavaScript/Electron.
- Perlu mendekompresi atau menelusuri aplikasi Android (APK), binary native, atau assembly .NET.
- Ingin merekonstruksi fitur software secara *clean-room* tanpa akses kode sumber asli.

### Alur 3-Fase Wajib:
1. **Decompile**: Bongkar bundle/binary (`analyze-web-bundle`, `export-web-scripts`, `search-android-classes`, dll.).
2. **Understand**: Petakan alur eksekusi dan dependensi data (`trace-application-feature`, `trace-android-references`).
3. **Recreate**: Implementasikan ulang kode bersih dengan pelacakan bukti (*Evidence Protocol*):
   - **SEEN**: Fakta nyata dari kode/payload/string.
   - **INFERRED**: Hipotesis/deduksi logis.
   - **UNKNOWN**: Batasan analisis/bagian yang tidak terjangkau.

---

## 3. Sinergi Rekonstruksi Penuh (Full-Stack Building)

Saat membangun ulang produk atau fitur secara komprehensif dari target eksternal:
1. **Lapisan Logika & API**: Gunakan **REA** untuk mengekstrak kontrak endpoint, parameter request/response, dan alur state.
2. **Lapisan Tampilan & Visual**: Gunakan **Dembrandt** untuk mengekstrak token CSS, tipografi, dan file `DESIGN.md`.
3. **Penyusunan Solusi**: Gabungkan keduanya dalam arsitektur proyek (misal Laravel Controller/Service + Blade/Tailwind UI) untuk hasil yang presisi dan bebas halusinasi.
