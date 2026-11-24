<?php

namespace App\Services;

use App\Models\Consumer;
use App\Rules\FileMimeType;
use Illuminate\Validation\Rule;

/**
 * Single source of truth for the parts of a water connection application an
 * administrator can require the applicant to correct after a rejection.
 *
 * The admin picks the requirements on the rejection form, so the resubmission
 * page can unlock exactly those fields and documents: everything else stays
 * untouched, including every valid uploaded document.
 */
class ConnectionApplicationCorrectionService
{
    public const FIELD_FULL_NAME = 'full_name';

    public const FIELD_EMAIL = 'email';

    public const FIELD_CONTACT_NUMBER = 'contact_number';

    public const FIELD_SERVICE_PUROK = 'service_purok';

    public const FIELD_ADDRESS_BLOCK = 'address_block';

    public const FIELD_ADDRESS_STREET = 'address_street';

    public const FIELD_PROPERTY_TYPE = 'property_type';

    public const FIELD_OWNERSHIP_STATUS = 'ownership_status';

    public const FIELD_VALID_ID_PRESENTED = 'valid_id_presented';

    public const FIELD_OTHER_DOCUMENT_DETAILS = 'other_document_details';

    public const FIELD_VALID_ID_FILE = 'valid_id_file';

    public const FIELD_PROOF_OF_RESIDENCY_FILE = 'proof_of_residency_file';

    public const FIELD_OTHER_DOCUMENT_FILE = 'other_document_file';

    public const FIELD_MEMBERSHIP_DOCUMENTS = 'membership_document_photos';

    /**
     * Used for rejections recorded before administrators could itemise the
     * requirements, or when the free-text reason matches no known field. The
     * applicant must always have something actionable to correct.
     */
    public const FALLBACK_FIELDS = [
        self::FIELD_VALID_ID_FILE,
        self::FIELD_PROOF_OF_RESIDENCY_FILE,
        self::FIELD_ADDRESS_BLOCK,
        self::FIELD_ADDRESS_STREET,
        self::FIELD_CONTACT_NUMBER,
    ];

    public const CONTACT_NUMBER_PATTERN = '/^[0-9]{10,15}$/';

    /**
     * Canonical order and labels shown to the administrator and the applicant.
     */
    private const OPTIONS = [
        self::FIELD_VALID_ID_FILE => 'Valid ID',
        self::FIELD_MEMBERSHIP_DOCUMENTS => 'VVTHOA Membership Document Photos',
        self::FIELD_PROOF_OF_RESIDENCY_FILE => 'Proof of Ownership/Residency',
        self::FIELD_ADDRESS_BLOCK => 'Block',
        self::FIELD_ADDRESS_STREET => 'Street',
        self::FIELD_CONTACT_NUMBER => 'Contact Number',
        self::FIELD_FULL_NAME => 'Full Name',
        self::FIELD_EMAIL => 'Email Address',
        self::FIELD_SERVICE_PUROK => 'Purok',
        self::FIELD_PROPERTY_TYPE => 'Property Type',
        self::FIELD_OWNERSHIP_STATUS => 'Ownership Status',
        self::FIELD_VALID_ID_PRESENTED => 'Valid ID Presented',
        self::FIELD_OTHER_DOCUMENT_DETAILS => 'Other Document Details',
        self::FIELD_OTHER_DOCUMENT_FILE => 'Other Document',
    ];

    private const TEXT_FIELDS = [
        self::FIELD_FULL_NAME,
        self::FIELD_EMAIL,
        self::FIELD_CONTACT_NUMBER,
        self::FIELD_SERVICE_PUROK,
        self::FIELD_ADDRESS_BLOCK,
        self::FIELD_ADDRESS_STREET,
        self::FIELD_PROPERTY_TYPE,
        self::FIELD_OWNERSHIP_STATUS,
        self::FIELD_VALID_ID_PRESENTED,
        self::FIELD_OTHER_DOCUMENT_DETAILS,
    ];

    private const DOCUMENT_COLUMNS = [
        self::FIELD_VALID_ID_FILE => 'valid_id_path',
        self::FIELD_PROOF_OF_RESIDENCY_FILE => 'proof_of_residency_path',
        self::FIELD_OTHER_DOCUMENT_FILE => 'other_document_path',
    ];

