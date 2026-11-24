<main class="application-page {{ $pageClass }}">
    <div class="application-shell {{ $shellClass }}">
        <div class="application-waves" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="application-shell-content">
            <x-organization-logo size="auth" />
            {{ $slot }}
        </div>
    </div>
</main>
