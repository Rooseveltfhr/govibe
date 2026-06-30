<?php

namespace Modules\Tagtoa\App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA AUDIT — consultation du journal (lecture seule).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->query('action');
        $query = AuditLog::where('tenant_id', Tenant::id());
        if ($action && array_key_exists($action, AuditService::LABELS)) {
            $query->where('action', $action);
        }

        $logs = $query->orderByDesc('created_at')->paginate(40)->withQueryString();
        $actions = AuditService::LABELS;

        return view('tagtoa::audit.index', compact('logs', 'actions', 'action'));
    }
}
