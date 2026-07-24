<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;

class MessageAdminController extends Controller
{
    public function index()
    {
        return view('admin.messages', ['messages' => ContactMessage::latest()->paginate(20)]);
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return back()->with('ok', 'Message supprimé.');
    }
}
