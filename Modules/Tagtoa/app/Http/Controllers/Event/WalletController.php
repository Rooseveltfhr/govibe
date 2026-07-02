<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Actions\Event\Wallet\ChargeWallet;
use Modules\Tagtoa\App\Actions\Event\Wallet\EncodeParticipantCard;
use Modules\Tagtoa\App\Actions\Event\Wallet\IssueNfcTag;
use Modules\Tagtoa\App\Actions\Event\Wallet\OpenEventWalletAccounts;
use Modules\Tagtoa\App\Actions\Event\Wallet\PayoutToOrganizer;
use Modules\Tagtoa\App\Actions\Event\Wallet\RefundWallet;
use Modules\Tagtoa\App\Actions\Event\Wallet\ResolveNfcTag;
use Modules\Tagtoa\App\Actions\Event\Wallet\TopUpWallet;
use Modules\Tagtoa\App\Exceptions\InsufficientFundsException;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletTxn;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TAGTOA EVENT — dashboard & terminal du wallet closed-loop.
 * Toute la logique argent est déléguée aux classes Action (double-entry, atomique).
 */
class WalletController extends Controller
{
    /* ---------------- Dashboard organisateur ---------------- */

    public function index(int $id): View
    {
        $event = $this->own($id);
        app(OpenEventWalletAccounts::class)->handle($event); // garantit les comptes système

        $system = WalletAccount::where('event_id', $event->id)
            ->whereIn('type', WalletAccount::SYSTEM_TYPES)->get()->keyBy('type');
        $vendors = WalletAccount::where('event_id', $event->id)
            ->where('type', WalletAccount::TYPE_VENDOR)->orderBy('owner_label')->get();
        $participantsCount = WalletAccount::where('event_id', $event->id)
            ->where('type', WalletAccount::TYPE_PARTICIPANT)->count();
        $loaded = (int) (WalletAccount::where('event_id', $event->id)
            ->where('type', WalletAccount::TYPE_PARTICIPANT)->sum('balance_minor'));
        $txns = WalletTxn::where('event_id', $event->id)->orderByDesc('id')->limit(50)->get();
        $ticketTypes = $event->ticketTypes()->get();

        return view('tagtoa::event.wallet', compact(
            'event', 'system', 'vendors', 'participantsCount', 'loaded', 'txns', 'ticketTypes'
        ));
    }

    /** Encode une carte NFC : billet (entrée) + wallet + recharge initiale optionnelle. */
    public function encode(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate([
            'uid'            => ['required', 'string', 'max:120'],
            'name'           => ['required', 'string', 'max:120'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'ticket_type_id' => ['nullable', 'integer'],
            'amount'         => ['nullable', 'numeric', 'min:0'],
            'kind'           => ['nullable', Rule::in(\Modules\Tagtoa\App\Models\Event\NfcTag::KINDS)],
        ]);

        if (! empty($data['ticket_type_id'])) {
            $ok = \Modules\Tagtoa\App\Models\Event\TicketType::where('event_id', $event->id)
                ->whereKey($data['ticket_type_id'])->exists();
            if (! $ok) {
                $data['ticket_type_id'] = null;
            }
        }

        $res = app(EncodeParticipantCard::class)->handle($event, $data);

        if (! empty($data['amount']) && (float) $data['amount'] > 0 && $res['tag']->walletAccount) {
            $acct = $res['tag']->walletAccount;
            app(TopUpWallet::class)->handle($acct, Money::toMinor($data['amount'], $acct->currency), [
                'idempotency_key' => 'encode-'.\Illuminate\Support\Str::uuid()->toString(),
                'payment_ref'     => 'ENCODAGE',
            ]);
        }

        return back()->with('success', __('Carte encodée.').' '.__('Billet').' : '.$res['ticket']->code);
    }

    /** Réglages wallet de l'event (e-mail de notification organisateur). */
    public function settings(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate(['notify_email' => ['nullable', 'email', 'max:160']]);
        $event->update(['notify_email' => $data['notify_email'] ?: null]);

        return back()->with('success', __('Réglages enregistrés.'));
    }

    public function terminal(int $id): View
    {
        $event = $this->own($id);
        $vendors = WalletAccount::where('event_id', $event->id)
            ->where('type', WalletAccount::TYPE_VENDOR)->orderBy('owner_label')->get();

        return view('tagtoa::event.wallet-terminal', compact('event', 'vendors'));
    }

    /* ---------------- Actions dashboard ---------------- */

    public function addVendor(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate(['label' => ['required', 'string', 'max:120']]);
        OpenEventWalletAccounts::vendor($event, $data['label']);

        return back()->with('success', __('Stand ajouté.'));
    }

    public function issueTag(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate([
            'uid'   => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'kind'  => ['nullable', Rule::in(\Modules\Tagtoa\App\Models\Event\NfcTag::KINDS)],
        ]);
        app(IssueNfcTag::class)->handle($event, $data['uid'], [
            'label' => $data['label'] ?? null,
            'phone' => $data['phone'] ?? null,
            'kind'  => $data['kind'] ?? 'card',
        ]);

        return back()->with('success', __('Tag NFC enregistré.'));
    }

    public function topUp(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate([
            'uid'         => ['required', 'string', 'max:120'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'payment_ref' => ['nullable', 'string', 'max:120'],
        ]);
        $participant = app(ResolveNfcTag::class)->handle($event, $data['uid']);
        if (! $participant) {
            return back()->with('error', __('Tag introuvable.'));
        }
        app(TopUpWallet::class)->handle($participant, Money::toMinor($data['amount'], $participant->currency), [
            'idempotency_key' => 'topup-'.\Illuminate\Support\Str::uuid()->toString(),
            'payment_ref'     => $data['payment_ref'] ?? null,
        ]);

        $fresh = $participant->fresh();
        app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class)->push([
            'channels' => ['whatsapp'],
            'phone'    => $fresh->owner_phone,
            'subject'  => __('Recharge TAGTOA'),
            'body'     => __('Recharge effectuée.').' '.__('Nouveau solde').' : '.Money::formatMinor((int) $fresh->balance_minor, $fresh->currency),
        ]);

        return back()->with('success', __('Recharge effectuée.'));
    }

    public function payout(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate(['vendor_id' => ['required', 'integer']]);
        $vendor = WalletAccount::where('event_id', $event->id)
            ->where('type', WalletAccount::TYPE_VENDOR)->findOrFail($data['vendor_id']);
        $balance = (int) $vendor->balance_minor;
        if ($balance <= 0) {
            return back()->with('error', __('Rien à régler pour ce stand.'));
        }
        app(PayoutToOrganizer::class)->handle($vendor, $balance, [
            'idempotency_key' => 'payout-'.$vendor->id.'-'.$balance.'-'.now()->format('YmdHis'),
        ]);

        return back()->with('success', __('Stand réglé.'));
    }

    /* ---------------- Endpoints terminal (JSON) ---------------- */

    /** Tap NFC → solde du participant (avant achat). */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $event = $this->own($id);
        $data = $request->validate(['uid' => ['required', 'string', 'max:120']]);
        $acct = app(ResolveNfcTag::class)->handle($event, $data['uid']);
        if (! $acct) {
            return response()->json(['ok' => false, 'message' => __('Tag introuvable.')], 404);
        }

        return response()->json([
            'ok'            => true,
            'account_id'    => $acct->id,
            'holder'        => $acct->owner_label,
            'balance_minor' => (int) $acct->balance_minor,
            'balance'       => Money::formatMinor((int) $acct->balance_minor, $acct->currency),
        ]);
    }

