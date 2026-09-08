<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectComment;
use Illuminate\Http\RedirectResponse;

class ProjectCommentController extends Controller
{
    public function index()
    {
        $comments = ProjectComment::with('project')->orderByDesc('created_at')->get();

        return view('admin.projets.comments.index', compact('comments'));
    }

    public function approve(ProjectComment $comment): RedirectResponse
    {
        $comment->update(['is_approved' => true]);

        return back()->with('status', 'Commentaire approuvé.');
    }

    public function destroy(ProjectComment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('status', 'Commentaire supprimé.');
    }
}
