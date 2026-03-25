@extends('layouts.admin')

@section('title', 'Import Data')

@section('content')

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Upload Data Report</h5>
    </div>

    <form method="POST" action="{{ route('import.upload') }}" enctype="multipart/form-data">
        @csrf

        <div class="card-body">

            <!-- Nama Report -->
            <div class="form-group">
                <label>Nama Report</label>
                <select name="id_report" class="form-control" required>
                    <option value="">-- Pilih Report --</option>
                    @foreach($reports as $report)
                        <option value="{{ $report->id_report }}">
                            {{ $report->nama_report }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Upload -->
            <div class="form-group">
                <label>Upload File (.rar)</label>
                <input type="file" name="file" class="form-control" required>
            </div>

        </div>

        <div class="card-footer">
            <button class="btn btn-primary">
                <i class="fas fa-upload"></i> Process
            </button>
        </div>

    </form>

</div>

@endsection