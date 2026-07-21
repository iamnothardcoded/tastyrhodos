<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Igniter\Cart\Models\Category;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Igniter\Local\Models\Location;
use Igniter\User\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read/write surface for the owner console.
 *
 * Every action is reachable only with an `owner`-scoped token (EnsureOwner)
 * and confined to api/jamasa/* (ConfineOwnerToken). The console deliberately
 * routes sold-out toggles and the customer export through HERE rather than the
 * stock /api/menus, /api/categories, /api/customers resources — those require
 * an admin-TYPE token (full API), so wrapping them keeps the owner token
 * minimal: a leak can toggle a dish or read this tenant's own customers,
 * nothing more. Pause / lead-time / manual-accept stay on the existing
 * OrderingSettingsController (also under jamasa/*).
 *
 * Single tenant per container/DB, so "this tenant's data" == the whole DB;
 * no cross-tenant scoping is needed.
 */
class OwnerController extends Controller
{
    /** Status ids that are neither incomplete (0) nor canceled (9). */
    protected function realOrders()
    {
        return Order::query()->whereNotNull('status_id')->whereNotIn('status_id', [0, 9]);
    }

    public function overview(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $weekAgo = now()->subDays(6)->toDateString();

        $stats = [
            'orders_today' => (clone $this->realOrders())->whereDate('order_date', $today)->count(),
            'revenue_today' => (float) (clone $this->realOrders())->whereDate('order_date', $today)->sum('order_total'),
            'orders_week' => (clone $this->realOrders())->whereDate('order_date', '>=', $weekAgo)->count(),
            'revenue_week' => (float) (clone $this->realOrders())->whereDate('order_date', '>=', $weekAgo)->sum('order_total'),
            'customers_total' => Customer::query()->count(),
        ];

        $menus = Menu::query()
            ->orderBy('menu_name')
            ->get(['menu_id', 'menu_name', 'menu_status'])
            ->map(fn(Menu $m) => [
                'id' => (int) $m->menu_id,
                'name' => $m->menu_name,
                'available' => (bool) $m->menu_status,
            ]);

        $categories = Category::query()
            ->orderBy('priority')
            ->get(['category_id', 'name', 'status'])
            ->map(fn(Category $c) => [
                'id' => (int) $c->category_id,
                'name' => $c->name,
                'available' => (bool) $c->status,
            ]);

        return response()->json([
            'location_id' => optional($this->resolveLocation())->getKey(),
            'stats' => $stats,
            'menus' => $menus,
            'categories' => $categories,
        ]);
    }

    public function menuStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_id' => 'required|integer',
            'available' => 'required|boolean',
        ]);

        if (!$menu = Menu::query()->find($data['menu_id'])) {
            return response()->json(['message' => 'Gericht nicht gefunden.'], 404);
        }

        $menu->menu_status = $data['available'];
        $menu->save();

        return response()->json(['id' => (int) $menu->menu_id, 'available' => (bool) $menu->menu_status]);
    }

    public function categoryStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|integer',
            'available' => 'required|boolean',
        ]);

        if (!$category = Category::query()->find($data['category_id'])) {
            return response()->json(['message' => 'Kategorie nicht gefunden.'], 404);
        }

        $category->status = $data['available'];
        $category->save();

        return response()->json(['id' => (int) $category->category_id, 'available' => (bool) $category->status]);
    }

    public function customers(Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 100) ?: 100, 500);

        $customers = Customer::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['customer_id', 'first_name', 'last_name', 'email', 'telephone', 'created_at', 'last_login'])
            ->map(fn(Customer $c) => $this->customerRow($c));

        return response()->json([
            'total' => Customer::query()->count(),
            'shown' => $customers->count(),
            'customers' => $customers,
        ]);
    }

    /** Full customer list as a CSV download — the Lieferando-can't-match export. */
    public function customersCsv(Request $request): StreamedResponse
    {
        $filename = 'kunden-'.now()->toDateString().'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 (umlauts) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Vorname', 'Nachname', 'E-Mail', 'Telefon', 'Kunde seit', 'Zuletzt aktiv'], ';');

            Customer::query()
                ->orderByDesc('created_at')
                ->chunk(500, function ($chunk) use ($out): void {
                    foreach ($chunk as $c) {
                        fputcsv($out, [
                            $c->first_name,
                            $c->last_name,
                            $c->email,
                            $c->telephone,
                            optional($c->created_at)->format('d.m.Y'),
                            optional($c->last_login)->format('d.m.Y'),
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function customerRow(Customer $c): array
    {
        return [
            'id' => (int) $c->customer_id,
            'name' => trim(($c->first_name ?? '').' '.($c->last_name ?? '')),
            'email' => $c->email,
            'telephone' => $c->telephone,
            'created_at' => optional($c->created_at)->toDateString(),
            'last_login' => optional($c->last_login)->toDateString(),
        ];
    }

    protected function resolveLocation(): ?Location
    {
        return Location::getDefault() ?? Location::query()->first();
    }
}
