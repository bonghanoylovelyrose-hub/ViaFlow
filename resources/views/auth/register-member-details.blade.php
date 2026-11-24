@extends('layouts.app')

@section('title', 'Existing VVTHOA Membership Verification')

@push('styles')
<style>
    .register-step-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        background:
            linear-gradient(135deg, rgba(245, 250, 252, 0.88), rgba(216, 237, 255, 0.78)),
            url('{{ asset('images/BGfinal.jpg') }}') center/cover no-repeat;
    }

    .register-step-shell {
        width: min(880px, 100%);
        background: rgba(255, 255, 255, 0.95);
        border-radius: 1.25rem;
        box-shadow: 0 28px 75px rgba(50, 165, 31, 0.18);
        padding: 1.75rem;
    }

    .register-step-head {
        text-align: center;
        margin-bottom: 1.25rem;
    }

    .register-step-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: rgba(7, 84, 200, 0.1);
        color: var(--vf-royal);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 0.9rem;
    }

    .register-step-head h1 {
        font-size: clamp(1.35rem, 2.5vw, 1.7rem);
        font-weight: 750;
        margin-bottom: 0.35rem;
        color: var(--vf-navy);
    }

    .register-step-copy {
        color: var(--vf-navy);
        margin-bottom: 0;
    }

    .register-section {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 1rem;
        padding: 0.9rem;
        margin-bottom: 0.75rem;
        background: #fbfdfe;
    }

    .register-section-title {
        font-weight: 700;
        color: var(--vf-navy);
        margin-bottom: 0.3rem;
    }

    .register-section-copy {
        color: var(--vf-navy);
        font-size: 0.84rem;
        line-height: 1.45;
        margin-bottom: 0.7rem;
    }

    .register-choice-card {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.9rem;
        min-height: 2.7rem;
        height: 100%;
        padding: 0.55rem 0.7rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 0.86rem;
        line-height: 1.3;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
        background: #fff;
    }

    /* Kept in the layout (so it stays focusable and keyboard reachable) but
       visually hidden. Using display:none here would make the control
       impossible to tab to, which is why an answer could be missed. */
    .register-choice-input {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .register-choice-input:focus-visible + .register-choice-card {
        outline: 3px solid rgba(7, 84, 200, 0.55);
        outline-offset: 2px;
    }

    .register-choice-card:hover {
        border-color: rgba(7, 84, 200, 0.45);
    }

    .register-choice-input:checked + .register-choice-card {
        border-color: var(--vf-royal);
        background: rgba(7, 84, 200, 0.06);
        box-shadow: 0 0 0 0.22rem rgba(7, 84, 200, 0.12);
        font-weight: 600;
    }

    .register-group-error {
        border-left: 3px solid #dc3545;
        padding-left: 0.75rem;
    }

    .register-choice-pair {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .register-choice-grid,
    .register-question-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.65rem;
        align-items: stretch;
    }

    .register-choice-grid > .col-md-6,
    .register-question-grid > .col-md-6 {
        width: auto;
        padding-inline: 0;
    }

    .register-question {
        display: flex;
        min-width: 0;
        flex-direction: column;
    }

    .register-question-label {
        min-height: 2.6rem;
        display: flex;
        align-items: flex-start;
        margin-bottom: 0.45rem !important;
        font-size: 0.88rem;
        line-height: 1.35;
    }

    .register-question .register-choice-pair {
        flex: 1 1 auto;
    }

    .register-question-helper {
        margin: 0.55rem 0 0;
        color: var(--vf-navy);
        font-size: 0.78rem;
        line-height: 1.35;
    }

    .register-checklist {
        display: grid;
        gap: 0.55rem;
    }

    .register-checklist > .form-check {
        display: flex;
        align-items: center;
        min-height: 2.45rem;
        margin: 0;
        padding: 0.45rem 0.65rem 0.45rem 2rem;
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.75rem;
        background: #fff;
    }

    .register-required-badge {
        display: inline-flex;
        align-items: center;
        margin-left: 0.45rem;
        padding: 0.18rem 0.5rem;
        border-radius: 999px;
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .document-upload-panel {
        display: none;
        border: 1px solid rgba(7, 84, 200, 0.18);
        border-radius: 0.9rem;
        padding: 0.8rem;
        background: #ffffff;
    }

    .document-upload-panel.is-visible {
        display: block;
    }

    .document-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }

    .document-preview-card {
        border: 1px solid rgba(216, 237, 255, 0.95);
        border-radius: 0.8rem;
        padding: 0.65rem;
        background: #fbfdfe;
    }

    .document-preview-card img {
        display: block;
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: contain;
        border-radius: 0.55rem;
        background: #fff;
        border: 1px solid rgba(216, 237, 255, 0.7);
    }

    .document-preview-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.45rem;
        margin-top: 0.55rem;
    }

    .register-step-primary {
        height: 3rem;
        border: 0;
        border-radius: 0.85rem;
        font-weight: 600;
        background: linear-gradient(135deg, var(--vf-royal), var(--vf-royal));
        box-shadow: 0 14px 28px rgba(7, 84, 200, 0.22);
    }

    .register-step-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.1rem;
    }

    .register-step-actions .btn {
        flex: 1 1 0;
        height: 3rem;
    }

    @media (max-width: 767.98px) {
        .register-step-shell {
            padding: 1.35rem 1rem;
            border-radius: 1rem;
        }

        .register-step-actions {
            flex-direction: column-reverse;
        }

        .register-choice-grid,
        .register-question-grid {
            grid-template-columns: 1fr;
        }

        .register-question-label {
            min-height: 0;
        }
    }
</style>
@include('auth.application-styles')
@endpush

