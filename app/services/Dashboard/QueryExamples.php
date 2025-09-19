<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Zero\Lib\DB\DBML;

class QueryExamples
{
    /**
     * Build the example query showcase used on the landing page and dashboard.
     */
    public static function build(): array
    {
        $examples = [];

        $topOrders = DBML::table('orders as o')
            ->select([
                'o.id',
                'u.name as customer_name',
                DBML::raw('SUM(oi.quantity * oi.price) AS total_amount'),
                DBML::raw('COUNT(DISTINCT oi.product_id) AS unique_products'),
            ])
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->whereBetween('o.created_at', ['2024-01-01', '2024-12-31'])
            ->groupBy(['o.id', 'u.name'])
            ->havingRaw('SUM(oi.quantity * oi.price) > ?', [500])
            ->orderByDesc(DBML::raw('total_amount'))
            ->limit(10);

        $examples[] = [
            'title' => 'Top completed orders in 2024 with aggregated totals',
            'sql' => $topOrders->toSql(),
            'bindings' => $topOrders->getBindings(),
        ];

        $loyalCustomers = DBML::table('users as u')
            ->select(['u.id', 'u.name'])
            ->addSelect(DBML::raw('(SELECT COUNT(*) FROM orders AS o WHERE o.user_id = u.id) AS orders_count'))
            ->where('u.active', 1)
            ->whereRaw('(SELECT COUNT(*) FROM orders AS o WHERE o.user_id = u.id) >= ?', [5])
            ->orderByDesc(DBML::raw('orders_count'));

        $examples[] = [
            'title' => 'Active users with at least five orders (subquery example)',
            'sql' => $loyalCustomers->toSql(),
            'bindings' => $loyalCustomers->getBindings(),
        ];

        $catalogQuery = DBML::table('products as p')
            ->select(['p.id', 'p.name', 'p.price'])
            ->where(function (DBML $query) {
                $query->where('p.status', 'active')
                    ->orWhere('p.stock', '>', 0);
            })
            ->when(true, function (DBML $query) {
                $query->where('p.is_featured', 1);
            })
            ->orderBy('p.name')
            ->limit(20)
            ->offset(10);

        $examples[] = [
            'title' => 'Featured product catalogue with nested conditions and pagination',
            'sql' => $catalogQuery->toSql(),
            'bindings' => $catalogQuery->getBindings(),
        ];

        $activeUsers = User::query()
            ->where('active', 1);

        $examples[] = [
            'title' => 'Active users via model query',
            'sql' => $activeUsers->toSql(),
            'bindings' => $activeUsers->getBindings(),
        ];

        return $examples;
    }
}
