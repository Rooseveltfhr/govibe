<?php

namespace Modules\Tagtoa\App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Tagtoa\App\Models\Review\Review;
use Modules\Tagtoa\App\Services\Review\ReviewService;

/**
 * TAGTOA REVIEWS — soumission publique d'un avis (NFC / QR), pas d'auth.
 * L'avis est créé en "pending" : il n'apparaît qu'après validation du marchand.
 */
class PublicController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(array_keys(Review::SUBJECTS))],
            'subject_id'   => ['required', 'integer'],
            'rating'       => ['required', 'integer', 'min:1', 'max:5'],
            'author_name'  => ['required', 'string', 'max:120'],
            'author_phone' => ['nullable', 'string', 'max:40'],
            'author_email' => ['nullable', 'email', 'max:160'],
            'comment'      => ['nullable', 'string', 'max:1000'],
            'client_uuid'  => ['nullable', 'string', 'max:64'],
        ]);

        // Résout la ressource réelle : tenant_id et alias dérivés du SERVEUR (anti-spoof).
        $model = Review::SUBJECTS[$data['subject_type']]['model'];
        $subject = $model::find((int) $data['subject_id']);
        if (! $subject) {
            return response()->json(['ok' => false, 'message' => __('Ressource introuvable.')], 404);
        }

        app(ReviewService::class)->submit(
            $data['subject_type'],
            (int) $subject->id,
            $subject->tenant_id ?? null,
            $subject->alias ?? null,
            $data
        );

        return response()->json([
            'ok'      => true,
            'message' => __('Merci ! Votre avis sera publié après validation.'),
        ]);
    }
}
