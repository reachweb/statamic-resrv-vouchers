<?php

namespace Reach\StatamicResrvVouchers\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Reach\StatamicResrv\Enums\ReservationEmailEvent;
use Reach\StatamicResrv\Mail\ReservationConfirmed;
use Reach\StatamicResrv\Support\ReservationEmailDispatcher;
use Reach\StatamicResrvVouchers\Exceptions\InvalidVoucherTransitionException;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Models\VoucherScan;
use Reach\StatamicResrvVouchers\Resources\VoucherResource;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;
use Statamic\Facades\Scope;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;

class VoucherCpController extends Controller
{
    use QueriesFilters;

    public function __construct(
        private readonly VoucherTokenSigner $signer,
        private readonly VoucherStateMachine $stateMachine,
    ) {}

    public function indexCp(): InertiaResponse
    {
        return Inertia::render('resrv-vouchers::Vouchers/Index', [
            'filters' => Scope::filters('resrv-vouchers'),
            'listUrl' => cp_route('resrv-vouchers.index.json'),
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

    public function index(FilteredRequest $request): VoucherResource
    {
        $query = Voucher::query()->with('reservation.customer');

        $activeFilterBadges = $this->queryFilters($query, $request->filters);

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('reservation', fn ($r) => $r->where('reference', 'like', "%{$search}%"));
            });
        }

        $sort = in_array(request('sort'), ['id', 'status', 'expires_at', 'used_at', 'created_at'], true)
            ? request('sort')
            : 'created_at';
        $query->orderBy($sort, request('order') === 'asc' ? 'asc' : 'desc');

        // Clamp so a huge ?perPage can't load/serialize unbounded rows (matches Resrv's CP listings).
        $perPage = (int) (request('perPage') ?? config('statamic.cp.pagination_size', 25));
        $perPage = max(1, min($perPage, 100));

        return (new VoucherResource($query->paginate($perPage)))
            ->columnPreferenceKey('resrv-vouchers.vouchers.columns')
            ->additional(['meta' => [
                'activeFilterBadges' => $activeFilterBadges,
            ]]);
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