@section('body')
@component('auth.application-shell', ['pageClass' => 'register-step-page', 'shellClass' => 'register-step-shell'])
        <div class="register-step-head">
            <span class="register-step-pill">
                <i class="bi bi-signpost-split"></i>
                Step 2 of 3
            </span>
            <h1>Verify Your Existing VVTHOA Membership</h1>
            <p class="register-step-copy">Confirm your membership details for administrator review before continuing.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                <i class="bi bi-exclamation-circle mt-1"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        <form method="POST" action="{{ route('register.member-details.store') }}" enctype="multipart/form-data" id="memberDetailsForm" data-registration-draft="member">
            @csrf

            @include('auth.connection-member-questions')
            @include('auth.connection-member-documents')
            @include('auth.connection-member-agreement')
            <div class="register-step-actions">
                <a href="{{ route('register') }}" class="btn btn-light border" data-registration-back>Back</a>
                <button type="submit" class="btn register-step-primary text-white">Continue to Connection Request</button>
            </div>
        </form>
@endcomponent
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('memberDetailsForm');
        const documentTypeInputs = Array.from(document.querySelectorAll('input[name="membership_document_type"]'));
        const uploadPanel = document.getElementById('membership_document_upload_panel');
        const photoInput = document.getElementById('membership_document_photos');
        const previewGrid = document.getElementById('membership_document_preview_grid');
        const photoError = document.getElementById('membership_document_photo_error');
        const allowedTypes = ['image/jpeg', 'image/png'];
        let selectedFiles = [];

        function selectedDocumentType() {
            return documentTypeInputs.find(function (input) {
                return input.checked;
            });
        }

        function showPhotoError(message) {
            if (!photoError) {
                return;
            }

            photoError.textContent = message || '';
            photoError.style.display = message ? 'block' : 'none';
        }

        function syncInputFiles() {
            if (!photoInput) {
                return;
            }

            const transfer = new DataTransfer();
            selectedFiles.forEach(function (file) {
                transfer.items.add(file);
            });
            photoInput.files = transfer.files;
            form.dispatchEvent(new Event('registration-files-changed'));
        }

        function renderPreviews() {
            if (!previewGrid) {
                return;
            }

            previewGrid.innerHTML = '';

            selectedFiles.forEach(function (file, index) {
                const card = document.createElement('div');
                card.className = 'document-preview-card';

                const image = document.createElement('img');
                image.alt = file.name;
                image.src = URL.createObjectURL(file);
                image.onload = function () {
                    URL.revokeObjectURL(image.src);
                };

                const fileName = document.createElement('div');
                fileName.className = 'small fw-semibold mt-2 text-truncate';
                fileName.textContent = file.name;

                const actions = document.createElement('div');
                actions.className = 'document-preview-actions';

                const replaceButton = document.createElement('button');
                replaceButton.type = 'button';
                replaceButton.className = 'btn btn-sm btn-outline-primary';
                replaceButton.textContent = 'Replace';
                replaceButton.addEventListener('click', function () {
                    const replacementInput = document.createElement('input');
                    replacementInput.type = 'file';
                    replacementInput.accept = '.jpg,.jpeg,.png,image/jpeg,image/png';
                    replacementInput.addEventListener('change', function () {
                        const replacement = replacementInput.files[0];

                        if (!replacement) {
                            return;
                        }

                        if (!allowedTypes.includes(replacement.type)) {
                            showPhotoError('Please upload a valid JPG, JPEG, or PNG image.');
                            return;
                        }

                        selectedFiles[index] = replacement;
                        syncInputFiles();
                        showPhotoError('');
                        renderPreviews();
                    });
                    replacementInput.click();
                });

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-sm btn-outline-danger';
                removeButton.textContent = 'Remove';
                removeButton.addEventListener('click', function () {
                    selectedFiles.splice(index, 1);
                    syncInputFiles();
                    renderPreviews();
                });

                actions.append(replaceButton, removeButton);
                card.append(image, fileName, actions);
                previewGrid.appendChild(card);
            });
        }

        function updateUploadVisibility() {
            const hasSelection = Boolean(selectedDocumentType());
            uploadPanel?.classList.toggle('is-visible', hasSelection);

            if (photoInput) {
                photoInput.required = hasSelection;
            }
        }

        documentTypeInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                documentTypeInputs.forEach(function (documentTypeInput) {
                    documentTypeInput.setCustomValidity('');
                });
                updateUploadVisibility();
                showPhotoError('');
            });
        });

        photoInput?.addEventListener('change', function () {
            const files = Array.from(photoInput.files || []);
            const invalidFile = files.find(function (file) {
                return !allowedTypes.includes(file.type);
            });

            if (invalidFile) {
                selectedFiles = [];
                photoInput.value = '';
                showPhotoError('Please upload a valid JPG, JPEG, or PNG image.');
                renderPreviews();
                return;
            }

            selectedFiles = files;
            showPhotoError('');
            renderPreviews();
        });

        form?.addEventListener('submit', function (event) {
            if (!selectedDocumentType()) {
                event.preventDefault();
                documentTypeInputs[0]?.setCustomValidity('Please select one document type.');
                documentTypeInputs[0]?.reportValidity();
                return;
            }

            documentTypeInputs.forEach(function (input) {
                input.setCustomValidity('');
            });

            if (selectedFiles.length === 0) {
                event.preventDefault();
                showPhotoError('Please attach at least one photo of the selected document.');
                photoInput?.focus();
                return;
            }

            const invalidFile = selectedFiles.find(function (file) {
                return !allowedTypes.includes(file.type);
            });

            if (invalidFile) {
                event.preventDefault();
                showPhotoError('Please upload a valid JPG, JPEG, or PNG image.');
            }
        });

        updateUploadVisibility();
    });
</script>
@endpush

@push('scripts')
    @include('auth.registration-draft')
@endpush
