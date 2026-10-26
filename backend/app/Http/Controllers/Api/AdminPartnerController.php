<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AdminPartnerController extends Controller {
    public function index(Request $request): JsonResponse {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);
        $page = PartnerProfile::with('user:id,name')->orderByDesc('created_at')->paginate($perPage);
        return response()->json($page->through(fn ($p) => [
            'id' => $p->id, 'name' => $p->user->name, 'verification_status' => $p->verification_status->value,
        ]));
    }
}
