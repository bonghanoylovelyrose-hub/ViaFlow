@extends('layouts.app')

@php($activePage = 'billing')

@section('title', 'Billing Statement')

@section('body')
    @include('shared.billing.statement-page')
@endsection
