<?php

namespace App\Services\Reports;

/**
 * The single registry of reports exposed by the Reports Center.
 */
final class ReportCatalog
{
    /**
     * @return array<string, ReportDefinition>
     */
    public static function all(): array
    {
        $consumerStatuses = [
            'Pending Verification',
            'Active',
            'For Review',
            'Disconnected',
            'For Reconnection',
            'Rejected',
        ];

        $definitions = [
            new ReportDefinition(
                key: 'collections',
                label: 'Collections Report',
                category: 'Financial',
                description: 'Verified payments received during the selected period.',
                dateBasis: 'Payment date',
                columns: [
                    'payment_date' => ['label' => 'Payment Date', 'type' => 'date'],
                    'receipt_number' => ['label' => 'OR No.', 'type' => 'text'],
                    'reference_number' => ['label' => 'Reference', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'prn' => ['label' => 'PRN', 'type' => 'text'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'payment_method' => ['label' => 'Method', 'type' => 'text'],
                    'payment_channel' => ['label' => 'Channel', 'type' => 'text'],
                    'verified_at' => ['label' => 'Verified At', 'type' => 'datetime'],
                    'verified_by' => ['label' => 'Verified By', 'type' => 'text'],
                ],
                channelOptions: ['Counter', 'Online'],
            ),
            new ReportDefinition(
                key: 'billing',
                label: 'Billing Report',
                category: 'Financial',
                description: 'Bills generated for the selected billing period, including balances and carry-forwards.',
                dateBasis: 'Billing period',
                columns: [
                    'billing_period' => ['label' => 'Billing Period', 'type' => 'date'],
                    'prn' => ['label' => 'PRN', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'purok' => ['label' => 'Purok', 'type' => 'text'],
                    'meter_number' => ['label' => 'Meter No.', 'type' => 'text'],
                    'previous_reading' => ['label' => 'Previous Reading', 'type' => 'number'],
                    'current_reading' => ['label' => 'Current Reading', 'type' => 'number'],
                    'consumption' => ['label' => 'Consumption', 'type' => 'number'],
                    'unit_rate' => ['label' => 'Unit Rate', 'type' => 'money'],
                    'base_charge' => ['label' => 'Base Charge', 'type' => 'money'],
                    'water_charge' => ['label' => 'Water Charge', 'type' => 'money'],
                    'arrears_amount' => ['label' => 'Arrears', 'type' => 'money'],
                    'penalty_amount' => ['label' => 'Penalty', 'type' => 'money'],
                    'loan_installment_amount' => ['label' => 'Loan Installment', 'type' => 'money'],
                    'amount' => ['label' => 'Total Bill', 'type' => 'money'],
                    'verified_paid' => ['label' => 'Verified Paid', 'type' => 'money'],
                    'balance_due' => ['label' => 'Balance Due', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                    'carried_forward_to' => ['label' => 'Carried Forward To', 'type' => 'text'],
                ],
                statusOptions: ['Paid', 'Paid with Balance', 'Unpaid', 'Overdue'],
            ),
            new ReportDefinition(
                key: 'payment-monitoring',
                label: 'Payment Monitoring',
                category: 'Financial',
                description: 'Every payment transaction, including pending, verified, and rejected submissions.',
                dateBasis: 'Payment date',
                columns: [
                    'payment_date' => ['label' => 'Payment Date', 'type' => 'date'],
                    'receipt_number' => ['label' => 'OR No.', 'type' => 'text'],
                    'reference_number' => ['label' => 'Reference', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'prn' => ['label' => 'PRN', 'type' => 'text'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'payment_method' => ['label' => 'Method', 'type' => 'text'],
                    'payment_channel' => ['label' => 'Channel', 'type' => 'text'],
                    'gateway_reference' => ['label' => 'Gateway Ref.', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'verified_at' => ['label' => 'Verified At', 'type' => 'datetime'],
                    'verified_by' => ['label' => 'Verified By', 'type' => 'text'],
                    'notes' => ['label' => 'Notes', 'type' => 'long_text'],
                ],
                statusOptions: ['Pending', 'Verified', 'Rejected'],
                channelOptions: ['Counter', 'Online'],
            ),
            new ReportDefinition(
                key: 'outstanding-balances',
                label: 'Outstanding Balances',
                category: 'Financial',
                description: 'Outstanding account balances as of the end of the selected month or year.',
                dateBasis: 'Billing period cutoff',
                columns: [
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'purok' => ['label' => 'Purok', 'type' => 'text'],
                    'contact_number' => ['label' => 'Contact', 'type' => 'text'],
                    'outstanding_balance' => ['label' => 'Outstanding Balance', 'type' => 'money'],
                    'open_bills' => ['label' => 'Open Bills', 'type' => 'number'],
                    'oldest_due_date' => ['label' => 'Oldest Due', 'type' => 'date'],
                    'latest_billing_period' => ['label' => 'Latest Period', 'type' => 'date'],
                    'notice' => ['label' => 'Disconnection Notice', 'type' => 'status'],
                    'account_status' => ['label' => 'Account Status', 'type' => 'status'],
                ],
                statusOptions: $consumerStatuses,
            ),
            new ReportDefinition(
                key: 'incidents',
                label: 'Incident Reports',
                category: 'Operations',
                description: 'Reported service issues, resolution status, and response time.',
                dateBasis: 'Date reported',
                columns: [
                    'reported_at' => ['label' => 'Reported At', 'type' => 'datetime'],
                    'reference' => ['label' => 'Incident No.', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'reported_by' => ['label' => 'Reported By', 'type' => 'text'],
                    'category' => ['label' => 'Category', 'type' => 'text'],
                    'custom_incident_type' => ['label' => 'Incident Type', 'type' => 'text'],
                    'location' => ['label' => 'Location', 'type' => 'text'],
                    'description' => ['label' => 'Description', 'type' => 'long_text'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'resolved_at' => ['label' => 'Resolved At', 'type' => 'datetime'],
                    'resolution_days' => ['label' => 'Resolution Days', 'type' => 'number'],
                    'resolution_notes' => ['label' => 'Resolution Notes', 'type' => 'long_text'],
                    'evidence' => ['label' => 'Evidence', 'type' => 'text'],
                ],
                statusOptions: ['Pending', 'Ongoing', 'For Consumer Confirmation', 'Resolved'],
                categoryOptions: ['Water Leak', 'No Water Supply', 'Water Quality Issue', 'Meter Concern', 'Others'],
            ),
            new ReportDefinition(
                key: 'meter-readings',
                label: 'Meter Reading Reports',
                category: 'Operations',
                description: 'Encoded readings, consumption, reader assignment, and billing state.',
                dateBasis: 'Reading date',
                columns: [
                    'reading_date' => ['label' => 'Reading Date', 'type' => 'date'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'meter_number' => ['label' => 'Meter No.', 'type' => 'text'],
                    'reader' => ['label' => 'Read By', 'type' => 'text'],
                    'previous_reading' => ['label' => 'Previous', 'type' => 'number'],
                    'current_reading' => ['label' => 'Current', 'type' => 'number'],
                    'consumption' => ['label' => 'Consumption', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'remarks' => ['label' => 'Remarks', 'type' => 'long_text'],
                    'bill_prn' => ['label' => 'Bill PRN', 'type' => 'text'],
                ],
                statusOptions: ['Pending', 'Encoded', 'Needs Revisit'],
            ),
            new ReportDefinition(
                key: 'loan-portfolio',
                label: 'Loan Reports',
                category: 'Loans',
                description: 'Loan applications, approvals, repayments, and remaining balances.',
                dateBasis: 'Application date',
                columns: [
                    'applied_at' => ['label' => 'Applied At', 'type' => 'datetime'],
                    'loan_id' => ['label' => 'Loan No.', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'amount' => ['label' => 'Loan Amount', 'type' => 'money'],
                    'term_months' => ['label' => 'Term (Months)', 'type' => 'number'],
                    'monthly_installment' => ['label' => 'Monthly Installment', 'type' => 'money'],
                    'installation_cost_total' => ['label' => 'Installation Cost', 'type' => 'money'],
                    'total_repaid' => ['label' => 'Total Repaid', 'type' => 'money'],
                    'balance_remaining' => ['label' => 'Balance Remaining', 'type' => 'money'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'review_status' => ['label' => 'Review Status', 'type' => 'status'],
                    'installation_status' => ['label' => 'Installation', 'type' => 'status'],
                    'next_due_date' => ['label' => 'Next Due Date', 'type' => 'date'],
                    'approved_at' => ['label' => 'Approved At', 'type' => 'datetime'],
                ],
                statusOptions: ['Pending Review', 'Approved', 'Active', 'Ongoing', 'Rejected', 'Completed'],
            ),
            new ReportDefinition(
                key: 'loan-repayments',
                label: 'Loan Repayments',
                category: 'Loans',
                description: 'Posted loan repayments and their effect on the remaining loan balance.',
                dateBasis: 'Payment date',
                columns: [
                    'payment_date' => ['label' => 'Payment Date', 'type' => 'date'],
                    'loan_id' => ['label' => 'Loan No.', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'amount' => ['label' => 'Amount', 'type' => 'money'],
                    'bill_prn' => ['label' => 'Bill PRN', 'type' => 'text'],
                    'payment_reference' => ['label' => 'Payment Reference', 'type' => 'text'],
                    'recorder' => ['label' => 'Recorded By', 'type' => 'text'],
                    'notes' => ['label' => 'Notes', 'type' => 'long_text'],
                    'loan_balance' => ['label' => 'Loan Balance', 'type' => 'money'],
                ],
            ),
            new ReportDefinition(
                key: 'disconnected-accounts',
                label: 'Disconnected Consumer Accounts',
                category: 'Accounts & Connections',
                description: 'Accounts disconnected during the selected period and their outstanding obligations. Current accounts without a recorded legacy disconnection date are included in the current period.',
                dateBasis: 'Disconnection date',
                columns: [
                    'disconnected_at' => ['label' => 'Disconnected At', 'type' => 'datetime'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'purok' => ['label' => 'Purok', 'type' => 'text'],
                    'contact_number' => ['label' => 'Contact', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'outstanding_balance' => ['label' => 'Outstanding Balance', 'type' => 'money'],
                    'open_bills' => ['label' => 'Open Bills', 'type' => 'number'],
                    'months_unpaid' => ['label' => 'Consecutive Unpaid', 'type' => 'number'],
                    'last_reconnection_request' => ['label' => 'Reconnection Request', 'type' => 'text'],
                ],
                statusOptions: ['Disconnected', 'For Reconnection'],
            ),
            new ReportDefinition(
                key: 'reconnected-accounts',
                label: 'Reconnected Consumer Accounts',
                category: 'Accounts & Connections',
                description: 'Accounts reconnected during the selected period, including downtime and request history.',
                dateBasis: 'Reconnection date',
                columns: [
                    'reconnected_at' => ['label' => 'Reconnected At', 'type' => 'datetime'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'purok' => ['label' => 'Purok', 'type' => 'text'],
                    'disconnected_at' => ['label' => 'Disconnected At', 'type' => 'datetime'],
                    'requested_at' => ['label' => 'Requested At', 'type' => 'datetime'],
                    'reviewed_at' => ['label' => 'Completed At', 'type' => 'datetime'],
                    'downtime_days' => ['label' => 'Downtime (Days)', 'type' => 'number'],
                    'balance_snapshot' => ['label' => 'Balance at Request', 'type' => 'money'],
                    'status' => ['label' => 'Account Status', 'type' => 'status'],
                    'reviewed_by' => ['label' => 'Reviewed By', 'type' => 'text'],
                ],
                statusOptions: ['Active', 'For Reconnection'],
            ),
            new ReportDefinition(
                key: 'consumer-accounts',
                label: 'Consumer Accounts',
                category: 'Accounts & Connections',
                description: 'Registered consumers, membership classification, account state, and connection dates.',
                dateBasis: 'Registration date',
                columns: [
                    'created_at' => ['label' => 'Registered At', 'type' => 'datetime'],
                    'application_reference' => ['label' => 'Application Ref.', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'meter_number' => ['label' => 'Meter No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'email' => ['label' => 'Email', 'type' => 'text'],
                    'contact_number' => ['label' => 'Contact', 'type' => 'text'],
                    'purok' => ['label' => 'Purok', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'membership_type' => ['label' => 'Membership', 'type' => 'text'],
                    'membership_verification_status' => ['label' => 'Membership Verification', 'type' => 'status'],
                    'property_type' => ['label' => 'Property Type', 'type' => 'text'],
                    'ownership_status' => ['label' => 'Ownership', 'type' => 'text'],
                    'installation_date' => ['label' => 'Installed On', 'type' => 'date'],
                    'disconnected_at' => ['label' => 'Disconnected At', 'type' => 'datetime'],
                    'reconnected_at' => ['label' => 'Reconnected At', 'type' => 'datetime'],
                ],
                statusOptions: $consumerStatuses,
            ),
            new ReportDefinition(
                key: 'service-requests',
                label: 'Service Requests',
                category: 'Accounts & Connections',
                description: 'New connection and reconnection requests, decisions, and balance snapshots.',
                dateBasis: 'Request date',
                columns: [
                    'requested_at' => ['label' => 'Requested At', 'type' => 'datetime'],
                    'reference' => ['label' => 'Request No.', 'type' => 'text'],
                    'request_type' => ['label' => 'Type', 'type' => 'text'],
                    'account_number' => ['label' => 'Account No.', 'type' => 'text'],
                    'consumer' => ['label' => 'Consumer', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'status'],
                    'current_balance_snapshot' => ['label' => 'Balance Snapshot', 'type' => 'money'],
                    'reviewed_at' => ['label' => 'Reviewed At', 'type' => 'datetime'],
                    'reviewed_by' => ['label' => 'Reviewed By', 'type' => 'text'],
                    'notes' => ['label' => 'Request Notes', 'type' => 'long_text'],
                    'admin_notes' => ['label' => 'Admin Notes', 'type' => 'long_text'],
                ],
                statusOptions: ['Pending', 'Approved', 'Rejected', 'Completed'],
                typeOptions: ['New Connection', 'Reconnection'],
            ),
        ];

        return collect($definitions)->keyBy('key')->all();
    }

    /**
     * @return array<string, list<ReportDefinition>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::all() as $definition) {
            $groups[$definition->category][] = $definition;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_keys(self::grouped());
    }
}