    private const DOCUMENT_DIRECTORIES = [
        self::FIELD_VALID_ID_FILE => 'requirements/valid-id',
        self::FIELD_PROOF_OF_RESIDENCY_FILE => 'requirements/proof-of-residency',
        self::FIELD_OTHER_DOCUMENT_FILE => 'requirements/other',
    ];

    /**
     * Field options for the administrative rejection checklist.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return self::OPTIONS;
    }

    /**
     * @return list<string>
     */
    public function textFields(): array
    {
        return self::TEXT_FIELDS;
    }

    /**
     * @return list<string>
     */
    public function documentFields(): array
    {
        return array_keys(self::DOCUMENT_COLUMNS);
    }

    public function isDocumentField(string $field): bool
    {
        return array_key_exists($field, self::DOCUMENT_COLUMNS);
    }

    public function documentColumn(string $field): ?string
    {
        return self::DOCUMENT_COLUMNS[$field] ?? null;
    }

    public function documentDirectory(string $field): ?string
    {
        return self::DOCUMENT_DIRECTORIES[$field] ?? null;
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    public function labelsFor(array $fields): array
    {
        $labels = [];

        foreach (self::OPTIONS as $field => $label) {
            if (in_array($field, $fields, true)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    public function labelFor(string $field): string
    {
        return self::OPTIONS[$field] ?? $field;
    }

    /**
     * Uses the explicit selection, with inference for older rejections that
     * do not have saved correction fields.
     *
     * @param  array<int, string>|null  $selected
     * @return list<string>
     */
    public function normalize(?array $selected, ?string $reason = null): array
    {
        $allowed = array_keys(self::OPTIONS);

        $fields = array_values(array_intersect(
            is_array($selected) ? $selected : [],
            $allowed,
        ));

        return $fields ?: ($this->inferFieldsFromReason($reason) ?: self::FALLBACK_FIELDS);
    }

    /**
     * Maps wording such as "valid ID", "proof of residency", "Block and
     * Street", or "contact number" to the fields the applicant may correct.
     *
     * @return list<string>
     */
    public function inferFieldsFromReason(?string $reason): array
    {
        $reason = strtolower(trim((string) $reason));

        if ($reason === '') {
            return [];
        }

        $patterns = [
            self::FIELD_VALID_ID_FILE => '/\b(valid\s*id|identification|umid|driver\'?s?\s*licen[cs]e|passport)\b/',
            self::FIELD_VALID_ID_PRESENTED => '/\b(valid\s*id\s*(presented|type)|id\s*(presented|type))\b/',
            self::FIELD_PROOF_OF_RESIDENCY_FILE => '/\b(proof\s*of\s*(ownership|residency|residence)|land\s*title|tax\s*declaration|lease|tenancy)\b/',
            self::FIELD_ADDRESS_BLOCK => '/\bblock\b/',
            self::FIELD_ADDRESS_STREET => '/\bstreet\b/',
            self::FIELD_CONTACT_NUMBER => '/\b(contact\s*(number|no\.?)|phone|mobile|cell)\b/',
            self::FIELD_EMAIL => '/\b(e-?mail)\b/',
            self::FIELD_FULL_NAME => '/\b(full\s*name|complete\s*name)\b/',
            self::FIELD_SERVICE_PUROK => '/\bpurok\b/',
            self::FIELD_PROPERTY_TYPE => '/\bproperty\s*type\b/',
            self::FIELD_OWNERSHIP_STATUS => '/\bownership\s*status\b/',
            self::FIELD_OTHER_DOCUMENT_DETAILS => '/\bother\s*document\b/',
            self::FIELD_OTHER_DOCUMENT_FILE => '/\b(other\s*document|supporting\s*document)\b/',
        ];

        $fields = [];

        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $reason) === 1) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Validation rules for the corrected fields only; the values of every
     * other field are never accepted from the resubmission request.
     *
     * @param  list<string>  $fields
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $rules[$field] = match ($field) {
                self::FIELD_FULL_NAME => ['required', 'string', 'max:255'],
                self::FIELD_EMAIL => ['required', 'email', 'max:255'],
                self::FIELD_CONTACT_NUMBER => ['required', 'string', 'regex:'.self::CONTACT_NUMBER_PATTERN],
                self::FIELD_SERVICE_PUROK => ['required', Rule::in(config('service_area.puroks', []))],
                self::FIELD_ADDRESS_BLOCK, self::FIELD_ADDRESS_STREET => ['required', 'string', 'max:100'],
                self::FIELD_PROPERTY_TYPE => ['required', 'in:Residential,Commercial'],
                self::FIELD_OWNERSHIP_STATUS => ['required', 'in:Owner,Tenant'],
                self::FIELD_VALID_ID_PRESENTED => ['required', 'string', 'max:100'],
                self::FIELD_OTHER_DOCUMENT_DETAILS => ['required', 'string', 'max:255'],
                self::FIELD_VALID_ID_FILE,
                self::FIELD_PROOF_OF_RESIDENCY_FILE,
                self::FIELD_OTHER_DOCUMENT_FILE => ['required', 'file', 'extensions:jpg,jpeg,png,pdf', new FileMimeType('jpg,jpeg,png,pdf')],
                default => ['nullable'],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationMessages(): array
    {
        return [
            'contact_number.required' => 'Please enter your corrected contact number.',
            'contact_number.regex' => 'The contact number must contain numbers only (10 to 15 digits).',
            'valid_id_file.required' => 'Please upload a replacement copy of your Valid ID.',
            'proof_of_residency_file.required' => 'Please upload a replacement Proof of Ownership/Residency document.',
            'other_document_file.required' => 'Please upload the requested supporting document.',
            '*.extensions' => 'Documents must be a JPG, JPEG, PNG, or PDF file.',
        ];
    }

    /**
     * Builds the attribute updates for the corrected fields only.
     *
     * Documents without a replacement upload keep their stored path, and
     * fields the administrator did not flag are never written.
     *
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $validated
     * @param  array<string, string>  $documentPaths
     * @return array<string, mixed>
     */
    public function applyCorrections(
        Consumer $consumer,
        array $fields,
        array $validated,
        array $documentPaths = [],
    ): array {
        $updates = [];

        foreach (self::TEXT_FIELDS as $field) {
            if (in_array($field, $fields, true) && array_key_exists($field, $validated)) {
                $updates[$field] = $validated[$field];
            }
        }

        foreach (self::DOCUMENT_COLUMNS as $field => $column) {
            if (in_array($field, $fields, true) && filled($documentPaths[$field] ?? null)) {
                $updates[$column] = $documentPaths[$field];
            }
        }

        if ($this->touchesServiceAddress($fields)) {
            $updates['service_address'] = $this->formatServiceAddress(
                $updates[self::FIELD_SERVICE_PUROK] ?? $consumer->service_purok ?? $this->purokFromAddress($consumer->service_address),
                $updates[self::FIELD_ADDRESS_BLOCK] ?? $consumer->address_block,
                $updates[self::FIELD_ADDRESS_STREET] ?? $consumer->address_street,
            );
        }

        if (isset($updates['valid_id_path'])) {
            $updates['valid_id_submitted'] = true;
        }

        return $updates;
    }

    /**
     * @param  list<string>  $fields
     */
    public function touchesServiceAddress(array $fields): bool
    {
        return array_intersect($fields, [
            self::FIELD_SERVICE_PUROK,
            self::FIELD_ADDRESS_BLOCK,
            self::FIELD_ADDRESS_STREET,
        ]) !== [];
    }

    /**
     * Accepts either a bare number ("12") or a full label ("Block 12") and
     * always renders it as "Block <value>", matching the public application.
     */
    public function formatServiceAddress(?string $purok, ?string $block = null, ?string $street = null): string
    {
        $serviceArea = config('service_area');

        return collect([
            $this->formatBlock($block),
            filled($street) ? $street : null,
            $purok,
            $serviceArea['barangay_subdivision'],
            $serviceArea['city'],
            $serviceArea['country'],
        ])->filter()->implode(', ');
    }

    /**
     * Recovers the Purok from an already formatted service address so address
     * corrections do not lose the part of the address that is not editable.
     */
    public function purokFromAddress(?string $address): ?string
    {
        foreach (config('service_area.puroks', []) as $purok) {
            if (str_contains((string) $address, $purok)) {
                return $purok;
            }
        }

        return null;
    }

    private function formatBlock(?string $block): ?string
    {
        if (blank($block)) {
            return null;
        }

        $block = trim($block);

        return preg_match('/^block\s+/i', $block) === 1
            ? 'Block '.preg_replace('/^block\s+/i', '', $block)
            : 'Block '.$block;
    }
}
