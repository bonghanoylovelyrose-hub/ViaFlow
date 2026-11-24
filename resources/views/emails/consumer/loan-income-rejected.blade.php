<p>Hello {{ $loan->consumer?->full_name ?? 'Consumer' }},</p>
<p>Your proof of income for loan application #{{ $loan->id }} needs correction for the following reason:</p>
<p>{{ $reason }}</p>
<p>Your loan application has not been rejected. Please correct the issue and resubmit your proof of income for review. Your loan application remains pending.</p>
<p><a href="{{ route('loans.index') }}">Open your loan application</a></p>
<p>ViaFlow</p>
