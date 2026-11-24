@extends('layouts.app')

@php($activePage = 'billing-loan')

@section('title', 'Loan Billing')

@section('body')
    @include('shared.billing.loan-index')
@endsection
