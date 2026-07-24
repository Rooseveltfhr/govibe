<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheckinLog;
use App\Models\Registration;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function index()
    {
        return view('admin.checkin', [
            'total'     => Registration::where('status', '!=', 'cancelled')->count(),
            'checkedIn' => Registration::whereNotNull('checked_in_at')->count(),
            'lastHour'  => CheckinLog::where('result', 'ok')->where('created_at', '>=', now()->subHour())->count(),
        ]);
    }

    /** Scan QR ou saisie du numéro de billet. */
    public function scan(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|max:190']);
        $code = trim($data['code']);

        // Le QR contient l'URL du billet, « numéro|token » (badge) ou le token brut.
        if (preg_match('#/billet/([a-z0-9]+)#i', $code, $m)) {
            $code = $m[1];
        } elseif (str_contains($code, '|')) {
            $code = trim(explode('|', $code)[1] ?? '');
        }

        $registration = Registration::where('qr_token', $code)
            ->orWhere('number', strtoupper($code))
            ->with('category')->first();

        if (! $registration) {
            return response()->json(['status' => 'not_found', 'message' => __('Billet introuvable.')]);
        }

        return response()->json($this->process($request, $registration));
    }

    /** Recherche manuelle (nom, email, numéro). */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = Registration::where('status', '!=', 'cancelled')
            ->where(function ($sub) use ($q) {
                $sub->where('number', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->with('category')->take(8)->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'token'      => $r->qr_token,
                'number'     => $r->number,
                'name'       => $r->fullName(),
                'category'   => $r->category?->name,
                'checked_in' => (bool) $r->checked_in_at,
                'paid'       => $r->isPaid(),
            ]);

        return response()->json(['results' => $results]);
    }

    private function process(Request $request, Registration $registration): array
    {
        $payload = [
            'name'     => $registration->fullName(),
            'number'   => $registration->number,
            'category' => $registration->category?->name,
            'audience' => $registration->audienceLabel(),
        ];

        if ($registration->status === 'cancelled') {
            $this->log($request, $registration, 'refused');

            return $payload + ['status' => 'refused', 'message' => __('Inscription annulée — accès refusé.')];
        }

        if ($registration->checked_in_at) {
            $this->log($request, $registration, 'already');

            return $payload + [
                'status'  => 'already',
                'message' => __('Déjà enregistré(e) à :time.', ['time' => $registration->checked_in_at->format('H:i')]),
            ];
        }

        $registration->update(['checked_in_at' => now()]);
        $this->log($request, $registration, 'ok');

        return $payload + [
            'status'  => 'ok',
            'message' => $registration->isPaid() ? __('Bienvenue ! Check-in confirmé.') : __('Check-in OK — paiement en attente à régulariser.'),
            'paid'    => $registration->isPaid(),
        ];
    }

    private function log(Request $request, Registration $registration, string $result): void
    {
        CheckinLog::create([
            'registration_id' => $registration->id,
            'user_id'         => $request->user()?->id,
            'method'          => $request->input('method', 'qr'),
            'result'          => $result,
        ]);
    }
}
