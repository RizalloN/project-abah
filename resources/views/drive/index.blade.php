@extends('layouts.admin')
@section('title', 'DriveASIX')
@section('content')
<style>
:root{
  --d-blue:#0b5d7a;--d-blue-dk:#073b53;--d-blue-lt:#e7f5f8;
  --d-surface:#ffffff;--d-bg:#f8fafc;--d-border:#e2e8f0;
  --d-text:#1e293b;--d-muted:#64748b;--d-hover:#f1f5f9;
  --d-radius:12px;
  --d-shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.04);
  --d-shadow-md:0 4px 20px rgba(0,0,0,.10);
  --d-shadow-lg:0 16px 48px rgba(0,0,0,.14);
}
*,*::before,*::after{box-sizing:border-box;}
.dv-wrap{background:var(--d-bg);min-height:calc(100vh - 60px);display:flex;flex-direction:column;}

/* ── TOP BAR ── */
.dv-topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:.85rem 1.25rem;background:var(--d-surface);
  border-bottom:1px solid var(--d-border);gap:1rem;flex-wrap:wrap;
  position:sticky;top:0;z-index:100;
}
.dv-brand{display:flex;align-items:center;gap:.75rem;}
.dv-brand-logo{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,#0b5d7a,#0891b2);
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:1.1rem;box-shadow:0 3px 10px rgba(26,115,232,.3);
}
.dv-brand-name{font-size:1.15rem;font-weight:800;color:var(--d-text);margin:0;letter-spacing:-.01em;}
.dv-brand-sub{font-size:.7rem;color:var(--d-muted);margin:0;}
.dv-topbar-right{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;}

