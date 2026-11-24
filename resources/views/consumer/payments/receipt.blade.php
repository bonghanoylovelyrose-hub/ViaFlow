@extends('layouts.app')

@php($activePage = 'payments')

@section('title', 'Digital Receipt')

@section('body')
    @include('shared.payments.receipt-ui')
@endsection