    /** Achat chez un stand : débit participant → crédit vendeur. */
    public function charge(Request $request, int $id): JsonResponse
    {
        $event = $this->own($id);
        $data = $request->validate([
            'uid'         => ['required', 'string', 'max:120'],
            'vendor_id'   => ['required', 'integer'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $participant = app(ResolveNfcTag::class)->handle($event, $data['uid']);
        if (! $participant) {
            return response()->json(['ok' => false, 'message' => __('Tag introuvable.')], 404);
        }
        $vendor = WalletAccount::where('event_id', $event->id)
            ->where('type', WalletAccount::TYPE_VENDOR)->find($data['vendor_id']);
        if (! $vendor) {
            return response()->json(['ok' => false, 'message' => __('Stand introuvable.')], 404);
        }

        try {
            $txn = app(ChargeWallet::class)->handle(
                $participant, $vendor,
                Money::toMinor($data['amount'], $participant->currency),
                ['idempotency_key' => $data['client_uuid'] ?? ('charge-'.\Illuminate\Support\Str::uuid()->toString())]
            );
        } catch (InsufficientFundsException $e) {
            return response()->json([
                'ok'      => false,
                'code'    => 'insufficient_funds',
                'message' => __('Solde insuffisant.'),
                'balance' => Money::formatMinor((int) $participant->fresh()->balance_minor, $participant->currency),
            ], 422);
        }

        $fresh = $participant->fresh();

        app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class)->push([
            'channels' => ['whatsapp'],
            'phone'    => $fresh->owner_phone,
            'subject'  => __('Achat TAGTOA'),
            'body'     => __('Achat').' : '.Money::formatMinor((int) $txn->amount_minor, $txn->currency)
                .' — '.$vendor->owner_label.'. '.__('Nouveau solde').' : '.Money::formatMinor((int) $fresh->balance_minor, $fresh->currency),
        ]);

        return response()->json([
            'ok'        => true,
            'reference' => $txn->reference,
            'charged'   => Money::formatMinor((int) $txn->amount_minor, $txn->currency),
            'balance'   => Money::formatMinor((int) $fresh->balance_minor, $fresh->currency),
        ]);
    }

    /* ---------------- Export réconciliation ---------------- */

    public function export(int $id): StreamedResponse
    {
        $event = $this->own($id);
        $txns = WalletTxn::where('event_id', $event->id)->orderBy('id')->get();

        $filename = 'wallet-'.$event->alias.'.csv';

        return Response::streamDownload(function () use ($txns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'type', 'amount', 'currency', 'source', 'dest', 'created_at']);
            foreach ($txns as $t) {
                fputcsv($out, [
                    $t->reference, $t->type,
                    Money::fromMinor((int) $t->amount_minor, $t->currency), $t->currency,
                    $t->source_account_id, $t->dest_account_id,
                    optional($t->created_at)->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function own(int $id): Event
    {
        return Event::where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
