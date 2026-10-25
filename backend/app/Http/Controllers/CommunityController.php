<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Cooperative;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Membership;
use App\Services\Notifier;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function announcements(Cooperative $coop)
    {
        return response()->json(Announcement::with('author:id,name')->where('cooperative_id', $coop->id)->orderByDesc('pinned')->orderByDesc('published_at')->get());
    }

    public function saveAnnouncement(Request $r, Cooperative $coop, ?Announcement $announcement = null)
    {
        $notifier = app(Notifier::class);
        $d = $r->validate(['title' => 'required|string|min:3|max:120', 'body' => 'required|string|min:3|max:3000', 'pinned' => 'nullable|boolean']);
        $d['pinned'] = (bool) ($d['pinned'] ?? false);
        if ($announcement) {
            $announcement->update($d);
        } else {
            $announcement = Announcement::create($d + ['cooperative_id' => $coop->id, 'author_id' => $r->user()->id, 'published_at' => now()]);
            $notifier->send(Membership::with('user')->where('cooperative_id', $coop->id)->where('status', 'active')->get(), 'Annonce : '.$d['title'], str($d['body'])->limit(90), 'announcement');
        }

        return response()->json($announcement, $announcement->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteAnnouncement(Cooperative $coop, Announcement $announcement)
    {
        $announcement->delete();

        return response()->json(['ok' => true]);
    }

    public function meetings(Request $r, Cooperative $coop)
    {
        $m = $r->attributes->get('membership');

        return response()->json(Meeting::with('attendances')->where('cooperative_id', $coop->id)->orderByDesc('starts_at')->get()->map(fn ($mt) => [
            'id' => $mt->id, 'title' => $mt->title, 'starts_at' => $mt->starts_at, 'location' => $mt->location, 'agenda' => $mt->agenda, 'minutes' => $mt->minutes, 'status' => $mt->status,
            'present' => $mt->attendances->where('status', 'present')->count(), 'my_status' => $mt->attendances->firstWhere('membership_id', $m->id)?->status,
            'attendances' => $m->isManager() ? $mt->attendances->map(fn ($a) => ['membership_id' => $a->membership_id, 'status' => $a->status])->values() : [],
        ]));
    }

    public function saveMeeting(Request $r, Cooperative $coop, ?Meeting $meeting = null)
    {
        $notifier = app(Notifier::class);
        $d = $r->validate(['title' => 'required|string|min:3|max:120', 'starts_at' => 'required|date', 'location' => 'nullable|string|max:120', 'agenda' => 'nullable|string|max:2000', 'minutes' => 'nullable|string|max:5000', 'status' => 'nullable|in:scheduled,done,cancelled']);
        if ($meeting) {
            $meeting->update($d);
        } else {
            $meeting = Meeting::create($d + ['cooperative_id' => $coop->id, 'status' => 'scheduled']);
            $notifier->send(Membership::with('user')->where('cooperative_id', $coop->id)->where('status', 'active')->get(), 'Réunion : '.$d['title'], 'Le '.\Illuminate\Support\Carbon::parse($d['starts_at'])->translatedFormat('d F à H:i').', '.($d['location'] ?? 'lieu à préciser'), 'meeting');
        }

        return response()->json($meeting, $meeting->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteMeeting(Cooperative $coop, Meeting $meeting)
    {
        $meeting->delete();

        return response()->json(['ok' => true]);
    }

    public function attendance(Request $r, Cooperative $coop, Meeting $meeting)
    {
        $d = $r->validate(['attendances' => 'required|array', 'attendances.*.membership_id' => 'required|integer', 'attendances.*.status' => 'nullable|in:present,absent,excused']);
        $valid = Membership::where('cooperative_id', $coop->id)->pluck('id');
        foreach ($d['attendances'] as $a) {
            if ($valid->contains($a['membership_id'])) {
                MeetingAttendance::updateOrCreate(['meeting_id' => $meeting->id, 'membership_id' => $a['membership_id']], ['status' => $a['status'] ?? null]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
