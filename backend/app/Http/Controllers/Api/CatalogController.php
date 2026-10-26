<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
class CatalogController extends Controller {
    public function services(): JsonResponse {
        return response()->json(['data' => Service::where('active', true)->orderBy('name')
            ->get(['id', 'code', 'name', 'description', 'scope', 'limitations', 'price_amount'])]);
    }
    public function areas(): JsonResponse {
        return response()->json(['data' => Area::where('active', true)->orderBy('name')->get(['id', 'name'])]);
    }
}
