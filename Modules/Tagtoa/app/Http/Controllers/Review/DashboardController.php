<?php

namespace Modules\Tagtoa\App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Review\Review;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA REVIEWS — modération marchand (publier / rejeter / répondre).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = Review::where('tenant_id', Tenant::id());
        if (in_array($status, Review::STATUSES, true)) {
            $query->where('status', $status);
        }

        $reviews = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'pending'  => $this->count('pending'),
            'approved' => $this->count('approved'),
            'rejected' => $this->count('rejected'),
        ];

        return view('tagtoa::review.index', compact('reviews', 'counts', 'status'));
    }

    public function setStatus(Request $request, int $id): RedirectResponse
    {
        $review = $this->own($id);
        $data = $request->validate(['status' => ['required', Rule::in(Review::STATUSES)]]);
        $review->update(['status' => $data['status']]);

        if (in_array($data['status'], ['approved', 'rejected'], true)) {
            app(AuditService::class)->log('review.'.$data['status'], $review, $review->author_name);
        }

        return back()->with('success', __('Avis mis à jour.'));
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $review = $this->own($id);
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:1000']]);
        $review->update([
            'reply'      => $data['reply'] ?: null,
            'replied_at' => $data['reply'] ? now() : null,
        ]);

        if ($data['reply']) {
            app(AuditService::class)->log('review.replied', $review, $review->author_name);
        }

        return back()->with('success', __('Réponse enregistrée.'));
    }

    public function feature(int $id): RedirectResponse
    {
        $review = $this->own($id);
        $review->update(['is_featured' => ! $review->is_featured]);

        return back()->with('success', __('Avis mis à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $review = $this->own($id);
        app(AuditService::class)->log('review.deleted', $review, $review->author_name);
        $review->delete();

        return back()->with('success', __('Avis supprimé.'));
    }

    protected function own(int $id): Review
    {
        return Review::where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function count(string $status): int
    {
        return (int) Review::where('tenant_id', Tenant::id())->where('status', $status)->count();
    }
}
