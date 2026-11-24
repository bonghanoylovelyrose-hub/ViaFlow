@extends('layouts.app')

@php($activePage = 'billing')

@section('title', 'Billing')

@section('body')
    @include('shared.billing.index-ui')
@endsection

