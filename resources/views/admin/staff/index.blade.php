@extends('layouts.app')

@php($activePage = 'staff')

@section('title', 'Staff Management')

@push('styles')
<style>
    #staffModal .staff-password-group { display: flex; }
    #staffModal .staff-password-group .form-control { min-width: 0; border-radius: .8rem 0 0 .8rem !important; }
    #staffModal .staff-password-toggle { flex: 0 0 2.8rem; min-height: 2.7rem; border: 1px solid var(--wb-border); border-left: 0; border-radius: 0 .8rem .8rem 0 !important; background: #fff; color: var(--wb-primary); }
    #staffModal .staff-password-toggle:focus-visible { outline: 2px solid var(--wb-primary); outline-offset: 2px; }
    .staff-remove-btn {
        background: #fce8e8 !important;
        border-color: #efb4b4 !important;
        color: #9b2525 !important;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }
    .staff-remove-btn i { color: #b42323; }
    .staff-remove-btn:hover, .staff-remove-btn:focus-visible {
        background: #f4cccc !important;
        border-color: #d77b7b !important;
        color: #7f1d1d !important;
    }
    @media (prefers-reduced-motion: reduce) { .staff-remove-btn { transition: none; } }
</style>
@endpush

@section('body')
<div class="app-shell d-lg-flex">
    @include('layouts.sidebar', ['activePage' => $activePage])
    <main class="main-content flex-grow-1">
        @include('layouts.navbar')
        <div class="content-wrapper">
            <div class="page-heading">
                <div>
                    <h1>Staff Management</h1>
                    <p>Manage staff and meter reader accounts.</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal">
                    <i class="bi bi-person-badge me-2"></i>Add Meter Reader
                </button>
            </div>

            <div class="panel-card p-4" data-admin-records-root>
                @if (session('success'))
                    <div class="alert alert-success d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-check-circle mt-1"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" data-admin-freeze>
                        <i class="bi bi-exclamation-circle mt-1"></i>
                        <div>{{ $errors->first() }}</div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Access Scope</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($staffMembers as $staffMember)
                                <tr>
                                    <td class="fw-semibold">{{ $staffMember->name }}</td>
                                    <td>{{ $staffMember->email }}</td>
                                    <td>
                                        <span class="badge {{ $staffMember->role === 'admin' ? 'badge-soft-primary' : 'badge-soft-warning' }}">
                                            {{ $staffMember->role === 'admin' ? 'Admin' : 'Meter Reader' }}
                                        </span>
                                    </td>
                                    <td class="text-secondary">
                                        {{ $staffMember->role === 'admin' ? 'Full management access' : 'Meter reading access' }}
                                    </td>
                                    <td class="text-end">
                                        @if ($staffMember->isMeterReader() && ! auth()->user()->is($staffMember))
                                            <button type="button" class="btn btn-sm staff-remove-btn" data-bs-toggle="modal" data-bs-target="#removeStaffModal" data-name="{{ $staffMember->name }}" data-email="{{ $staffMember->email }}" data-action="{{ route('staff.destroy', $staffMember) }}"><i class="bi bi-trash3" aria-hidden="true"></i> Remove</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-5 text-secondary">No accounts found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="removeStaffModal" tabindex="-1" aria-labelledby="removeStaffTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="removeStaffTitle">Remove Meter Reader?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This meter reader will no longer be able to log in. Their existing records will be retained.</p>
                <div class="p-3 rounded-3 border bg-light">
                    <div class="fw-semibold" id="removeStaffName"></div>
                    <div class="text-secondary text-break" id="removeStaffEmail"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="removeStaffForm">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-primary">Confirm Removal</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Create Meter Reader Account</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('staff.store') }}" id="staffCreateForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="reader@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="role">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="meter_reader" @selected(old('role', 'meter_reader') === 'meter_reader')>Meter Reader</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="password">Password</label>
                            <div class="input-group staff-password-group">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Create password" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="staff-password-toggle" data-password-target="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="password_confirmation">Repeat Password</label>
                            <div class="input-group staff-password-group">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="staff-password-toggle" data-password-target="password_confirmation" aria-label="Show repeat password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="staffCreateForm" class="btn btn-primary">Save Meter Reader</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        const staffPassword = document.getElementById('password');
        const staffPasswordConfirmation = document.getElementById('password_confirmation');
        function validateStaffPasswords() {
            staffPasswordConfirmation.setCustomValidity(staffPasswordConfirmation.value && staffPassword.value !== staffPasswordConfirmation.value ? 'Passwords do not match.' : '');
        }
        staffPassword.addEventListener('input', validateStaffPasswords);
        staffPasswordConfirmation.addEventListener('input', validateStaffPasswords);
        document.querySelectorAll('#staffModal .staff-password-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.dataset.passwordTarget);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(show));
                button.setAttribute('aria-label', (show ? 'Hide ' : 'Show ') + (input.id === 'password_confirmation' ? 'repeat password' : 'password'));
                button.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });
        const removeStaffModal = document.getElementById('removeStaffModal');
        removeStaffModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('removeStaffName').textContent = button.dataset.name;
            document.getElementById('removeStaffEmail').textContent = button.dataset.email;
            document.getElementById('removeStaffForm').action = button.dataset.action;
        });
    </script>
    @if ($errors->any())
        <script>
            const staffModalElement = document.getElementById('staffModal');
            if (staffModalElement) {
                const staffModal = new bootstrap.Modal(staffModalElement);
                staffModal.show();
            }
        </script>
    @endif
@endpush
