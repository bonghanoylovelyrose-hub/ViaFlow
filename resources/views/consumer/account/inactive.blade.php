@extends('layouts.app')

@section('title', 'Account Disconnection')

@push('styles')
    <style>
        .disconnection-screen { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; background: rgba(6, 43, 128, .65); }
        .disconnection-popup { width: 100%; max-width: 720px; background: #fff; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0, 0, 0, .2); }
    </style>
@endpush

@section('body')
<main class="disconnection-screen">
    <section class="disconnection-popup p-4" id="inactiveAccountNotice" role="alertdialog" aria-modal="true" aria-labelledby="inactiveAccountNoticeTitle" aria-describedby="inactiveAccountNoticeBody">
        <h1 class="h4 mb-4" id="inactiveAccountNoticeTitle">Account Disconnection</h1>
        <div id="inactiveAccountNoticeBody">
            @include('consumer.account.summary')
        </div>
        <form method="POST" action="{{ route('logout') }}" class="text-end">
            @csrf
            <button type="submit" class="btn btn-primary">Log Out</button>
        </form>
    </section>
</main>
@endsection

@push('scripts')
    <script>
        const accountBalanceAmounts = document.querySelectorAll('#inactiveAccountNotice [data-account-balance]');
        const accountBalanceFormat = new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        async function checkPortalAccess() {
            try {
                const response = await fetch(@json(route('account.access-status')), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (response.status === 401) {
                    window.location.replace(@json(route('login')));
                    return;
                }
                if (response.ok) {
                    const { portal_access, balances } = await response.json();
                    if (portal_access === true) {
                        window.location.replace(@json(route('dashboard')));
                        return;
                    }
                    for (const element of accountBalanceAmounts) {
                        const amount = balances?.[element.dataset.accountBalance];
                        if (Number.isFinite(amount)) {
                            element.textContent = 'PHP ' + accountBalanceFormat.format(amount);
                        }
                    }
                }
            } catch (error) {
            }
            window.setTimeout(checkPortalAccess, 15000);
        }
        window.setTimeout(checkPortalAccess, 15000);
    </script>
@endpush
