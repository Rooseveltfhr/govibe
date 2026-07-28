<?php

namespace App\Http\Controllers\ERP\CRM;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\OutboundMessage;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $channel = $request->get('channel', 'all');
        $status  = $request->get('status', 'all');

        $query = OutboundMessage::with('sentBy')->latest();

        if ($channel !== 'all') {
            $query->where('channel', $channel);
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $messages = $query->paginate(30)->withQueryString();

        $stats = [
            'total'    => OutboundMessage::count(),
            'sent'     => OutboundMessage::where('status', 'sent')->count(),
            'failed'   => OutboundMessage::where('status', 'failed')->count(),
            'whatsapp' => OutboundMessage::where('channel', 'whatsapp')->count(),
            'email'    => OutboundMessage::where('channel', 'email')->count(),
        ];

        return view('erp.crm.notifications.index', compact('messages', 'stats', 'channel', 'status'));
    }

    public function send(Request $request, NotificationService $notif): RedirectResponse
    {
        $data = $request->validate([
            'channel'   => ['required', 'in:whatsapp,email'],
            'recipient' => ['required', 'string', 'max:255'],
            'name'      => ['nullable', 'string', 'max:255'],
            'subject'   => ['nullable', 'string', 'max:255'],
            'message'   => ['required', 'string', 'max:4000'],
        ]);

        if ($data['channel'] === 'whatsapp') {
            $notif->whatsapp($data['recipient'], $data['message'], $data['name'] ?? null);
        } else {
            $subject = $data['subject'] ?: 'Message de GOVIBE Innovation Hub';
            $html    = nl2br(e($data['message']));
            $notif->email($data['recipient'], $data['name'] ?? '', $subject, "<p>{$html}</p>");
        }

        return back()->with('success', 'Message envoyé avec succès.');
    }
}
