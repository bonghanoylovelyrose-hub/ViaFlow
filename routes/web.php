<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InactiveAccountController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConnectionResubmissionController;
use App\Http\Controllers\ConsumerController;
use App\Http\Controllers\ConsumerPasswordController;
use App\Http\Controllers\ConsumerPasswordSetupController;
use App\Http\Controllers\ConsumerEmailVerificationController;
use App\Http\Controllers\ReconnectionRescheduleController;
use App\Http\Controllers\ConsumerProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DssController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\InstallationRescheduleController;
use App\Http\Controllers\LoanAgreementController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\DatabaseNotification;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::post('/auth/validate-email', [AuthController::class, 'validateEmail'])->name('auth.validate-email');

Route::get('/account/setup-password/{user}/{token}', [ConsumerPasswordSetupController::class, 'show'])
    ->middleware('throttle:10,1')->name('password.setup');
Route::post('/account/setup-password/{user}/{token}', [ConsumerPasswordSetupController::class, 'store'])
    ->middleware('throttle:10,1')->name('password.setup.store');
Route::get('/account/verify-email/{consumer}/{token}', [ConsumerEmailVerificationController::class, 'show'])
    ->middleware(['signed', 'throttle:10,1'])->name('consumer.email.verify');
Route::post('/account/verify-email/{consumer}/{token}', [ConsumerEmailVerificationController::class, 'confirm'])
    ->middleware(['signed', 'throttle:10,1'])->name('consumer.email.verify.confirm');
