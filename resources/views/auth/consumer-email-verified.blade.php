@extends('layouts.app')
@section('title', 'Email Verified')
@section('body')
<main class="container py-5" style="max-width: 560px"><div class="panel-card p-4">
    <h1 class="h3">Email Verified</h1>
    <p>{{ $sent ? 'Your new login details have been emailed to this address.' : 'Your email address has been verified.' }}</p>
    <a href="{{ route('login') }}" class="btn btn-primary">Go to Login</a>
</div></main>
@endsection
