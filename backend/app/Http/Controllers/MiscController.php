<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cooperative;
use Illuminate\Http\Request;

class MiscController extends Controller
{
    public function audit(Request $r, Cooperative $coop)
    {
        $q = AuditLog::with('user:id,name')->where('cooperative_id', $coop->id)->latest('id');
        foreach (['action' => 'action', 'subject_type' => 'subject_type', 'user_id' => 'user_id'] as $param => $col) {
            if ($v = $r->query($param)) {
                $q->where($col, $v);
            }
        }

        return response()->json($q->paginate(25));
    }

    public function settings(Cooperative $coop)
    {
        return response()->json(['name' => $coop->name, 'description' => $coop->description, 'city' => $coop->city, 'invite_code' => $coop->invite_code, 'settings' => $coop->settings + Cooperative::DEFAULTS]);
    }

    public function updateSettings(Request $r, Cooperative $coop)
    {
        $d = $r->validate(['name' => 'sometimes|string|min:3|max:80', 'description' => 'nullable|string|max:300', 'city' => 'nullable|string|max:60', 'default_interest_rate' => 'sometimes|numeric|min:0|max:60', 'penalty_percent' => 'sometimes|integer|min:0|max:50', 'loan_max_multiplier' => 'sometimes|integer|min:1|max:20', 'loan_min_amount' => 'sometimes|integer|min:1000']);
        $settings = array_merge($coop->settings, array_intersect_key($d, Cooperative::DEFAULTS));
        $coop->update(array_intersect_key($d, array_flip(['name', 'description', 'city'])) + ['settings' => $settings]);

        return $this->settings($coop->refresh());
    }

    public function notifications(Request $r, Cooperative $coop)
    {
        $list = $r->user()->notifications()->latest()->take(40)->get()->filter(fn ($n) => ($n->data['cooperative_id'] ?? $coop->id) === $coop->id)->values();

        return response()->json(['unread' => $list->whereNull('read_at')->count(), 'items' => $list->map(fn ($n) => ['id' => $n->id, 'title' => $n->data['title'], 'body' => $n->data['body'], 'type' => $n->data['type'] ?? 'info', 'read' => (bool) $n->read_at, 'created_at' => $n->created_at])]);
    }

    public function readAll(Request $r)
    {
        $r->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function read(Request $r, Cooperative $coop, string $id)
    {
        $r->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['ok' => true]);
    }
}
