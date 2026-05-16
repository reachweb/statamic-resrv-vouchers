@extends('statamic::layout')
@section('title', __('Scan Voucher'))
@section('wrapper_class', 'page-wrapper max-w-md')

@section('content')

    <div class="flex mb-3">
        <h1 class="flex-1">{{ __('Scan Voucher') }}</h1>
    </div>

    <div>
        <voucher-scanner></voucher-scanner>
    </div>

@endsection
