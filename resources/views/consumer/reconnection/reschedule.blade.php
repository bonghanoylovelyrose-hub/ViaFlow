@extends('layouts.app')
@section('title', 'Request Reconnection Reschedule')
@section('body')
<main class="container py-5" style="max-width:720px;">
    <div class="panel-card p-4">
        <h1 class="h3">Request for Reschedule</h1>
        <p><strong>{{ $consumer->full_name }}</strong> &middot; Account {{ $consumer->account_number }}</p>
        <p>Current Confirmed Reconnection Schedule: <strong>{{ \App\Support\ReconnectionSchedule::format($scheduledAt) }}</strong> (Philippine time)</p>
        <p>Your confirmed schedule will remain unchanged until an administrator assigns a new schedule.</p>
        <x-reschedule-usage :usage="$rescheduleUsage" type="reconnection" />
        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
        @if (! $rescheduleUsage['available'])
            <button type="button" class="btn btn-secondary" disabled>Request for Reschedule</button>
        @elseif ($pendingRequest)
            <div class="alert alert-info">Your request for {{ $pendingRequest->formattedPreferredSchedule() }} is pending admin approval.</div>
        @else
            <form method="POST" action="{{ $formAction }}">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="preferred-date">Preferred New Date</label>
                        <input type="date" id="preferred-date" name="preferred_date" class="form-control" min="{{ today(\App\Support\ReconnectionSchedule::TIMEZONE)->toDateString() }}" value="{{ old('preferred_date') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="preferred-time">Preferred New Time</label>
                        <input type="time" id="preferred-time" name="preferred_time" class="form-control" value="{{ old('preferred_time') }}" required>
                        <div class="form-text">Philippine local time (Asia/Manila)</div>
                    </div>
                </div>
                <label class="form-label" for="reschedule-reason">Reason for Rescheduling</label>
                <textarea id="reschedule-reason" name="reason" class="form-control mb-3" maxlength="1000" rows="4" required>{{ old('reason') }}</textarea>
                <button type="submit" class="btn btn-primary">Request New Schedule</button>
            </form>
        @endif
        @if ($history->isNotEmpty())
            <h2 class="h5 mt-4">Request History</h2>
            <ul>
                @foreach ($history as $entry)
                    <li>{{ ucfirst($entry->status) }} &middot; Requested {{ $entry->formattedPreferredSchedule() }}
                        @if ($entry->isApproved()) &middot; Confirmed {{ $entry->formattedNewSchedule() }} @endif
                        @if ($entry->admin_notes) &middot; {{ $entry->admin_notes }} @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</main>
@endsection
