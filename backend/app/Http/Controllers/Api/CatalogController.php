<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class CatalogController extends Controller {
    public function services(Request $request): JsonResponse {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
        ]);
        $services = Service::where('active', true)
            ->when($filters['search'] ?? null, function ($query, $term) {
                $needle = '%' . mb_strtolower(trim($term)) . '%';
                $query->where(function ($nested) use ($needle) {
                    $nested->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$needle])
                        ->orWhereRaw("LOWER(COALESCE(scope, '')) LIKE ?", [$needle]);
                });
            })
            ->when(isset($filters['min_price']), fn ($query) => $query->where('price_amount', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($query) => $query->where('price_amount', '<=', $filters['max_price']))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description', 'scope', 'limitations', 'price_amount']);
        return response()->json(['data' => $services]);
    }
    public function areas(): JsonResponse {
        return response()->json(['data' => Area::where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }
}
