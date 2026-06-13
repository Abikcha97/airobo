<?php

namespace App\Http\Controllers;

use App\Models\DispenserFillHistory;
use App\Models\DispenserInventory;
use App\Models\Kiosk;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispenserController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    // ---------------------------------------------------------------
    // T16 — POST /api/v1/kiosks/{id}/dispenser/fill
    // ---------------------------------------------------------------
    public function fill(Request $request, int $id): JsonResponse
    {
        Kiosk::findOrFail($id);

        $request->validate([
            'fills'              => 'required|array|min:1',
            'fills.*.denomination' => 'required|integer|min:1000',
            'fills.*.quantity'   => 'required|integer|min:1',
            'notes'              => 'nullable|string|max:500',
        ]);

        $userId      = $request->get('__auth_user_id');
        $totalAdded  = 0;
        $inventoryAfter = [];

        DB::transaction(function () use ($id, $request, $userId, &$totalAdded, &$inventoryAfter) {
            foreach ($request->fills as $fill) {
                $denomination = (int) $fill['denomination'];
                $quantity     = (int) $fill['quantity'];

                // Upsert inventory
                DispenserInventory::updateOrCreate(
                    ['kiosk_id' => $id, 'denomination' => $denomination],
                    ['quantity' => DB::raw("quantity + {$quantity}")]
                );

                // Fill history
                DispenserFillHistory::create([
                    'kiosk_id'      => $id,
                    'denomination'  => $denomination,
                    'quantity_added' => $quantity,
                    'filled_by'     => $userId,
                    'notes'         => $request->notes,
                ]);

                $totalAdded += $denomination * $quantity;
            }

            $inventoryAfter = DispenserInventory::where('kiosk_id', $id)
                ->orderByDesc('denomination')
                ->get(['denomination', 'quantity'])
                ->map(fn ($row) => [
                    'denomination' => $row->denomination,
                    'quantity'     => $row->quantity,
                    'total'        => $row->denomination * $row->quantity,
                    'status'       => $row->quantity === 0 ? 'EMPTY'
                                     : ($row->quantity < $row->min_threshold ? 'LOW' : 'OK'),
                ])->toArray();
        });

        return response()->json([
            'kiosk_id'        => $id,
            'total_cash_added' => $totalAdded,
            'inventory_after' => $inventoryAfter,
        ]);
    }

    // ---------------------------------------------------------------
    // T16 — GET /api/v1/kiosks/{id}/dispenser/inventory
    // ---------------------------------------------------------------
    public function inventory(int $id): JsonResponse
    {
        Kiosk::findOrFail($id);

        $rows = DispenserInventory::where('kiosk_id', $id)
            ->orderByDesc('denomination')
            ->get();

        $denominations = $rows->map(fn ($row) => [
            'denomination' => $row->denomination,
            'quantity'     => $row->quantity,
            'total'        => $row->denomination * $row->quantity,
            'status'       => $row->quantity === 0 ? 'EMPTY'
                             : ($row->quantity < $row->min_threshold ? 'LOW' : 'OK'),
        ]);

        return response()->json([
            'kiosk_id'    => $id,
            'total_cash'  => $rows->sum(fn ($r) => $r->denomination * $r->quantity),
            'last_updated' => $rows->max('updated_at'),
            'denominations' => $denominations,
        ]);
    }

    // ---------------------------------------------------------------
    // T16 — GET /api/v1/kiosks/{id}/dispenser/history
    // ---------------------------------------------------------------
    public function history(Request $request, int $id): JsonResponse
    {
        Kiosk::findOrFail($id);

        $query = DispenserFillHistory::where('kiosk_id', $id);

        if ($from = $request->query('from')) {
            $query->where('filled_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('filled_at', '<=', $to . ' 23:59:59');
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        return response()->json(
            $query->orderByDesc('filled_at')->paginate($perPage)
        );
    }
}
