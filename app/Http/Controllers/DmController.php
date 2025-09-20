<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Channel;
use App\Models\ChannelUser;

class DmController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // 参加している DM または MPIM を取得
        $dms = Channel::query()
            ->where(function ($q) {
                $q->where('is_dm', true)
                    ->orWhere('is_mpim', true);
            })
            ->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNull('left_at'); // まだ参加中
            })
            ->orderBy('updated_at', 'desc')
            ->with(['users:id,name,avatar_url'])
            ->get(['id', 'name', 'is_dm', 'is_mpim', 'updated_at']);

        return response()->json([
            'dms' => $dms
        ]);
    }
}
