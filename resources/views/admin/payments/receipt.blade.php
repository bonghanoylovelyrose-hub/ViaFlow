@extends('layouts.app')

@php($activePage = 'payments')

@section('title', 'Digital Receipt')

@push('styles')
<style>
    .receipt-details-table th,
    .receipt-details-table td {
        border-left: 0 !important;
        border-right: 0 !important;
    }
</style>
@endpush

@section('body')
    @include('shared.payments.receipt-ui', ['showAdminPaymentDates' => true])
@endsection
