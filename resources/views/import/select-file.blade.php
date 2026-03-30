@extends('layouts.admin')

@section('title', 'Pilih File')

@section('content')

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Pilih File dari RAR</h5>
    </div>

    <div class="card-body">

        <!-- 🔥 PERBAIKAN: Ubah action="#" menjadi action="{{ route('import.preview') }}" -->
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
            </div>

            <button type="submit" class="btn btn-success">
                Lanjut Preview
            </button>

        </form>

    </div>
</div>

@endsection