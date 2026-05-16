@extends('statamic::layout')
@section('title', __('Resrv Vouchers'))
@section('wrapper_class', 'page-wrapper max-w-xl')

@section('content')

    <div class="flex mb-3">
        <h1 class="flex-1">{{ __('Vouchers') }}</h1>
    </div>

    <div>
        <vouchers-list></vouchers-list>
    </div>

@endsection
