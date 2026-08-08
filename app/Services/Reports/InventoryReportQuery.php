<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class InventoryReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Inventory Report';
    }

    public function columns(): array
    {
        return ['sku' => 'SKU', 'name' => 'Product', 'on_hand' => 'On Hand', 'available' => 'Available', 'reserved' => 'Reserved', 'average_cost' => 'Average Cost', 'valuation' => 'Valuation', 'stock_status' => 'Status'];
    }

    public function summary(ReportFilters $filters): array
    {
        $row = $this->base($filters)->selectRaw('SUM(product_inventories.quantity*product_inventories.average_cost) total_valuation, COUNT(*) products')->first();

        return ['products' => (int) $row->products, 'total_valuation' => $row->total_valuation ?? 0];
    }

    public function rows(ReportFilters $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters->perPage);
    }

    public function exportRows(ReportFilters $filters): Traversable
    {
        return $this->paginatedExport(fn (int $page) => $this->query($filters)->paginate(500, page: $page));
    }

    private function query(ReportFilters $filters): Builder
    {
        return $this->base($filters)->leftJoin('product_translations', fn ($j) => $j->on('product_translations.product_id', '=', 'products.id')->where('product_translations.locale', 'en'))
            ->selectRaw("products.id, products.sku, product_translations.name, product_inventories.quantity on_hand, product_inventories.quantity available, 0 reserved, product_inventories.average_cost, product_inventories.quantity*product_inventories.average_cost valuation, CASE WHEN product_inventories.quantity<=0 THEN 'out_of_stock' WHEN product_inventories.low_stock_alert IS NOT NULL AND product_inventories.quantity<=product_inventories.low_stock_alert THEN 'low_stock' ELSE 'in_stock' END stock_status")
            ->orderBy('products.sku');
    }

    private function base(ReportFilters $filters): Builder
    {
        return DB::table('product_inventories')->join('products', 'products.id', '=', 'product_inventories.product_id')->when($filters->productId, fn (Builder $q, $id) => $q->where('products.id', $id));
    }
}
