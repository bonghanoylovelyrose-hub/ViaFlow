<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\User;
use App\Rules\ValidEmail;
use App\Services\ConnectionApplicationService;
use App\Services\ConsumerPasswordSetupService;
use App\Services\DisconnectedAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const REGISTER_CONNECTION_SESSION_KEY = 'register_connection_flow';

    private const REGISTER_MEMBER_DETAILS_SESSION_KEY = 'register_member_details';

    /**
     * Accepts digits only, 10-15 characters, so no letters, spaces or symbols
     * can ever be stored as a contact number.
     */
    public const CONTACT_NUMBER_PATTERN = '/^[0-9]{10,15}$/';

    public static function contactNumberRules(): array
    {
        return ['nullable', 'string', 'regex:'.self::CONTACT_NUMBER_PATTERN];
    }

    public static function contactNumberMessages(): array
    {
        return [
            'contact_number.regex' => 'The contact number must contain numbers only (10 to 15 digits).',
            'contact_number.numeric' => 'The contact number must contain numbers only.',
        ];
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request, ConsumerPasswordSetupService $passwords): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($credentials['login']);
        $normalizedLogin = mb_strtolower($login);
        $remember = $request->boolean('remember');

        // Admin and meter-reader authentication is unchanged: an email address
        // or username is still accepted exactly as before.
        $staffCandidates = User::query()
            ->whereIn('role', ['admin', 'staff', 'meter_reader'])
            ->when(
                str_contains($normalizedLogin, '@'),
                fn ($query) => $query->where('email', $normalizedLogin),
                fn ($query) => $query->where('username', $normalizedLogin),
            )
            ->get();

        $user = $staffCandidates->first(
            fn (User $candidate): bool => Hash::check($credentials['password'], $candidate->password)
        );

        $consumer = null;
        $temporaryLogin = false;

        // Consumers authenticate only with the meter number currently assigned
        // to their account. Email is no longer a consumer login identifier.
        if (! $user) {
            $consumer = Consumer::query()
                ->with('user')
                ->whereRaw('LOWER(meter_number) = ?', [$normalizedLogin])
                ->first();

            if ($consumer?->user?->isConsumer()) {
                if (Hash::check($credentials['password'], $consumer->user->password)) {
                    $user = $consumer->user;
                } elseif ($fingerprint = $passwords->verifyTemporaryPassword($consumer->user, $credentials['password'])) {
                    $request->session()->regenerate(true);
                    $request->session()->put(ConsumerPasswordSetupService::TEMPORARY_LOGIN_SESSION, [
                        'user_id' => $consumer->user->id,
                        'credential_fingerprint' => $fingerprint,
                    ]);

                    $user = $consumer->user;
                    $temporaryLogin = true;
                }
            }
        }

        if (! $user) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => 'Invalid meter number/email/username or password.',
                ]);
        }

        $remember = $remember && ! $user->must_change_reactivation_password;
        if ($remember) {
            // Rotate the framework token and bind it to a server-side expiry.
            $user->setRememberToken(Str::random(60));
            $user->remember_expires_at = now()->addMinutes(config('auth.guards.web.remember'));
            $user->save();
        }

        Auth::login($user, $remember);

        if (! in_array($user->role, ['admin', 'staff', 'meter_reader', 'consumer'], true)) {
            Auth::logout();

            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => 'This account is not authorized to access ViaFlow.',
                ]);
        }

        if ($user->isConsumer()) {
            $consumer ??= $user->consumer;

            if ($consumer?->status === 'Rejected') {
                Auth::logout();

                return back()
                    ->withInput($request->only('login'))
                    ->withErrors([
                        'login' => 'Your application was rejected and cannot access the system. Please contact ViaFlow for assistance.',
                    ]);
            }

            // An account that has been disconnected for arrears must still be
            // able to sign in: it needs access to settle its balance and to
            // submit a reconnection request. Only applications that have not
            // been activated yet are turned away here.
            if (! $consumer || ! $consumer->isVerified()) {
                Auth::logout();

                return back()
                    ->withInput($request->only('login'))
                    ->withErrors([
                        'login' => 'Your application is still pending admin verification. You can log in after your account is approved, activated, and assigned a unique meter number.',
                    ]);
            }
        }

        $request->session()->regenerate();
        if (! $temporaryLogin) {
            $request->session()->forget(ConsumerPasswordSetupService::TEMPORARY_LOGIN_SESSION);
        }

        if ($user->isConsumer() && app(DisconnectedAccountService::class)->isRestricted($consumer)) {
            return redirect()->route('account.inactive')->with('inactive_login_notice', true);
        }

        return redirect()->route('dashboard')->with('show_temporary_password_reminder', $temporaryLogin || $user->requiresPasswordChange());
    }

    public function showRegisterStart(): View
    {
        return view('auth.register-type');
    }

    public function storeRegisterType(Request $request): RedirectResponse
    {
        // "ARE YOU A VVTHOA MEMBER? YES OR NO"
        $validated = $request->validate([
            'is_viohoa_member' => ['required', 'in:yes,no'],
        ], [
            'is_viohoa_member.required' => 'Please answer whether you are a VVTHOA member.',
            'is_viohoa_member.in' => 'Please answer whether you are a VVTHOA member.',
        ]);

        $declaredMember = $validated['is_viohoa_member'] === 'yes';

        // "Yes" means the applicant already belongs to VVTHOA, so the
        // classification follows the answer immediately. Benefits still wait
        // for the administrator to verify the claim and activate the account.
        $workflowMembershipType = $declaredMember ? 'Member' : 'Non-Member';

        $request->session()->put(self::REGISTER_CONNECTION_SESSION_KEY, [
            'is_viohoa_member' => $validated['is_viohoa_member'],
            'declared_viohoa_member' => $declaredMember,
            'membership_type' => $workflowMembershipType,
        ]);

        if ($declaredMember) {
            return redirect()->route('register.member-details');
        }

        $request->session()->forget(self::REGISTER_MEMBER_DETAILS_SESSION_KEY);

        return redirect()->route('register.new-connection');
    }

    public function showMemberDetails(Request $request): View|RedirectResponse
    {
        if ($this->selectedMembershipType($request) !== 'Member') {
            return redirect()->route('register');
        }

        // "Are you part of the local association?" is the same question the
        // applicant already answered on the previous step, so carry that answer
        // forward instead of making them repeat it.
        return view('auth.register-member-details', [
            'declaredMember' => $this->declaredViohoaMember($request),
        ]);
    }

    public function storeMemberDetails(Request $request, ConnectionApplicationService $applications): RedirectResponse
    {
        if ($this->selectedMembershipType($request) !== 'Member') {
            return redirect()->route('register');
        }

        $validated = $request->validate($applications->memberRules(), [
            'housing_rights_documented.required' => 'Please answer whether you have housing rights documents.',
            'housing_rights_documented.in' => 'Please answer whether you have housing rights documents.',
            'resident_of_community.required' => 'Please answer whether you are a resident of the community.',
            'resident_of_community.in' => 'Please answer whether you are a resident of the community.',
            'association_member.required' => 'Please answer whether you are part of the local association.',
            'association_member.in' => 'Please answer whether you are part of the local association.',
            'association_registered.required' => 'Please confirm whether you are already officially registered.',
            'association_registered.in' => 'Please confirm whether you are already officially registered.',
            'membership_document_type.required' => 'Please select one document type.',
            'membership_document_type.in' => 'Please select one document type.',
            'membership_document_photos.required' => 'Please attach at least one photo of the selected document.',
            'membership_document_photos.array' => 'Please attach at least one photo of the selected document.',
            'membership_document_photos.min' => 'Please attach at least one photo of the selected document.',
            'membership_document_photos.*.required' => 'Please attach at least one photo of the selected document.',
            'membership_document_photos.*.file' => 'Please upload a valid JPG, JPEG, or PNG image.',
            'membership_document_photos.*.extensions' => 'Please upload a valid JPG, JPEG, or PNG image.',
        ]);

        $request->session()->put(self::REGISTER_MEMBER_DETAILS_SESSION_KEY, $applications->storeMemberDetails($validated));

        return redirect()->route('register.new-connection');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        $membershipType = $this->selectedMembershipType($request);

        if (! $membershipType) {
            return redirect()->route('register');
        }

        if ($membershipType === 'Member' && ! $this->hasMemberDetails($request)) {
            return redirect()->route('register.member-details');
        }

        return view('auth.register', [
            'selectedMembershipType' => $membershipType,
            'serviceArea' => $this->serviceArea(),
        ]);
    }

    public function register(Request $request, ConnectionApplicationService $applications): RedirectResponse
    {
        $workflowMembershipType = $this->selectedMembershipType($request);
        $declaredMember = $this->declaredViohoaMember($request);

        if (! $workflowMembershipType) {
            return redirect()->route('register');
        }

        if ($workflowMembershipType === 'Member' && ! $this->hasMemberDetails($request)) {
            return redirect()->route('register.member-details');
        }

        $validated = $request->validate($applications->applicantRules($declaredMember), self::contactNumberMessages());
        $memberDetails = $declaredMember ? $request->session()->get(self::REGISTER_MEMBER_DETAILS_SESSION_KEY, []) : [];
        $consumer = $applications->submit($validated, $declaredMember, $memberDetails);

        $request->session()->forget(self::REGISTER_CONNECTION_SESSION_KEY);
        $request->session()->forget(self::REGISTER_MEMBER_DETAILS_SESSION_KEY);

        return redirect()
            ->route('login')
            ->with('success', 'Your new connection request has been submitted and is pending admin verification. Application reference: '.$consumer?->application_reference)
            ->with('application_reference', $consumer?->application_reference);
    }

    public function validateEmail(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'email' => ['required', 'email', 'max:255', new ValidEmail],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => $validator->errors()->first('email'),
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Email address is valid.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function selectedMembershipType(Request $request): ?string
    {
        $membershipType = data_get($request->session()->get(self::REGISTER_CONNECTION_SESSION_KEY), 'membership_type');

        return in_array($membershipType, ['Member', 'Non-Member'], true)
            ? $membershipType
            : null;
    }

    /**
     * Whether the applicant answered "yes" to "ARE YOU A VVTHOA MEMBER?".
     * The answer classifies the applicant as a Member, while membership
     * verification remains Pending until an administrator reviews it.
     */
    private function declaredViohoaMember(Request $request): bool
    {
        $answer = data_get($request->session()->get(self::REGISTER_CONNECTION_SESSION_KEY), 'is_viohoa_member');

        return in_array($answer, [true, 1, '1', 'yes'], true);
    }

    private function hasMemberDetails(Request $request): bool
    {
        return is_array($request->session()->get(self::REGISTER_MEMBER_DETAILS_SESSION_KEY));
    }

    private function serviceArea(): array
    {
        return config('service_area');
    }
}
