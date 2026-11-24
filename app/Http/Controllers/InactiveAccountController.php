<?php

namespace App\Http\Controllers;

use App\Services\DisconnectedAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InactiveAccountController extends Controller
{
    public function accessStatus(Request $request, DisconnectedAccountService $accounts): JsonResponse
    {
        $consumer = $request->user()->consumer;
        if (! $accounts->isRestricted($consumer)) {
            return response()->json(['portal_access' => true]);
        }

        return response()->json([
            'portal_access' => false,
            'balances' => $accounts->balanceBreakdown($consumer),
        ]);
    }

    public function __invoke(Request $request, DisconnectedAccountService $accounts): View|RedirectResponse
    {
        $consumer = $request->user()->consumer;

        if (! $accounts->isRestricted($consumer)) {
            return redirect()->route('dashboard');
        }

        return view('consumer.account.inactive', ['accountSummary' => $accounts->summary($consumer)]);
    }
}