/* ── BUTTONS ── */
.btn-dv{
  display:inline-flex;align-items:center;gap:.4rem;
  padding:.46rem .95rem;border-radius:20px;border:none;
  min-height:34px;
  font-size:.8rem;font-weight:700;cursor:pointer;
  transition:all .15s;white-space:nowrap;text-decoration:none;line-height:1.2;
}
.btn-dv-primary{background:var(--d-blue);color:#fff;box-shadow:0 2px 8px rgba(26,115,232,.25);}
.btn-dv-primary:hover{background:var(--d-blue-dk);box-shadow:0 4px 14px rgba(26,115,232,.35);color:#fff;}
.btn-dv-secondary{background:var(--d-surface);color:var(--d-blue);border:1.5px solid var(--d-blue);}
.btn-dv-secondary:hover{background:var(--d-blue-lt);color:var(--d-blue);}
.btn-dv-danger{background:#fff1f2;color:#dc2626;border:1.5px solid #fca5a5;}
.btn-dv-danger:hover{background:#fee2e2;}
.btn-dv-ghost{background:transparent;color:var(--d-muted);border:none;}
.btn-dv-ghost:hover{background:var(--d-hover);color:var(--d-text);}
.btn-dv-icon{width:36px;height:36px;min-height:36px;padding:0!important;border-radius:50%;justify-content:center;}
.btn-dv-icon.on{background:var(--d-blue-lt);color:var(--d-blue);}
.btn-dv:disabled{cursor:not-allowed;opacity:.58;box-shadow:none;transform:none;pointer-events:none;}

/* ── BREADCRUMB ── */
.dv-breadcrumb{
  display:flex;align-items:center;gap:.2rem;
  padding:.6rem 1.25rem;background:var(--d-surface);
  border-bottom:1px solid var(--d-border);font-size:.8rem;flex-wrap:wrap;
}
.dv-breadcrumb a{color:var(--d-muted);text-decoration:none;font-weight:600;padding:.2rem .42rem;border-radius:6px;transition:all .12s;white-space:nowrap;min-width:0;max-width:min(24rem,70vw);overflow:hidden;text-overflow:ellipsis;}
.dv-breadcrumb a:hover{background:var(--d-hover);color:var(--d-text);}
.dv-breadcrumb .sep{color:#cbd5e1;display:flex;align-items:center;}
.dv-breadcrumb .current{color:var(--d-text);font-weight:700;padding:.2rem .42rem;white-space:nowrap;min-width:0;max-width:min(24rem,70vw);overflow:hidden;text-overflow:ellipsis;}

/* ── TOOLBAR ── */
.dv-toolbar{
  display:flex;align-items:center;gap:.6rem;
  padding:.6rem 1.25rem;background:var(--d-surface);
  border-bottom:1px solid var(--d-border);flex-wrap:wrap;
}
.dv-toolbar-div{width:1px;height:20px;background:var(--d-border);flex-shrink:0;}

/* ── DROP ZONE ── */
.dv-dropzone{
  margin:.85rem 1.25rem 0;border:2px dashed var(--d-blue);
  border-radius:var(--d-radius);background:var(--d-blue-lt);
  padding:1.75rem;text-align:center;cursor:pointer;
  transition:all .15s;display:none;
}
.dv-dropzone.is-open{display:block;}
.dv-dropzone.drag-over{background:#d2e3fc;border-color:var(--d-blue-dk);}
.dv-dropzone.is-busy{cursor:wait;opacity:.72;pointer-events:none;}
.dv-dz-icon{font-size:2.2rem;color:var(--d-blue);margin-bottom:.5rem;}
.dv-dz-text{font-size:.88rem;font-weight:700;color:var(--d-blue);margin:0 0 .2rem;}
.dv-dz-hint{font-size:.74rem;color:var(--d-muted);margin:0;}
#dvFileInput{display:none;}

.dv-progress{margin:.5rem 1.25rem;display:none;}
.dv-progress-bar{height:5px;border-radius:99px;background:var(--d-border);overflow:hidden;}
.dv-progress-fill{height:100%;background:var(--d-blue);border-radius:99px;transition:width .2s;}
.dv-progress-lbl{font-size:.74rem;font-weight:700;color:var(--d-blue);margin-bottom:.3rem;}
.dv-progress.is-validating .dv-progress-fill{background:#d97706;animation:dvProgressPulse 1s ease-in-out infinite alternate;}
.dv-progress.is-validating .dv-progress-lbl{color:#a16207;}
.dv-progress.is-error .dv-progress-fill{background:#dc2626;animation:none;}
.dv-progress.is-error .dv-progress-lbl{color:#b91c1c;}
@keyframes dvProgressPulse{from{opacity:.62;}to{opacity:1;}}

/* ── FLASH ── */
.dv-flash{display:flex;align-items:center;gap:.5rem;margin:.75rem 1.25rem;padding:.6rem 1rem;border-radius:var(--d-radius);font-size:.82rem;font-weight:600;}
.dv-flash.ok{background:#f0fdf4;border:1px solid #86efac;color:#166534;}
.dv-flash.err{background:#fff1f2;border:1px solid #fca5a5;color:#991b1b;}

/* ── CONTENT ── */
.dv-content{padding:1rem 1.25rem 2rem;flex:1;}
.dv-sec-lbl{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--d-muted);margin:.1rem 0 .55rem .1rem;}

/* ── GRID ── */
.dv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:.7rem;margin-bottom:1.5rem;}
.dv-card{
  background:var(--d-surface);border:1.5px solid var(--d-border);
  border-radius:var(--d-radius);padding:1rem .85rem .8rem;
  cursor:default;position:relative;display:flex;flex-direction:column;
  align-items:center;gap:.45rem;text-align:center;
  transition:box-shadow .15s,border-color .15s;user-select:none;min-height:118px;
}
.dv-card:hover{box-shadow:var(--d-shadow-md);border-color:#c5cde4;}
.dv-card.selected{border-color:var(--d-blue);background:var(--d-blue-lt);box-shadow:0 0 0 3px rgba(26,115,232,.12);}
.dv-card-icon{font-size:2.5rem;line-height:1;}
.dv-card-name{font-size:.76rem;font-weight:700;color:var(--d-text);word-break:break-word;line-height:1.3;max-width:100%;}
.dv-card-meta{font-size:.67rem;color:var(--d-muted);}
.dv-card-more{position:absolute;top:.35rem;right:.35rem;opacity:0;transition:opacity .15s;}
.dv-card:hover .dv-card-more,.dv-card.ctx-open .dv-card-more{opacity:1;}
@media (hover:none), (pointer:coarse){
  .dv-card-more{opacity:1;}
}

/* ── LIST ── */
.dv-list{border:1px solid var(--d-border);border-radius:var(--d-radius);overflow:hidden;margin-bottom:1.5rem;}
.dv-list-head,.dv-list-row{display:grid;grid-template-columns:2rem 1fr 7rem 9rem 5rem;gap:.75rem;padding:.55rem 1rem;align-items:center;}
.dv-list-head{background:#f8fafc;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--d-muted);border-bottom:1px solid var(--d-border);}
.dv-list-row{background:var(--d-surface);border-bottom:1px solid var(--d-border);transition:background .1s;cursor:default;}
.dv-list-row:last-child{border-bottom:none;}
.dv-list-row:hover{background:var(--d-hover);}
.dv-list-row.selected{background:var(--d-blue-lt);}
.dv-list-name{font-size:.8rem;font-weight:600;color:var(--d-text);display:flex;align-items:center;gap:.45rem;overflow:hidden;}
.dv-list-name span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.dv-list-cell{font-size:.75rem;color:var(--d-muted);}
.dv-list-acts{display:flex;justify-content:flex-end;gap:.25rem;}

/* ── EMPTY ── */
.dv-empty{text-align:center;padding:4rem 2rem;}
.dv-empty-icon{font-size:3.5rem;color:#dde3ec;margin-bottom:1rem;}
.dv-empty h3{font-size:1rem;font-weight:700;color:#94a3b8;margin-bottom:.35rem;}
.dv-empty p{font-size:.83rem;color:var(--d-muted);}

/* ── CONTEXT MENU ── */
.dv-ctx{
  position:fixed;z-index:9000;min-width:215px;
  background:var(--d-surface);border:1px solid var(--d-border);
  border-radius:var(--d-radius);box-shadow:var(--d-shadow-lg);
  padding:.35rem;display:none;
}
.dv-ctx.is-open{display:block;animation:ctxIn .12s ease;}
@keyframes ctxIn{from{opacity:0;transform:scale(.96) translateY(-4px);}to{opacity:1;transform:none;}}
.dv-ctx-item{display:flex;align-items:center;gap:.65rem;padding:.52rem .8rem;border-radius:8px;font-size:.82rem;font-weight:600;color:var(--d-text);cursor:pointer;transition:background .1s;user-select:none;}
.dv-ctx-item:hover{background:var(--d-hover);}
.dv-ctx-item i{width:1.1rem;text-align:center;color:var(--d-muted);font-size:.82rem;}
.dv-ctx-item.danger{color:#dc2626;}
.dv-ctx-item.danger i{color:#dc2626;}
.dv-ctx-sep{height:1px;background:var(--d-border);margin:.3rem 0;}

/* ── MODALS ── */
.dv-overlay{
  position:fixed;inset:0;background:rgba(15,23,42,.55);
  z-index:9100;display:none;align-items:center;justify-content:center;
  backdrop-filter:blur(3px);padding:1rem;
}
.dv-overlay.is-open{display:flex;}
.dv-modal{background:var(--d-surface);border-radius:16px;box-shadow:var(--d-shadow-lg);width:100%;}
.dv-overlay.is-open .dv-modal{animation:modalIn .18s ease;}
@keyframes modalIn{from{opacity:0;transform:translateY(12px) scale(.97);}to{opacity:1;transform:none;}}
.dv-modal-sm{max-width:420px;}.dv-modal-md{max-width:560px;}.dv-modal-lg{max-width:1100px;}
.dv-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--d-border);}
.dv-modal-title{font-size:.95rem;font-weight:800;color:var(--d-text);margin:0;display:flex;align-items:center;gap:.5rem;}
.dv-modal-body{padding:1.2rem;}
.dv-modal-footer{display:flex;justify-content:flex-end;gap:.5rem;padding:.85rem 1.25rem;border-top:1px solid var(--d-border);}
.dv-preview-body{min-height:300px;background:#f1f3f4;display:flex;align-items:center;justify-content:center;max-height:72vh;overflow:auto;}
.dv-preview-body iframe{width:100%;height:68vh;border:none;display:block;}
.dv-preview-body img{max-width:100%;max-height:68vh;object-fit:contain;border-radius:6px;display:block;margin:auto;}

/* ── FORM ── */
.dv-lbl{display:block;font-size:.78rem;font-weight:700;color:var(--d-text);margin-bottom:.35rem;}
.dv-inp,.dv-sel{width:100%;padding:.55rem .85rem;border:1.5px solid var(--d-border);border-radius:8px;font-size:.88rem;color:var(--d-text);outline:none;transition:border-color .15s,box-shadow .15s;background:#fff;}
.dv-inp:focus,.dv-sel:focus{border-color:var(--d-blue);box-shadow:0 0 0 3px rgba(26,115,232,.12);}
.dv-inp-grp{margin-bottom:.9rem;}

/* ── FOLDER TREE ── */
.dv-tree{border:1px solid var(--d-border);border-radius:8px;max-height:260px;overflow-y:auto;}
.dv-tree-item{display:flex;align-items:center;gap:.5rem;padding:.5rem .85rem;cursor:pointer;font-size:.82rem;font-weight:600;color:var(--d-text);border-bottom:1px solid var(--d-border);transition:background .1s;}
.dv-tree-item:last-child{border-bottom:none;}
.dv-tree-item:hover{background:var(--d-hover);}
.dv-tree-item.sel{background:var(--d-blue-lt);color:var(--d-blue);}
.dv-tree-item i.fa-folder{color:#f6c341;}

.dv-trash-badge{display:inline-flex;align-items:center;justify-content:center;background:#dc2626;color:#fff;border-radius:99px;font-size:.65rem;font-weight:800;min-width:18px;height:18px;padding:0 5px;margin-left:.2rem;}

@media(max-width:640px){
  .dv-grid{grid-template-columns:repeat(auto-fill,minmax(128px,1fr));}
  .dv-list-head,.dv-list-row{grid-template-columns:2rem 1fr 5rem;}
  .dv-list-head>:nth-child(n+4),.dv-list-row>:nth-child(n+4){display:none;}
}
</style>

<div class="dv-wrap">

{{-- ══ TOP BAR ══ --}}
<div class="dv-topbar">
  <div class="dv-brand">
    <div class="dv-brand-logo"><i class="fas fa-hdd"></i></div>
    <div>
      <p class="dv-brand-name">DriveASIX</p>
      <p class="dv-brand-sub">Penyimpanan file internal Area 6</p>
    </div>
  </div>
  <div class="dv-topbar-right">
    @if(auth()->user()->isAdmin())
      <button class="btn-dv btn-dv-primary" id="btnNewFolder"><i class="fas fa-folder-plus"></i> Folder Baru</button>
      <button class="btn-dv btn-dv-primary" id="btnUpload"><i class="fas fa-upload"></i> Upload</button>
      <button class="btn-dv {{ $trashedCount > 0 ? 'btn-dv-danger' : 'btn-dv-ghost' }}" id="btnTrash">
        <i class="fas fa-trash-alt"></i> Sampah
        @if($trashedCount > 0)<span class="dv-trash-badge">{{ $trashedCount }}</span>@endif
      </button>
    @endif
    <button class="btn-dv btn-dv-ghost btn-dv-icon" id="btnGrid" title="Grid view (G)"><i class="fas fa-th"></i></button>
    <button class="btn-dv btn-dv-ghost btn-dv-icon" id="btnList" title="List view (L)"><i class="fas fa-list"></i></button>
  </div>
</div>

{{-- ══ BREADCRUMB ══ --}}
<div class="dv-breadcrumb">
  <a href="{{ route('drive.index') }}"><i class="fas fa-hdd" style="margin-right:.3rem;font-size:.8rem;"></i>DriveASIX</a>
  @foreach($breadcrumbs as $crumb)
    <span class="sep"><i class="fas fa-chevron-right" style="font-size:.6rem;color:#cbd5e1;margin:0 .15rem;"></i></span>
    @if(!$loop->last)
      <a href="{{ route('drive.index', $crumb->id) }}">{{ $crumb->name }}</a>
    @else
      <span class="current">{{ $crumb->name }}</span>
    @endif
  @endforeach
</div>

{{-- ══ TOOLBAR ══ --}}
<div class="dv-toolbar">
  <span style="font-size:.76rem;color:var(--d-muted);"><i class="fas fa-mouse-pointer" style="margin-right:.3rem;"></i>Klik kanan atau klik <i class="fas fa-ellipsis-v"></i> pada item untuk opsi</span>
  <div class="dv-toolbar-div"></div>
  <span style="font-size:.74rem;color:var(--d-muted);">{{ $folders->count() }} folder &middot; {{ $files->count() }} file</span>
</div>

{{-- ══ FLASH ══ --}}
@if(session('success'))
  <div class="dv-flash ok" id="dvFlash"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="dv-flash err" id="dvFlash2"><i class="fas fa-times-circle"></i> {{ session('error') }}</div>
@endif
@if($errors->any())
  <div class="dv-flash err" id="dvValidationErrors">
    <i class="fas fa-exclamation-circle"></i>
    <span>{{ $errors->first() }}</span>
  </div>
@endif

{{-- ══ UPLOAD ZONE ══ --}}
@if(auth()->user()->isAdmin())
  <div class="dv-dropzone" id="dvDropzone">
    <div class="dv-dz-icon"><i class="fas fa-upload"></i></div>
    <p class="dv-dz-text">Seret & lepas file di sini, atau <u>klik untuk memilih</u></p>
    <p class="dv-dz-hint">Semua format didukung &middot; Maks 50 MB per file</p>
  </div>
  <div class="dv-progress" id="dvProgress" role="status" aria-live="polite">
    <p class="dv-progress-lbl" id="dvProgressLbl">Mengunggah...</p>
    <div class="dv-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
      <div class="dv-progress-fill" id="dvProgressFill" style="width:0%"></div>
    </div>
  </div>
  <form id="dvUploadForm" method="POST" action="{{ route('drive.upload') }}" enctype="multipart/form-data" style="display:none;">
    @csrf
    <input type="hidden" name="folder_id" value="{{ $folderId }}">
    <input type="file" id="dvFileInput" name="files[]" multiple
      accept=".xlsx,.xls,.csv,.pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.docx,.doc,.word,.pptx,.ppt">
  </form>
@endif

{{-- ══ CONTENT ══ --}}
<div class="dv-content">
  @if($folders->isEmpty() && $files->isEmpty())
    <div class="dv-empty">
      <div class="dv-empty-icon"><i class="fas fa-folder-open"></i></div>
      <h3>Folder ini masih kosong</h3>
      <p>{{ auth()->user()->isAdmin() ? 'Buat folder baru atau upload file untuk memulai.' : 'Belum ada file di sini.' }}</p>
    </div>
  @else

    {{-- GRID VIEW --}}
    <div id="viewGrid">
      @if($folders->isNotEmpty())
        <div class="dv-sec-lbl">Folder</div>
        <div class="dv-grid" id="gridFolders">
          @foreach($folders as $folder)
          <div class="dv-card" data-type="folder" data-id="{{ $folder->id }}" data-name="{{ $folder->name }}"
            data-open-url="{{ route('drive.index', $folder->id) }}"
            data-rename-url="{{ route('drive.folder.rename', $folder) }}"
            data-move-url="{{ route('drive.folder.move', $folder) }}"
            data-delete-url="{{ route('drive.folder.delete', $folder) }}"
            role="button" tabindex="0" aria-label="Buka folder {{ $folder->name }}"
            ondblclick="openItem(this)"
            onclick="selectCard(this,event)"
            @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
            @if(auth()->user()->isAdmin())
            <button class="btn-dv btn-dv-ghost btn-dv-icon dv-card-more"
              onclick="event.stopPropagation();openCtx(event,this.closest('.dv-card'))"
              title="Opsi lainnya"><i class="fas fa-ellipsis-v"></i></button>
            @endif
            <div class="dv-card-icon" style="color:#f6c341;"><i class="fas fa-folder"></i></div>
            <div class="dv-card-name">{{ $folder->name }}</div>
            <div class="dv-card-meta">Folder</div>
          </div>
          @endforeach
        </div>
      @endif
      @if($files->isNotEmpty())
        <div class="dv-sec-lbl">File</div>
        <div class="dv-grid" id="gridFiles">
          @foreach($files as $file)
          @php $ic = $file->iconInfo(); @endphp
          <div class="dv-card" data-type="file" data-id="{{ $file->id }}" data-name="{{ $file->original_name }}"
            data-mode="{{ $file->openMode() }}"
            data-office-url="{{ route('drive.file.office-editor', $file) }}"
            data-editor-url="{{ route('drive.file.editor', $file) }}"
            data-preview-url="{{ route('drive.file.preview', $file) }}"
            data-document-url="{{ route('drive.file.document-preview', $file) }}"
            data-download-url="{{ route('drive.file.download', $file) }}"
            data-rename-url="{{ route('drive.file.rename', $file) }}"
            data-move-url="{{ route('drive.file.move', $file) }}"
            data-copy-url="{{ route('drive.file.copy', $file) }}"
            data-delete-url="{{ route('drive.file.delete', $file) }}"
            role="button" tabindex="0" aria-label="Buka file {{ $file->original_name }}"
            ondblclick="openItem(this)"
            onclick="selectCard(this,event)"
            @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
            @if(auth()->user()->isAdmin())
            <button class="btn-dv btn-dv-ghost btn-dv-icon dv-card-more"
              onclick="event.stopPropagation();openCtx(event,this.closest('.dv-card'))"
              title="Opsi lainnya"><i class="fas fa-ellipsis-v"></i></button>
            @endif
            <div class="dv-card-icon" style="color:{{ $ic['color'] }};"><i class="{{ $ic['icon'] }}"></i></div>
            <div class="dv-card-name">{{ $file->original_name }}</div>
            <div class="dv-card-meta">{{ $file->humanSize() }}</div>
          </div>
          @endforeach
        </div>
      @endif
    </div>

    {{-- LIST VIEW --}}
    <div id="viewList" style="display:none;">
      <div class="dv-list">
        <div class="dv-list-head">
          <div></div><div>Nama</div><div>Ukuran</div><div>Diunggah oleh</div><div>Aksi</div>
        </div>
        @foreach($folders as $folder)
        <div class="dv-list-row" data-type="folder" data-id="{{ $folder->id }}" data-name="{{ $folder->name }}"
          data-open-url="{{ route('drive.index', $folder->id) }}"
          data-rename-url="{{ route('drive.folder.rename', $folder) }}"
          data-move-url="{{ route('drive.folder.move', $folder) }}"
          data-delete-url="{{ route('drive.folder.delete', $folder) }}"
          role="button" tabindex="0" aria-label="Buka folder {{ $folder->name }}"
          ondblclick="openItem(this)"
          onclick="selectRow(this,event)"
          @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
          <div style="color:#f6c341;text-align:center;"><i class="fas fa-folder"></i></div>
          <div class="dv-list-name"><span>{{ $folder->name }}</span></div>
          <div class="dv-list-cell">—</div>
          <div class="dv-list-cell">{{ optional($folder->creator)->name ?? '—' }}</div>
          <div class="dv-list-acts" onclick="event.stopPropagation()">
            @if(auth()->user()->isAdmin())
              <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="openCtx(event,this.closest('.dv-list-row'))" title="Opsi"><i class="fas fa-ellipsis-v"></i></button>
            @endif
          </div>
        </div>
        @endforeach
        @foreach($files as $file)
        @php $ic = $file->iconInfo(); @endphp
        <div class="dv-list-row" data-type="file" data-id="{{ $file->id }}" data-name="{{ $file->original_name }}"
          data-mode="{{ $file->openMode() }}"
          data-office-url="{{ route('drive.file.office-editor', $file) }}"
          data-editor-url="{{ route('drive.file.editor', $file) }}"
          data-preview-url="{{ route('drive.file.preview', $file) }}"
          data-document-url="{{ route('drive.file.document-preview', $file) }}"
          data-download-url="{{ route('drive.file.download', $file) }}"
          data-rename-url="{{ route('drive.file.rename', $file) }}"
          data-move-url="{{ route('drive.file.move', $file) }}"
          data-copy-url="{{ route('drive.file.copy', $file) }}"
          data-delete-url="{{ route('drive.file.delete', $file) }}"
          role="button" tabindex="0" aria-label="Buka file {{ $file->original_name }}"
          ondblclick="openItem(this)"
          onclick="selectRow(this,event)"
          @if(auth()->user()->isAdmin()) oncontextmenu="openCtx(event,this)" @endif>
          <div style="color:{{ $ic['color'] }};text-align:center;"><i class="{{ $ic['icon'] }}"></i></div>
          <div class="dv-list-name"><span>{{ $file->original_name }}</span></div>
          <div class="dv-list-cell">{{ $file->humanSize() }}</div>
          <div class="dv-list-cell">{{ optional($file->uploader)->name ?? '—' }}</div>
          <div class="dv-list-acts" onclick="event.stopPropagation()">
            @if(auth()->user()->isAdmin())
              <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="openCtx(event,this.closest('.dv-list-row'))" title="Opsi"><i class="fas fa-ellipsis-v"></i></button>
            @endif
          </div>
        </div>
        @endforeach
      </div>
    </div>

  @endif
</div>
</div>

{{-- ══ CONTEXT MENU ══ --}}
<div class="dv-ctx" id="dvCtx">
  <div class="dv-ctx-item" id="ctxOpen"><i class="fas fa-external-link-alt"></i> Buka</div>
  <div class="dv-ctx-item" id="ctxPreview"><i class="fas fa-eye"></i> Preview</div>
  <div class="dv-ctx-item" id="ctxDownload"><i class="fas fa-download"></i> Download</div>
  <div class="dv-ctx-sep" id="ctxAdminSep"></div>
  <div class="dv-ctx-item" id="ctxRename"><i class="fas fa-pen"></i> Rename</div>
  <div class="dv-ctx-item" id="ctxMove"><i class="fas fa-folder-open"></i> Pindahkan ke...</div>
  <div class="dv-ctx-item" id="ctxCopy"><i class="fas fa-copy"></i> Salin ke...</div>
  <div class="dv-ctx-sep" id="ctxDangerSep"></div>
  <div class="dv-ctx-item danger" id="ctxDelete"><i class="fas fa-trash-alt"></i> Hapus ke Sampah</div>
</div>

{{-- ══ MODAL: New Folder ══ --}}
<div class="dv-overlay" id="modalFolder">
  <div class="dv-modal dv-modal-sm">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title"><i class="fas fa-folder-plus" style="color:#f6c341;"></i> Folder Baru</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalFolder')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="{{ route('drive.folder.store') }}">
      @csrf
      <input type="hidden" name="parent_id" value="{{ $folderId }}">
      <div class="dv-modal-body">
        <label class="dv-lbl" for="newFolderName">Nama Folder</label>
        <input class="dv-inp" type="text" id="newFolderName" name="name" placeholder="Contoh: Laporan Q3 2026" required maxlength="255">
      </div>
      <div class="dv-modal-footer">
        <button type="button" class="btn-dv btn-dv-ghost" onclick="closeModal('modalFolder')">Batal</button>
        <button type="submit" class="btn-dv btn-dv-primary"><i class="fas fa-check"></i> Buat</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ MODAL: Rename ══ --}}
<div class="dv-overlay" id="modalRename">
  <div class="dv-modal dv-modal-sm">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title" id="renameMTitle"><i class="fas fa-pen" style="color:var(--d-blue);"></i> Rename</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalRename')"><i class="fas fa-times"></i></button>
    </div>
    <form id="renameForm" method="POST" action="">
      @csrf @method('PATCH')
      <div class="dv-modal-body">
        <label class="dv-lbl">Nama Baru</label>
        <input class="dv-inp" type="text" id="renameInp" name="name" required maxlength="255">
      </div>
      <div class="dv-modal-footer">
        <button type="button" class="btn-dv btn-dv-ghost" onclick="closeModal('modalRename')">Batal</button>
        <button type="submit" class="btn-dv btn-dv-primary"><i class="fas fa-check"></i> Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ MODAL: Move/Copy ══ --}}
<div class="dv-overlay" id="modalMoveCopy">
  <div class="dv-modal dv-modal-md">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title" id="mcTitle"><i class="fas fa-folder-open" style="color:#f6c341;"></i> Pindahkan ke</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalMoveCopy')"><i class="fas fa-times"></i></button>
    </div>
    <form id="mcForm" method="POST" action="">
      @csrf @method('PATCH')
      <input type="hidden" name="action" id="mcAction" value="move">
      <input type="hidden" name="destination_folder_id" id="mcDestId" value="">
      <div class="dv-modal-body">
        <p style="font-size:.8rem;color:var(--d-muted);margin:0 0 .75rem;">Pilih folder tujuan. Klik <strong>Root DriveASIX</strong> untuk memindahkan ke halaman utama.</p>
        <div class="dv-tree" id="folderTree">
          <div class="dv-tree-item sel" data-id="" onclick="selectTree(this,'')">
            <i class="fas fa-hdd" style="color:var(--d-blue);font-size:.9rem;"></i> Root DriveASIX
          </div>
          @foreach($allFolders as $f)
          <div class="dv-tree-item" data-id="{{ $f->id }}" onclick="selectTree(this,{{ $f->id }})">
            <span style="display:inline-block;width:{{ $f->depth * 1.1 }}rem;"></span>
            <i class="fas fa-folder"></i> {{ $f->name }}
          </div>
          @endforeach
        </div>
      </div>
      <div class="dv-modal-footer">
        <button type="button" class="btn-dv btn-dv-ghost" onclick="closeModal('modalMoveCopy')">Batal</button>
        <button type="submit" class="btn-dv btn-dv-primary" id="mcSubmitBtn"><i class="fas fa-check"></i> Pindahkan</button>
      </div>
    </form>
  </div>
</div>

{{-- ══ MODAL: Preview ══ --}}
<div class="dv-overlay" id="modalPreview">
  <div class="dv-modal dv-modal-lg">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title" id="previewTitle" style="max-width:78%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.85rem;"></h3>
      <div style="display:flex;gap:.4rem;">
        <a class="btn-dv btn-dv-secondary" id="previewDlBtn" href="#" style="font-size:.76rem;padding:.38rem .7rem;"><i class="fas fa-download"></i> Download</a>
        <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalPreview')"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="dv-preview-body" id="previewBody"></div>
  </div>
</div>

{{-- ══ MODAL: Trash ══ --}}
<div class="dv-overlay" id="modalTrash">
  <div class="dv-modal dv-modal-md">
    <div class="dv-modal-hdr">
      <h3 class="dv-modal-title"><i class="fas fa-trash-alt" style="color:#dc2626;"></i> Sampah</h3>
      <button class="btn-dv btn-dv-ghost btn-dv-icon" onclick="closeModal('modalTrash')"><i class="fas fa-times"></i></button>
    </div>
    <div class="dv-modal-body" style="padding:0;">
      @if($trashedFiles->isEmpty())
        <div style="text-align:center;padding:2.5rem;color:var(--d-muted);">
          <i class="fas fa-check-circle" style="font-size:2rem;color:#86efac;margin-bottom:.75rem;display:block;"></i>
          Tidak ada file di sampah.
        </div>
      @else
        @foreach($trashedFiles as $tf)
          @php $tic = $tf->iconInfo(); @endphp
          <div style="display:flex;align-items:center;gap:.75rem;padding:.7rem 1.25rem;border-bottom:1px solid var(--d-border);">
            <span style="color:{{ $tic['color'] }};font-size:1.3rem;flex-shrink:0;"><i class="{{ $tic['icon'] }}"></i></span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.82rem;font-weight:700;color:var(--d-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $tf->original_name }}</div>
              <div style="font-size:.7rem;color:var(--d-muted);">{{ $tf->humanSize() }} &middot; Dihapus {{ $tf->deleted_at->diffForHumans() }}</div>
            </div>
            <div style="display:flex;gap:.3rem;flex-shrink:0;">
              <form method="POST" action="{{ route('drive.file.restore', $tf->id) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-dv btn-dv-secondary" style="font-size:.72rem;padding:.3rem .6rem;" title="Pulihkan"><i class="fas fa-undo"></i> Pulihkan</button>
              </form>
              <form method="POST" action="{{ route('drive.file.purge', $tf->id) }}" onsubmit="return confirm('Hapus permanen?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-dv btn-dv-danger" style="font-size:.72rem;padding:.3rem .6rem;" title="Hapus permanen"><i class="fas fa-times"></i></button>
              </form>
            </div>
          </div>
        @endforeach
        <div style="padding:.85rem 1.25rem;display:flex;justify-content:flex-end;border-top:1px solid var(--d-border);">
          <form method="POST" action="{{ route('drive.file.purge-all') }}" onsubmit="return confirm('Hapus semua file di sampah secara permanen? Aksi ini tidak dapat dibatalkan.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-dv btn-dv-danger"><i class="fas fa-fire-alt"></i> Kosongkan Sampah</button>
          </form>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ══ JAVASCRIPT ══ --}}
<script>
const IS_ADMIN = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';

/* ── View Toggle ── */
function setView(v) {
  const grid = document.getElementById('viewGrid');
  const list = document.getElementById('viewList');
  if (!grid || !list) return;
  localStorage.setItem('drive_view', v);
  grid.style.display = v === 'grid' ? '' : 'none';
  list.style.display = v === 'list' ? '' : 'none';
  document.getElementById('btnGrid')?.classList.toggle('on', v === 'grid');
  document.getElementById('btnList')?.classList.toggle('on', v === 'list');
}
setView(localStorage.getItem('drive_view') || 'grid');
document.getElementById('btnGrid')?.addEventListener('click', () => setView('grid'));
document.getElementById('btnList')?.addEventListener('click', () => setView('list'));

/* ── Selection ── */
function isCoarsePointer() { return window.matchMedia?.('(hover: none), (pointer: coarse)').matches ?? false; }
function selectCard(el, event) {
  document.querySelectorAll('.dv-card.selected').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  if (isCoarsePointer() && !event?.target?.closest('button')) openItem(el);
}
function selectRow(el, event) {
  document.querySelectorAll('.dv-list-row.selected').forEach(r => r.classList.remove('selected'));
  el.classList.add('selected');
  if (isCoarsePointer() && !event?.target?.closest('button')) openItem(el);
}
document.querySelectorAll('.dv-card[role="button"],.dv-list-row[role="button"]').forEach(item => {
  item.addEventListener('keydown', event => {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    event.preventDefault();
    openItem(item);
  });
});
document.addEventListener('click', e => { if (!e.target.closest('.dv-card,.dv-list-row')) { document.querySelectorAll('.dv-card.selected,.dv-list-row.selected').forEach(el => el.classList.remove('selected')); } });

/* ── Modals ── */
function openModal(id) { document.getElementById(id).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('is-open'); if (!document.querySelector('.dv-overlay.is-open')) document.body.style.overflow = ''; }
document.querySelectorAll('.dv-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); }));

/* ── New Folder ── */
document.getElementById('btnNewFolder')?.addEventListener('click', () => { document.getElementById('newFolderName').value = ''; openModal('modalFolder'); setTimeout(() => document.getElementById('newFolderName').focus(), 120); });

/* ── Upload ── */
const $dz = document.getElementById('dvDropzone');
const $fi = document.getElementById('dvFileInput');
const $uf = document.getElementById('dvUploadForm');
const $pp = document.getElementById('dvProgress');
const $pf = document.getElementById('dvProgressFill');
const $pl = document.getElementById('dvProgressLbl');
const $uploadButton = document.getElementById('btnUpload');
const UPLOAD_BATCH_SIZE = 10;
let uploadInFlight = false;

function setUploadBusy(busy) {
  uploadInFlight = busy;
  if ($uploadButton) $uploadButton.disabled = busy;
  if ($fi) $fi.disabled = busy;
  $dz?.classList.toggle('is-busy', busy);
  $dz?.setAttribute('aria-busy', busy ? 'true' : 'false');
}
function setUploadProgress(width, label, state = '') {
  const boundedWidth = Math.max(0, Math.min(100, Number(width) || 0));
  if ($pp) {
    $pp.style.display = 'block';
    $pp.classList.toggle('is-validating', state === 'validating');
    $pp.classList.toggle('is-error', state === 'error');
    $pp.querySelector('[role="progressbar"]')?.setAttribute('aria-valuenow', String(Math.round(boundedWidth)));
  }
  if ($pf) $pf.style.width = `${boundedWidth}%`;
  if ($pl) $pl.textContent = label;
}
function setUploadError(message) {
  const currentWidth = Number.parseFloat($pf?.style.width || '0');
  setUploadProgress(
    Number.isFinite(currentWidth) ? Math.min(currentWidth, 96) : 0,
    message,
    'error'
  );
  if ($fi) $fi.value = '';
  setUploadBusy(false);
}
function parseUploadJson(xhr) {
  const contentType = xhr.getResponseHeader('Content-Type') || '';
  if (!/\bapplication\/(?:[a-z0-9.+-]*\+)?json\b/i.test(contentType)) return null;
  try {
    const payload = JSON.parse(xhr.responseText);
    return payload && typeof payload === 'object' && !Array.isArray(payload) ? payload : null;
  } catch (_) {
    return null;
  }
}
function uploadErrorMessage(payload, batchFiles, fallback) {
  if (payload?.errors && typeof payload.errors === 'object') {
    for (const [key, messages] of Object.entries(payload.errors)) {
      const message = Array.isArray(messages) ? messages[0] : messages;
      if (!message) continue;
      const match = key.match(/^files\.(\d+)(?:\.|$)/);
      const file = match ? batchFiles[Number(match[1])] : null;
      return file?.name ? `${file.name}: ${message}` : String(message);
    }
  }
  return payload?.message || fallback;
}
function sameOriginUploadRedirect(value) {
  if (typeof value !== 'string' || !value.trim()) return null;
  try {
    const url = new URL(value, window.location.href);
    if (url.origin !== window.location.origin || url.username || url.password) return null;
    return url;
  } catch (_) {
    return null;
  }
}
function sendUploadBatch(batchFiles, batchIndex, batchCount) {
  return new Promise((resolve, reject) => {
    const fd = new FormData($uf);
    fd.delete('files[]');
    batchFiles.forEach(file => fd.append('files[]', file));

    const xhr = new XMLHttpRequest();
    xhr.open('POST', $uf.action);
    xhr.setRequestHeader('X-CSRF-TOKEN', CSRF);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.upload.addEventListener('progress', event => {
      if (!event.lengthComputable) return;
      const batchFraction = Math.max(0, Math.min(1, event.loaded / event.total));
      const aggregatePercent = Math.min(
        99,
        Math.round(((batchIndex + batchFraction) / batchCount) * 100)
      );
      setUploadProgress(
        Math.min(aggregatePercent, 96),
        `Mengunggah batch ${batchIndex + 1}/${batchCount}... ${aggregatePercent}%`
      );
    });
    xhr.upload.addEventListener('load', () => {
      const validationWidth = Math.min(
        96,
        Math.round(((batchIndex + 1) / batchCount) * 100)
      );
      setUploadProgress(
        validationWidth,
        `Memvalidasi dan menyimpan... (batch ${batchIndex + 1}/${batchCount})`,
        'validating'
      );
    });
    xhr.addEventListener('load', () => {
      const payload = parseUploadJson(xhr);
      const redirect = sameOriginUploadRedirect(payload?.redirect_url);
      const validSuccess = xhr.status === 201
        && payload !== null
        && Number.isInteger(payload.uploaded_count)
        && payload.uploaded_count === batchFiles.length
        && redirect !== null;

      if (validSuccess) {
        resolve({
          uploadedCount: payload.uploaded_count,
          redirect,
        });
        return;
      }

      reject(new Error(uploadErrorMessage(
        payload,
        batchFiles,
        xhr.status === 201
          ? 'Respons sukses server tidak lengkap atau tidak konsisten.'
          : 'Upload gagal. Periksa format dan ukuran file.'
      )));
    });
    xhr.addEventListener('error', () => reject(new Error('Koneksi ke server terputus.')));
    xhr.addEventListener('abort', () => reject(new Error('Upload dibatalkan.')));
    xhr.addEventListener('timeout', () => reject(new Error('Upload melewati batas waktu.')));

    try {
      xhr.send(fd);
    } catch (_) {
      reject(new Error('Upload tidak dapat dimulai.'));
    }
  });
}

$uploadButton?.addEventListener('click', () => {
  if (uploadInFlight) return;
  $dz.classList.toggle('is-open');
  if ($dz.classList.contains('is-open')) $dz.scrollIntoView({behavior:'smooth',block:'nearest'});
});
$dz?.addEventListener('click', () => { if (!uploadInFlight) $fi.click(); });
$dz?.addEventListener('dragover', e => {
  e.preventDefault();
  if (!uploadInFlight) $dz.classList.add('drag-over');
});
$dz?.addEventListener('dragleave', () => $dz.classList.remove('drag-over'));
$dz?.addEventListener('drop', e => {
  e.preventDefault();
  $dz.classList.remove('drag-over');
  if (!uploadInFlight) doUpload(e.dataTransfer.files);
});
$fi?.addEventListener('change', () => doUpload($fi.files));
async function doUpload(files) {
  if (uploadInFlight || !files?.length || !$uf) return;

  const selectedFiles = Array.from(files);
  const batches = [];
  for (let offset = 0; offset < selectedFiles.length; offset += UPLOAD_BATCH_SIZE) {
    batches.push(selectedFiles.slice(offset, offset + UPLOAD_BATCH_SIZE));
  }

  if ($fi) $fi.value = '';
  setUploadBusy(true);
  setUploadProgress(0, `Mengunggah 0/${selectedFiles.length} file... 0%`);

  let uploadedTotal = 0;
  let finalRedirect = null;
  let activeBatch = 0;

  try {
    for (activeBatch = 0; activeBatch < batches.length; activeBatch++) {
      const result = await sendUploadBatch(
        batches[activeBatch],
        activeBatch,
        batches.length
      );
      uploadedTotal += result.uploadedCount;
      finalRedirect = result.redirect;
    }

    if (uploadedTotal !== selectedFiles.length || !finalRedirect) {
      throw new Error('Jumlah file tersimpan tidak sesuai dengan file yang dipilih.');
    }

    setUploadProgress(
      100,
      `${uploadedTotal} file berhasil disimpan. Membuka folder...`
    );
    if ($fi) $fi.value = '';
    setUploadBusy(false);
    window.location.assign(finalRedirect.href);
  } catch (error) {
    const batchNumber = Math.min(activeBatch + 1, batches.length);
    const savedNote = uploadedTotal > 0
      ? ` ${uploadedTotal} file dari batch sebelumnya sudah tersimpan.`
      : '';
    setUploadError(
      `Batch ${batchNumber}/${batches.length} gagal. ${error?.message || 'Upload gagal.'}${savedNote}`
    );
  }
}

/* ── Trash ── */
document.getElementById('btnTrash')?.addEventListener('click', () => openModal('modalTrash'));

/* ── Context Menu ── */
const $ctx = document.getElementById('dvCtx');
let ctxType=null, ctxId=null, ctxName=null, ctxElement=null;
function openCtx(e, item) {
  e.preventDefault(); e.stopPropagation();
  ctxElement=item;
  ctxType=item?.dataset.type ?? null;
  ctxId=item?.dataset.id ?? null;
  ctxName=item?.dataset.name ?? '';
  const isFile = ctxType === 'file';
  document.getElementById('ctxOpen').style.display    = '';
  document.getElementById('ctxPreview').style.display = isFile  ? '' : 'none';
  document.getElementById('ctxDownload').style.display= isFile  ? '' : 'none';
  document.getElementById('ctxAdminSep').style.display = IS_ADMIN ? '' : 'none';
  document.getElementById('ctxRename').style.display   = IS_ADMIN ? '' : 'none';
  document.getElementById('ctxMove').style.display     = IS_ADMIN ? '' : 'none';
  document.getElementById('ctxCopy').style.display     = IS_ADMIN && isFile ? '' : 'none';
  document.getElementById('ctxDangerSep').style.display= IS_ADMIN ? '' : 'none';
  document.getElementById('ctxDelete').style.display   = IS_ADMIN ? '' : 'none';
  $ctx.style.left='0'; $ctx.style.top='0';
  $ctx.classList.add('is-open');
  const vw=window.innerWidth, vh=window.innerHeight;
  const w=$ctx.offsetWidth, h=$ctx.offsetHeight;
  $ctx.style.left = Math.min(e.clientX, vw-w-8) + 'px';
  $ctx.style.top  = Math.min(e.clientY, vh-h-8) + 'px';
}
document.addEventListener('click',      () => $ctx.classList.remove('is-open'));
document.addEventListener('keydown',    e  => { if (e.key==='Escape') $ctx.classList.remove('is-open'); });

document.getElementById('ctxOpen').addEventListener('click',     () => openItem(ctxElement));
document.getElementById('ctxPreview').addEventListener('click',  () => openItem(ctxElement));
document.getElementById('ctxDownload').addEventListener('click', () => {
  if (ctxElement?.dataset.downloadUrl) window.location.assign(ctxElement.dataset.downloadUrl);
});

document.getElementById('ctxRename').addEventListener('click', () => {
  document.getElementById('renameMTitle').innerHTML = `<i class="fas fa-pen" style="color:var(--d-blue);"></i> Rename ${ctxType==='folder'?'Folder':'File'}`;
  document.getElementById('renameInp').value = ctxName;
  document.getElementById('renameForm').action = ctxElement?.dataset.renameUrl ?? '';
  openModal('modalRename');
  setTimeout(() => { const i=document.getElementById('renameInp'); i.focus(); i.select(); }, 120);
});

document.getElementById('ctxMove').addEventListener('click', () => {
  document.getElementById('mcForm').action = ctxElement?.dataset.moveUrl ?? '';
  document.getElementById('mcAction').value = 'move';
  document.getElementById('mcTitle').innerHTML = '<i class="fas fa-folder-open" style="color:#f6c341;"></i> Pindahkan ke';
  document.getElementById('mcSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Pindahkan';
  openModal('modalMoveCopy');
});

document.getElementById('ctxCopy').addEventListener('click', () => {
  document.getElementById('mcForm').action = ctxElement?.dataset.copyUrl ?? '';
  document.getElementById('mcAction').value = 'copy';
  document.getElementById('mcTitle').innerHTML = '<i class="fas fa-copy" style="color:var(--d-blue);"></i> Salin ke';
  document.getElementById('mcSubmitBtn').innerHTML = '<i class="fas fa-check"></i> Salin Ke Sini';
  openModal('modalMoveCopy');
});

document.getElementById('ctxDelete').addEventListener('click', () => {
  const consequence = ctxType === 'folder'
    ? 'Folder beserta seluruh isinya akan dihapus permanen.'
    : 'File akan masuk ke Sampah dan masih dapat dipulihkan.';
  if (!confirm(`Hapus ${ctxType==='folder'?'folder':'file'} "${ctxName}"?\n${consequence}`)) return;
  if (ctxElement?.dataset.deleteUrl) postForm(ctxElement.dataset.deleteUrl, 'DELETE');
});

/* ── Folder Tree ── */
function selectTree(el, id) {
  document.querySelectorAll('.dv-tree-item.sel').forEach(i => i.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('mcDestId').value = id;
}

/* ── Preview ── */
function openItem(item) {
  if (!item) return;
  if (item.dataset.type === 'folder') {
    if (item.dataset.openUrl) window.location.assign(item.dataset.openUrl);
    return;
  }

  switch (item.dataset.mode) {
    case 'office':
      window.location.assign(item.dataset.officeUrl);
      break;
    case 'spreadsheet':
      window.location.assign(item.dataset.editorUrl);
      break;
    case 'document':
      window.location.assign(item.dataset.documentUrl);
      break;
    case 'pdf':
      window.open(item.dataset.previewUrl, '_blank', 'noopener,noreferrer');
      break;
    case 'image':
      openImagePreview(item);
      break;
    default:
      window.location.assign(item.dataset.downloadUrl);
  }
}

function openImagePreview(item) {
  document.getElementById('previewTitle').textContent = item.dataset.name ?? 'Preview gambar';
  document.getElementById('previewDlBtn').href = item.dataset.downloadUrl;
  const body = document.getElementById('previewBody');
  const image = document.createElement('img');
  image.src = item.dataset.previewUrl;
  image.alt = item.dataset.name ?? 'Gambar';
  image.loading = 'eager';
  body.replaceChildren(image);
  openModal('modalPreview');
}

/* ── Helper ── */
function postForm(url, method) {
  const f=document.createElement('form'); f.method='POST'; f.action=url;
  const c=document.createElement('input'); c.type='hidden'; c.name='_token'; c.value=CSRF;
  const m=document.createElement('input'); m.type='hidden'; m.name='_method'; m.value=method;
  f.appendChild(c); f.appendChild(m); document.body.appendChild(f); f.submit();
}

/* ── Auto-dismiss flash ── */
setTimeout(() => { document.getElementById('dvFlash')?.remove(); document.getElementById('dvFlash2')?.remove(); }, 4000);

/* ── Keyboard shortcuts ── */
document.addEventListener('keydown', e => {
  if (e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA') return;
  if (e.key==='g') setView('grid');
  if (e.key==='l') setView('list');
  if (e.key==='n' && IS_ADMIN) { e.preventDefault(); document.getElementById('btnNewFolder')?.click(); }
  if (e.key==='u' && IS_ADMIN) { e.preventDefault(); document.getElementById('btnUpload')?.click(); }
});
</script>
@endsection
