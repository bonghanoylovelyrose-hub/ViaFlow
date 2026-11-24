@include('admin.consumers.inactive-account-styles')

<nav class="inactive-account-tabs" aria-label="Consumer account views">
    @foreach (['inactive' => 'Inactive Accounts', 'disconnection_history' => 'Disconnection History'] as $category => $label)
        <a class="inactive-account-tab"
            href="{{ route('consumers.index', ['account_category' => $category]) }}"
            @if ($filters['account_category'] === $category) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
