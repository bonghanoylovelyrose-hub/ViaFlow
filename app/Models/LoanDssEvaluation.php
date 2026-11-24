<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDssEvaluation extends Model
{
    protected $fillable = [
        'loan_id',
        'recommendation',
        'evaluation_type',
        'criteria_version',
        'evaluated_values',
        'income_evidence',
        'income_verification_status',
        'failed_criteria',
        'missing_information',
        'evidence_hash',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_values' => 'array',
            'income_evidence' => 'array',
            'failed_criteria' => 'array',
            'missing_information' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function displayableApprovalReasons(): array
    {
        $reasons = $this->evaluated_values['approval_reasons'] ?? [];

        return $this->filterObsoleteCriteria(is_array($reasons) ? $reasons : []);
    }

    public function displayableFailedCriteria(): array
    {
        $criteria = $this->failed_criteria ?? [];

        return $this->filterObsoleteCriteria(is_array($criteria) ? $criteria : []);
    }

    private function filterObsoleteCriteria(array $reasons): array
    {
        return array_values(array_filter(
            $reasons,
            fn (mixed $reason): bool => ! $this->isObsoleteCriterion($reason),
        ));
    }

    private function isObsoleteCriterion(mixed $reason): bool
    {
        if (is_array($reason)) {
            $id = strtolower((string) ($reason['id'] ?? ''));
            if (in_array($id, ['verified_membership', 'membership_verification', 'address_verification', 'residential_location', 'residential_location_eligibility', 'service_area_eligibility'], true)) {
                return true;
            }

            $text = implode(' ', array_filter(
                array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $reason),
            ));
        } else {
            $text = is_scalar($reason) ? (string) $reason : '';
        }

        return preg_match('/via\\s+vida|vvthoa|homeowners?\\s+association|association\\s+membership|membership\\s+(?:verification|type)|verified\\s+(?:association|membership)|address|residential|residence|service\\s+area|service\\s+location|location\\s+eligibility/i', $text) === 1;
    }
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
