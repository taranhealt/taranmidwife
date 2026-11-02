<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class FavoriteController extends Controller {
    public function index(Request $request): JsonResponse {
        $items = DB::table('patient_favorites')->join('services', 'services.id', '=', 'patient_favorites.service_id')
            ->where('patient_favorites.patient_id', $request->user()->id)->where('services.active', true)
            ->orderBy('services.name')->get(['services.id', 'services.code', 'services.name', 'services.description',
                'services.scope', 'services.limitations', 'services.price_amount']);
        return response()->json(['data' => $items]);
    }
    public function store(Request $request): JsonResponse {
        $data = $request->validate(['service_id' => ['required', 'string', 'exists:services,id']]);
        $service = Service::whereKey($data['service_id'])->where('active', true)->firstOrFail();
        $created = DB::table('patient_favorites')->insertOrIgnore([
            'patient_id' => $request->user()->id, 'service_id' => $service->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['data' => ['service_id' => $service->id, 'is_favorite' => true]], $created ? 201 : 200);
    }
    public function destroy(Request $request, string $service): JsonResponse {
        DB::table('patient_favorites')->where('patient_id', $request->user()->id)->where('service_id', $service)->delete();
        return response()->json(['data' => ['service_id' => $service, 'is_favorite' => false]]);
    }
}
