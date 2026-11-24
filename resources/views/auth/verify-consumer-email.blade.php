@extends('layouts.app')
@section('title', 'Verify Email')
@section('body')
<main class="container py-5" style="max-width: 560px">
    <div class="panel-card p-4">
        <h1 class="h3">Verify Your Email</h1>
        <p>Confirm that you can receive email at <strong>{{ $email }}</strong>.</p>
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ $formAction }}">@csrf<button type="submit" class="btn btn-primary">Confirm Email</button></form>
    </div>
</main>
@endsection
