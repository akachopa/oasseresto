<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Sales\Models\CashierShift;
use App\Modules\Sales\Services\PosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class PosController
{
    public function __construct(
        private readonly PosService $pos,
        private readonly ScopeManager $scope,
    ) {}

    public function index(): View
    {
        $shift = $this->pos->activeShift(Auth::user());

        if ($shift === null) {
            return view('sales.pos.no-shift', [
                'warehouses' => $this->warehouses(),
            ]);
        }

        return view('sales.pos.index', ['shift' => $shift]);
    }

    public function shift(): View
    {
        $shift = $this->pos->activeShift(Auth::user());

        return view('sales.pos.shift', [
            'shift' => $shift,
            'summary' => $shift ? $this->pos->summary($shift) : null,
            'warehouses' => $this->warehouses(),
            'history' => CashierShift::where('user_id', Auth::id())
                ->latest('opened_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function openShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->pos->openShift(
                Auth::user(),
                (int) $validated['warehouse_id'],
                (float) ($validated['opening_cash'] ?? 0),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('pos.index')->with('status', 'Shift kasir dibuka.');
    }

    public function closeShift(Request $request, CashierShift $shift): RedirectResponse
    {
        abort_unless((int) $shift->user_id === (int) Auth::id(), 403);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->pos->closeShift($shift, (float) $validated['counted_cash'], $validated['note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('pos.shift')->with('status', 'Shift kasir ditutup.');
    }

    public function cashMovement(Request $request, CashierShift $shift): RedirectResponse
    {
        abort_unless((int) $shift->user_id === (int) Auth::id(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'direction' => ['required', 'in:in,out'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->pos->recordCashMovement(
                $shift,
                (float) $validated['amount'],
                $validated['direction'],
                $validated['note'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Mutasi kas dicatat pada shift ini.');
    }

    public function shiftDetail(CashierShift $shift): View
    {
        abort_unless(
            (int) $shift->user_id === (int) Auth::id() || Auth::user()?->can('pos.shift.close'),
            403,
        );

        $shift->load(['cashier', 'warehouse']);

        return view('sales.pos.shift-detail', [
            'shift' => $shift,
            'summary' => $this->pos->summary($shift),
            'invoices' => $shift->invoices()->with('customer')->latest('id')->limit(50)->get(),
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function warehouses()
    {
        $branchId = $this->scope->branchId();

        return Warehouse::active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
