@push('styles')
    <style>
        @include('auth.connection-membership-choice-styles')

        #consumerCreateForm [hidden] { display: none !important; }
        #consumerCreateForm .register-step-card { background: var(--vf-white); min-height: 7rem; }
        #consumerCreateForm .register-step-option:checked + .register-step-card { background: var(--vf-background); }
        #consumerCreateForm :is(.register-step-option, .register-choice-input):focus-visible + span { outline: 3px solid var(--vf-bright); }
        #consumerCreateForm .register-section {
            padding: .9rem;
            margin-bottom: .75rem;
            border: 1px solid var(--vf-light);
            border-radius: .75rem;
            background: var(--vf-background);
        }
        #consumerCreateForm :is(.register-section-title, .role-register-section-title) { font-weight: 700; color: var(--vf-navy); }
        #consumerCreateForm :is(.register-section-copy, .role-register-section-copy) { margin: .25rem 0 .65rem; font-size: .875rem; }
        #consumerCreateForm :is(.register-choice-grid, .register-question-grid, .register-choice-pair) {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .5rem;
        }
        #consumerCreateForm :is(.register-choice-grid, .register-question-grid) > div { width: auto; }
        #consumerCreateForm .register-choice-input { position: absolute; opacity: 0; }
        #consumerCreateForm .register-question-helper { margin: .6rem 0 0; font-size: .875rem; }
        #consumerCreateForm .register-checklist { display: grid; gap: .65rem; }
        @media (max-width: 575.98px) {
            #consumerCreateForm :is(.register-choice-grid, .register-question-grid) { grid-template-columns: 1fr; }
        }
    </style>
@endpush

<div class="modal fade" id="consumerModal" tabindex="-1" aria-labelledby="consumerApplicationTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('consumers.store') }}" enctype="multipart/form-data" class="modal-content border-0 rounded-4"
              id="consumerCreateForm" data-invalid-field="{{ session('consumer_modal') === 'single' ? array_key_first($errors->getMessages()) : '' }}">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="consumerApplicationTitle">Apply for Connection</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ol class="list-unstyled d-flex gap-2 justify-content-between flex-wrap mb-3" aria-label="Application steps">
                    <li class="badge badge-soft-primary" data-application-indicator aria-current="step">1. Membership</li>
                    <li class="badge badge-soft-neutral" data-application-indicator>2. Consumer Information</li>
                    <li class="badge badge-soft-neutral" data-application-indicator>3. Requirements</li>
                </ol>

                @if (session('consumer_modal') === 'single' && $errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <section data-application-step>
                    <h3 class="h5 mb-3 text-center">Are you a VVTHOA member?</h3>
                    @include('auth.connection-membership-choice')
                </section>

                <section data-application-step hidden>
                    <div class="register-section">
                        <h3 class="h6 register-section-title">Consumer Information</h3>
                        @include('auth.connection-applicant-fields', ['serviceArea' => config('service_area')])
                    </div>
                    <div class="register-section">
                        <h3 class="h6 register-section-title">Connection Details</h3>
                        <div class="row g-3">
                            @include('auth.connection-property-fields')
                        </div>
                    </div>
                    <fieldset data-membership-answer="yes" hidden disabled>
                        @include('auth.connection-member-questions', ['declaredMember' => true])
                    </fieldset>
                </section>

                <section data-application-step hidden>
                    <h3 class="h6 mb-2">Requirements Submission</h3>
                    <p class="small text-secondary">Upload the documents the applicant brought. If a required document is missing, the application will be saved as Pending Requirements and the applicant will receive an upload link.</p>
                    <div class="register-section">
                        <div class="row g-2">
                            @include('auth.connection-valid-id-type', ['allowMissingDocuments' => true])
                            @include('auth.connection-valid-id-upload', ['allowMissingDocuments' => true])
                        </div>
                    </div>
                    <fieldset data-membership-answer="yes" hidden disabled>
                        @include('auth.connection-member-documents', [
                            'allowMissingDocuments' => true,
                            'membershipDocumentHeading' => 'VVTHOA Membership Document',
                        ])
                        @include('auth.connection-member-agreement')
                    </fieldset>
                    <fieldset data-membership-answer="no" hidden disabled>
                        @include('auth.connection-nonmember-acknowledgment')
                    </fieldset>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-light border" data-application-back hidden>Back</button>
                <button type="button" class="btn btn-primary" data-application-next>Continue</button>
                <button type="submit" class="btn btn-primary" data-application-submit hidden>Submit Application</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('consumerCreateForm');
            const steps = [...form.querySelectorAll('[data-application-step]')];
            const indicators = [...form.querySelectorAll('[data-application-indicator]')];
            const back = form.querySelector('[data-application-back]');
            const next = form.querySelector('[data-application-next]');
            const submit = form.querySelector('[data-application-submit]');
            let step = 0;

            function showStep(index) {
                step = index;
                steps.forEach((panel, position) => { panel.hidden = index !== position; });
                indicators.forEach(function (indicator, position) {
                    indicator.classList.toggle('badge-soft-primary', position === index);
                    indicator.classList.toggle('badge-soft-neutral', position !== index);
                    if (position === index) indicator.setAttribute('aria-current', 'step');
                    else indicator.removeAttribute('aria-current');
                });
                back.hidden = index === 0;
                submit.hidden = index < steps.length - 1;
                next.hidden = ! submit.hidden;
                form.querySelector('.modal-body').scrollTop = 0;
            }

            function updateRequirements() {
                const member = form.querySelector('[name="is_viohoa_member"]:checked')?.value;
                form.querySelectorAll('[data-membership-answer]').forEach(function (fieldset) {
                    fieldset.hidden = fieldset.disabled = fieldset.dataset.membershipAnswer !== member;
                });
                form.querySelector('#valid_id_presented').required = form.querySelector('#valid_id_file').files.length > 0;
                form.querySelectorAll('[name="membership_document_type"]').forEach(function (input) {
                    input.required = form.querySelector('#membership_document_photos').files.length > 0;
                });
            }

            function validateStep(index) {
                const invalid = [...steps[index].querySelectorAll('input, select, textarea')]
                    .find(control => control.willValidate && ! control.checkValidity());
                if (! invalid) return true;
                showStep(index);
                invalid.reportValidity();
                return false;
            }

            form.noValidate = true;
            form.addEventListener('change', updateRequirements);
            back.addEventListener('click', () => showStep(step - 1));
            next.addEventListener('click', () => {
                if (validateStep(step)) showStep(step + 1);
            });
            form.addEventListener('submit', function (event) {
                if (step < steps.length - 1) {
                    event.preventDefault();
                    next.click();
                } else if (! steps.every((panel, position) => validateStep(position))) {
                    event.preventDefault();
                } else {
                    submit.disabled = true;
                }
            });
            form.querySelector('.js-digits-only').addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 15);
            });

            updateRequirements();
            const invalidField = form.dataset.invalidField.split('.')[0];
            const field = [...form.elements].find(control => control.name === invalidField || control.name === invalidField + '[]');
            showStep(Math.max(0, steps.indexOf(field?.closest('[data-application-step]'))));
        });
    </script>
@endpush
