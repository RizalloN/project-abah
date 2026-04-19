import os

file_path = r'c:\xampp\htdocs\project-ABAH\resources\views\import\report-management.blade.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = '    .report-management-progress__meta { display:block; color:#94a3b8; font-size: 0.85rem; font-weight:500; min-height:1.2rem; }'
replacement = """    .report-management-progress__meta { display:block; color:#94a3b8; font-size: 0.85rem; font-weight:500; min-height:1.2rem; }

    .report-management-notice { padding:1.25rem 1.5rem; border-radius:12px; background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; font-size:0.9rem; font-weight:600; line-height:1.5; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); }

    .report-management-load-card { padding:1.5rem; border-radius:16px; background:#ffffff; border:1px solid rgba(226,232,240,0.8); box-shadow:0 10px 15px -3px rgba(0,0,0,0.05); overflow:hidden; position:relative; }
    .report-management-load-card::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:#2563eb; }
    .report-management-load-card__header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.25rem; }
    .report-management-load-card__eyebrow { font-size:0.7rem; font-weight:800; letter-spacing:0.05em; text-transform:uppercase; color:#64748b; margin-bottom:0.25rem; }
    .report-management-load-card__title { font-size:1.1rem; font-weight:700; color:#0f172a; line-height:1.2; }
    .report-management-load-card__stage { padding:0.35rem 0.85rem; border-radius:999px; background:#eff6ff; color:#2563eb; font-size:0.75rem; font-weight:700; letter-spacing:0.02em; border:1px solid #bfdbfe; }
    
    .report-management-progress { height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; margin:1.25rem 0 0.75rem; }
    .report-management-progress__bar { background:linear-gradient(90deg, #2563eb 0%, #60a5fa 100%); transition:width 0.4s cubic-bezier(0.4, 0, 0.2, 1); border-radius:999px; }
    .report-management-progress__bar--indeterminate { background: linear-gradient(90deg, #2563eb 25%, #60a5fa 50%, #2563eb 75%); background-size: 200% 100%; animation: reportManagementProgressShift 1.5s infinite linear; }

    .report-management-load-card__meta-row { display:flex; justify-content:space-between; align-items:center; }
    .report-management-progress__value { font-size:1.25rem; font-weight:800; color:#0f172a; }
    .report-management-load-card__units { font-size:0.85rem; font-weight:600; color:#64748b; }
    .report-management-progress__text { color:#475569; font-size:0.9rem; font-weight:500; }"""

if target in content:
    new_content = content.replace(target, replacement)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Success")
else:
    # Try with different line endings or whitespace
    print("Target not found exactly. Trying fuzzy match...")
    import re
    fuzzy_target = re.escape(target).replace(r'\ ', r'\s+')
    if re.search(fuzzy_target, content):
        new_content = re.sub(fuzzy_target, replacement, content)
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print("Success (fuzzy)")
    else:
        print("Failure")
