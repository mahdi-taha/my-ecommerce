<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class ReviewsReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Reviews Report';
    }

    public function columns(): array
    {
        return ['sku' => 'SKU', 'name' => 'Product', 'review_count' => 'Reviews', 'approved_reviews' => 'Approved', 'pending_reviews' => 'Pending', 'rejected_reviews' => 'Rejected', 'average_rating' => 'Average Rating'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['reviews' => $this->base($filters)->count()];
    }

    public function rows(ReportFilters $filters): LengthAwarePaginator
    {
        $query = $this->query($filters);

        return $query->paginate($filters->perPage, total: $this->countReportRows($query));
    }

    public function exportRows(ReportFilters $filters): Traversable
    {
        return $this->paginatedExport(fn (int $page) => $this->query($filters)->paginate(500, page: $page));
    }

    private function query(ReportFilters $filters): Builder
    {
        return $this->base($filters)->join('products', 'products.id', '=', 'product_reviews.product_id')->leftJoin('product_translations', fn ($j) => $j->on('product_translations.product_id', '=', 'products.id')->where('product_translations.locale', 'en'))
            ->selectRaw("products.id, products.sku, product_translations.name, COUNT(*) review_count, SUM(CASE WHEN product_reviews.status='approved' THEN 1 ELSE 0 END) approved_reviews, SUM(CASE WHEN product_reviews.status='pending' THEN 1 ELSE 0 END) pending_reviews, SUM(CASE WHEN product_reviews.status='rejected' THEN 1 ELSE 0 END) rejected_reviews, AVG(CASE WHEN product_reviews.status='approved' THEN product_reviews.rating END) average_rating")
            ->groupBy('products.id', 'products.sku', 'product_translations.name')->orderByDesc('review_count')->orderBy('products.sku');
    }

    private function base(ReportFilters $filters): Builder
    {
        return DB::table('product_reviews')->when($filters->start, fn (Builder $q, $v) => $q->where('product_reviews.created_at', '>=', $v))->when($filters->endExclusive, fn (Builder $q, $v) => $q->where('product_reviews.created_at', '<', $v))->when($filters->productId, fn (Builder $q, $v) => $q->where('product_reviews.product_id', $v));
    }
}
