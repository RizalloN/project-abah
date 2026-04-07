@extends('layouts.admin')

@section('title', 'Pilih File')

@section('content')

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Pilih File Import</h5>
    </div>

    <div class="card-body">

        <!-- 🔥 PERBAIKAN: Ubah action="#" menjadi action="{{ route('import.preview') }}" -->
        @if(!empty($files))
            <form method="POST" action="{{ session('import_type') === 'brimo' ? route('import.brimo.preview') : route('import.preview') }}">
                @csrf

                <div class="form-group">
                    <label>Pilih File</label>
                    <select name="file_path" class="form-control">
                        @foreach($files as $file)
                            <option value="{{ $file['path'] }}">
                                {{ $file['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-2">Jika upload dilakukan langsung dalam format `.csv`, daftar ini biasanya hanya berisi satu file.</small>
                </div>

                <button type="submit" class="btn btn-success">
                    Lanjut Preview
                </button>
            </form>
        @else
            <div class="alert alert-warning mb-0">
                Tidak ada file yang berhasil dibaca dari upload sebelumnya. Silakan kembali dan upload ulang file import.
            </div>
        @endif

    </div>
</div>

@endsection
