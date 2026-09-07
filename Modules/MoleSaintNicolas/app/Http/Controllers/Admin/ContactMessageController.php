<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;

class ContactMessageController extends Controller
{
    public function index()
    {
        $messages = ContactMessage::orderByRaw('read_at is not null')
            ->latest()
            ->get();

        return view('admin.contact-messages.index', compact('messages'));
    }

    public function markRead(ContactMessage $message): RedirectResponse
    {
        if (! $message->isRead()) {
            $message->update(['read_at' => now()]);
        }

        return redirect()->route('admin.messages.index');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('status', 'Message supprimé.');
    }
}
