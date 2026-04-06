@extends('layouts.admin')

@section('title', 'Input Data')

@section('content')
    @include('input.partials.styles')
    @include('input.partials.hero')
    @include('input.partials.form')
    @include('input.partials.preview')
@endsection

@section('scripts')
    @include('input.partials.scripts')
@endsection