Route::get('/reconnection/{reconnection}/reschedule', [ReconnectionRescheduleController::class, 'show'])->name('reconnection.reschedule');
Route::post('/reconnection/{reconnection}/reschedule', [ReconnectionRescheduleController::class, 'store'])
    ->middleware('throttle:10,1')->name('reconnection.reschedule.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [ConsumerPasswordController::class, 'showForgotForm'])->name('password.forgot');
    Route::post('/forgot-password', [ConsumerPasswordController::class, 'requestPasswordReset'])
        ->middleware('throttle:5,1,password-reset-email')
        ->name('password.forgot.send');
    Route::get('/register', [AuthController::class, 'showRegisterStart'])->name('register');
    Route::post('/register/type', [AuthController::class, 'storeRegisterType'])->name('register.type.store');
    Route::get('/register/member-details', [AuthController::class, 'showMemberDetails'])->name('register.member-details');
    Route::post('/register/member-details', [AuthController::class, 'storeMemberDetails'])->name('register.member-details.store');
    Route::get('/register/new-connection', [AuthController::class, 'showRegister'])->name('register.new-connection');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

// Rejected applicants have no login yet, so the correction form is reached
// through the temporary signed link emailed by the administrator. The
// signature is the authorisation; no session or role is required.
Route::middleware('signed')->group(function () {
    Route::get('/connection/application/{consumer}/resubmit', [ConnectionResubmissionController::class, 'edit'])
        ->name('connection.resubmit.edit');
    Route::post('/connection/application/{consumer}/resubmit', [ConnectionResubmissionController::class, 'store'])
        ->name('connection.resubmit.store');
    Route::get('/connection/application/{consumer}/resubmit/document/{field}', [ConnectionResubmissionController::class, 'document'])
        ->name('connection.resubmit.document');
});

Route::middleware('signed:relative')->group(function () {
    Route::get('/connection/application/{consumer}/requirements', [ConnectionResubmissionController::class, 'edit'])
        ->name('connection.requirements.edit');
    Route::post('/connection/application/{consumer}/requirements', [ConnectionResubmissionController::class, 'store'])
        ->name('connection.requirements.store');
});

// Installation reschedule requests can be opened via signed link from emails or authenticated portal.
Route::get('/installation/reschedule/connection/{consumer}', [InstallationRescheduleController::class, 'showConnection'])
    ->name('installation.reschedule.connection');
Route::post('/installation/reschedule/connection/{consumer}', [InstallationRescheduleController::class, 'storeConnection'])
    ->name('installation.reschedule.connection.store');
Route::get('/installation/reschedule/loan/{loan}', [InstallationRescheduleController::class, 'showLoan'])
    ->name('installation.reschedule.loan');
Route::post('/installation/reschedule/loan/{loan}', [InstallationRescheduleController::class, 'storeLoan'])
    ->name('installation.reschedule.loan.store');

Route::middleware(['auth', 'consumer.password'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/setup-password', [ConsumerPasswordSetupController::class, 'showTemporary'])->name('password.setup.temporary');
    Route::post('/account/setup-password', [ConsumerPasswordSetupController::class, 'storeTemporary'])
        ->middleware('throttle:10,1,password-setup-temporary')->name('password.setup.temporary.store');
    Route::get('/account/change-password', [ConsumerPasswordController::class, 'showChangeForm'])->name('consumer.password.change');
    Route::patch('/account/change-password', [ConsumerPasswordController::class, 'updatePassword'])->name('consumer.password.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])
        ->whereUuid('notification')
        ->name('notifications.open');
    Route::get('/uploads/{path}', function (string $path) {
        abort_if(str_starts_with($path, 'announcements/'), 404);

        $user = request()->user();
        if (! $user->isAdmin()) {
            $consumerId = $user->isConsumer() ? $user->consumer?->id : null;
            abort_unless($consumerId && \App\Models\Payment::query()
                ->where('consumer_id', $consumerId)
                ->where('proof_path', $path)
                ->exists(), 403);
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return Storage::disk('local')->response($path);
    })->where('path', '.*')->name('uploads.serve');

    Route::get('/profile', [ConsumerProfileController::class, 'show'])->name('consumer.profile.show');
    Route::patch('/profile/password', [ConsumerProfileController::class, 'updatePassword'])->name('consumer.profile.password');
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/{incident}/photo', [IncidentController::class, 'photo'])->name('incidents.photo');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::post('/incidents/{incident}/confirm', [IncidentController::class, 'confirm'])->name('incidents.confirm');
    Route::post('/incidents/{incident}/dispute', [IncidentController::class, 'dispute'])->name('incidents.dispute');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/parse-receipt', [PaymentController::class, 'parseReceipt'])->name('payments.parse-receipt');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/{payment}/receipt/pdf', [PaymentController::class, 'receiptPdf'])->name('payments.receipt.pdf');
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/{loan}/agreement', [LoanAgreementController::class, 'show'])->middleware('role:consumer')->name('loans.agreement.show');
    Route::post('/loans/confirm-submission', [LoanController::class, 'confirmSubmission'])->name('loans.confirm-submission');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::post('/loans/{loan}/income-proof', [LoanController::class, 'replaceIncomeProof'])->name('loans.income-proof.replace');
    Route::post('/loans/{loan}/request-review', [LoanController::class, 'requestReview'])->name('loans.request-review');
    Route::get('/loans/{loan}/payment-breakdown', [LoanController::class, 'paymentBreakdown'])->name('loans.payment-breakdown');
    Route::get('/loans/{loan}/income-proof', [LoanController::class, 'incomeProof'])->name('loans.income-proof');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{announcement}/photo', [AnnouncementController::class, 'photo'])->name('announcements.photo');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
});

Route::middleware(['auth', 'consumer.password', 'role:consumer'])->group(function () {
    Route::get('/account/inactive', InactiveAccountController::class)->name('account.inactive');
    Route::get('/account/access-status', [InactiveAccountController::class, 'accessStatus'])->name('account.access-status');
    Route::get('/connection', [ConnectionController::class, 'status'])->name('connection.status');
});

Route::middleware(['auth', 'role:admin,staff,meter_reader'])->group(function () {
    Route::get('/meter-readings', [MeterReadingController::class, 'index'])->name('meter-readings.index');
    Route::get('/meter-readings/csrf-token', fn () => response()->json(['token' => csrf_token()])->header('Cache-Control', 'private, no-store'))->name('meter-readings.csrf-token');
    Route::post('/meter-readings/lookup-by-meter', [MeterReadingController::class, 'lookupByMeter'])->name('meter-readings.lookup-by-meter');
    Route::post('/meter-readings', [MeterReadingController::class, 'store'])->name('meter-readings.store');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');
    Route::get('/consumers', [ConsumerController::class, 'index'])->name('consumers.index');
    Route::get('/consumers/{consumer}/billing-history', [ConsumerController::class, 'billingHistory'])->name('consumers.billing-history');
    Route::post('/consumers', [ConsumerController::class, 'store'])->name('consumers.store');
    Route::post('/consumers/bulk-upload', [ConsumerController::class, 'bulkStore'])->name('consumers.bulk-store');
    Route::get('/consumers/bulk-upload/template', [ConsumerController::class, 'downloadTemplate'])->name('consumers.bulk-template');
    Route::patch('/consumers/{consumer}/meter-number', [ConsumerController::class, 'assignMeter'])->name('consumers.assign-meter');
    Route::patch('/consumers/{consumer}/activate', [ConsumerController::class, 'activate'])->name('consumers.activate');
    Route::post('/consumers/{consumer}/resend-credentials', [ConsumerController::class, 'resendCredentials'])->name('consumers.resend-credentials');
    Route::post('/consumers/{consumer}/installation-complete', [ConsumerController::class, 'markInstallationComplete'])->name('consumers.installation-complete');
    Route::post('/consumers/{consumer}/resend-email-verification', [ConsumerController::class, 'resendEmailVerification'])->name('consumers.resend-email-verification');
    Route::post('/consumers/{consumer}/confirm-disconnection', [ConsumerController::class, 'confirmDisconnection'])->name('consumers.confirm-disconnection');
    Route::patch('/consumers/{consumer}/reconnection-fee', [ConsumerController::class, 'updateReconnectionFee'])->name('consumers.reconnection-fee');
    Route::post('/consumers/{consumer}/reconnect', [ConsumerController::class, 'reconnect'])->name('consumers.reconnect');
    Route::post('/consumers/{consumer}/reconnections/{reconnection}/complete', [ConsumerController::class, 'completeReconnection'])->name('consumers.reconnection-complete');
    Route::post('/consumers/{consumer}/retry-reactivation-email', [ConsumerController::class, 'retryReactivationEmail'])->name('consumers.retry-reactivation-email');
    Route::post('/billing', [BillingController::class, 'store'])->name('billing.store');
    Route::post('/billing/generate', [BillingController::class, 'generate'])->name('billing.generate');
    Route::get('/billing/consumers/{consumer}/monthly', [BillingController::class, 'monthlyHistory'])->name('billing.monthly-history');
    Route::patch('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
    Route::patch('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    Route::patch('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::patch('/incidents/{incident}/priority', [IncidentController::class, 'reviewPriority'])->name('incidents.priority.update');
    Route::get('/incident-meter-search', [IncidentController::class, 'searchMeters'])->name('incidents.meter-search');
    Route::patch('/loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
    Route::post('/loans/{loan}/release-installment-schedule', [LoanAgreementController::class, 'release'])->name('loans.agreement.release');
    Route::post('/loans/{loan}/agreement/retry-email', [LoanAgreementController::class, 'retryEmail'])->name('loans.agreement.retry-email');
    Route::get('/loans/{loan}/verify-income', [LoanController::class, 'showIncomeVerification'])->name('loans.verify-income.show');
    Route::post('/loans/{loan}/verify-income', [LoanController::class, 'verifyIncome'])->name('loans.verify-income');
    Route::post('/loans/{loan}/evaluate-verified-income', [LoanController::class, 'evaluateVerifiedIncome'])->name('loans.evaluate-verified-income');
    Route::patch('/loan-material-rates', [LoanController::class, 'updateMaterialRates'])->name('loan-material-rates.update');
    Route::post('/loans/{loan}/assign-and-schedule', [LoanController::class, 'assignAndSchedule'])->name('loans.assign-and-schedule');
    Route::post('/loans/{loan}/installation-email/retry', [LoanController::class, 'retryInstallationEmail'])->name('loans.installation-email.retry');
    Route::patch('/loans/{loan}/installation-status', [LoanController::class, 'updateInstallationStatus'])->name('loans.installation-status.update');
    Route::patch('/loans/{loan}/installation-schedule', [LoanController::class, 'updateInstallationSchedule'])->name('loans.installation-schedule.update');
    Route::post('/loans/{loan}/repayments', [LoanController::class, 'storeRepayment'])->name('loans.repayments.store');
    Route::any('/loan-meters/{path?}', fn () => redirect()
        ->route('loans.index')
        ->with('info', 'The Loan Meters module has been removed.'))
        ->where('path', '.*');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/dss/incidents', [DssController::class, 'incidents'])->name('dss.incidents');
    Route::get('/dss/loans', [DssController::class, 'loans'])->name('dss.loans');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::patch('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::patch('/consumers/{consumer}', [ConsumerController::class, 'update'])->name('consumers.update');
    Route::patch('/consumers/{consumer}/disconnection-notice/profile', [ConsumerController::class, 'update'])->name('consumers.update-from-disconnection-notice');
    Route::patch('/consumers/{consumer}/reject', [ConsumerController::class, 'reject'])->name('consumers.reject');
    Route::post('/consumers/{consumer}/send-requirements-link', [ConsumerController::class, 'sendRequirementsLink'])->name('consumers.send-requirements-link');
    Route::post('/consumers/{consumer}/resend-correction-link', [ConsumerController::class, 'resendCorrectionLink'])->name('consumers.resend-correction-link');
    Route::patch('/service-requests/{serviceRequest}', [BillingController::class, 'updateServiceRequest'])->name('billing.requests.update');
    Route::patch('/installation-reschedules/{rescheduleRequest}/approve', [InstallationRescheduleController::class, 'approve'])->name('installation-reschedules.approve');
    Route::patch('/installation-reschedules/{rescheduleRequest}/assign', [InstallationRescheduleController::class, 'assign'])->name('installation-reschedules.assign');
    Route::patch('/installation-reschedules/{rescheduleRequest}/reject', [InstallationRescheduleController::class, 'reject'])->name('installation-reschedules.reject');
});

Route::middleware(['auth', 'consumer.password', 'role:consumer,admin'])->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/billing/loan', [BillingController::class, 'loanIndex'])->name('billing.loan');
    Route::get('/billing/{bill}/pdf', [BillingController::class, 'statementPdf'])->name('billing.statement.pdf');
    Route::get('/billing/{bill}', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/service-requests', [BillingController::class, 'storeServiceRequest'])->name('billing.requests.store');
    Route::get('/service-requests/{serviceRequest}/proof', [BillingController::class, 'serviceRequestProof'])->name('billing.requests.proof');
});


