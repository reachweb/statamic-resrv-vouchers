<?php

namespace Reach\StatamicResrvVouchers\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Reach\StatamicResrv\Enums\ReservationEmailEvent;
use Reach\StatamicResrv\Mail\ReservationConfirmed;
use Reach\StatamicResrv\Support\ReservationEmailDispatcher;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Exceptions\InvalidVoucherTransitionException;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Models\VoucherScan;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;

class VoucherCpController extends Controller
{
    public function __construct(
        private readonly VoucherTokenSigner $signer,
        private readonly VoucherStateMachine $stateMachine,
    ) {}

    public function indexCp(): InertiaResponse
    {
        return Inertia::render('resrv-vouchers::Vouchers/Index', [
            'listUrl' => cp_route('resrv-vouchers.index.json'),
            'resendUrl' => cp_route('resrv-vouchers.resend', ['voucher' => '__id__']),
            'statuses' => collect(VoucherStatus::cases())->map(fn (VoucherStatus $s) => [
                'value' => $s->value,
                'label' => __(ucfirst($s->value)),
            ])->all(),
            'defaultPerPage' => 25,
        ]);
    }

    public function scanCp(): InertiaResponse
    {
        return Inertia::render('resrv-vouchers::Vouchers/Scan', [
            'lookupUrl' => cp_route('resrv-vouchers.lookup'),
            'markUsedUrl' => cp_route('resrv-vouchers.mark-used'),
            'unMarkUrl' => cp_route('resrv-vouchers.un-mark'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable'],
            'status.*' => ['string', Rule::enum(VoucherStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Voucher::query()->with('reservation.customer');

        if ($status = $validated['status'] ?? null) {
            $query->whereIn('status', is_array($status) ? $status : [$status]);
        }

        $vouchers = $query->latest()->paginate($validated['per_page'] ?? 25);

        $vouchers->getCollection()->each(
            fn (Voucher $v) => $v->reservation?->makeHidden(['entry'])
        );

        return response()->json($vouchers);
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $userId = $this->userId();
        $voucher = $this->voucherFromToken($request->input('token'));

        if (! $voucher) {
            $this->logScan(null, $userId, 'scan', 'not-found', $request);

            return response()->json(['message' => 'Invalid voucher token.'], 422);
        }

        $voucher->load('reservation.customer');
        $voucher->reservation?->makeHidden(['entry']);

        $status = $this->stateMachine->statusOf($voucher);
        $this->logScan($voucher->id, $userId, 'scan', $status->resultKey(), $request);

        return response()->json([
            'voucher' => $voucher,
            'reservation' => $voucher->reservation,
            'status' => $status->value,
            'status_banner' => $status->banner(),
        ]);
    }

    public function markUsed(Request $request): JsonResponse
    {
        return $this->applyTransition(
            $request,
            'mark-used',
            fn (Voucher $v, ?string $uid) => $this->stateMachine->markUsed($v, $uid),
        );
    }

    public function unMark(Request $request): JsonResponse
    {
        return $this->applyTransition(
            $request,
            'un-mark',
            fn (Voucher $v, ?string $uid) => $this->stateMachine->unMark($v, $uid),
        );
    }

    public function resend(Request $request, Voucher $voucher): JsonResponse
    {
        $reservation = $voucher->reservation;
        $userId = $this->userId();

        if (! $reservation) {
            $this->logScan($voucher->id, $userId, 'resend', 'not-found', $request);

            return response()->json(['message' => 'Voucher has no reservation.'], 422);
        }

        $sent = app(ReservationEmailDispatcher::class)->send(
            $reservation,
            ReservationEmailEvent::CustomerConfirmed,
            new ReservationConfirmed($reservation),
        );

        $result = $sent ? 'success' : 'not-sent';
        $this->logScan($voucher->id, $userId, 'resend', $result, $request);

        if (! $sent) {
            return response()->json(['message' => 'Email could not be sent.'], 422);
        }

        return response()->json(['voucher' => $voucher]);
    }

    private function applyTransition(Request $request, string $action, Closure $apply): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $userId = $this->userId();
        $voucher = $this->voucherFromToken($request->input('token'));

        if (! $voucher) {
            $this->logScan(null, $userId, $action, 'not-found', $request);

            return response()->json(['message' => 'Invalid voucher token.'], 422);
        }

        try {
            $apply($voucher, $userId);
        } catch (InvalidVoucherTransitionException $e) {
            $this->logScan($voucher->id, $userId, $action, 'invalid-transition', $request);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->logScan($voucher->id, $userId, $action, 'success', $request);

        return response()->json(['voucher' => $voucher]);
    }

    private function voucherFromToken(?string $token): ?Voucher
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $uuid = $this->signer->verify($token);

        return $uuid ? Voucher::query()->find($uuid) : null;
    }

    private function logScan(?string $voucherId, ?string $userId, string $action, string $result, Request $request): void
    {
        VoucherScan::create([
            'voucher_id' => $voucherId,
            'user_id' => $userId,
            'action' => $action,
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function userId(): ?string
    {
        $id = auth()->id();

        return $id !== null ? (string) $id : null;
    }
}
