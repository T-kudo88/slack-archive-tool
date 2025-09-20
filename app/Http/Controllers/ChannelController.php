<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Channel;

class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // パブリックチャンネル一覧（DM ではない）
        $channels = Channel::query()
            ->where('is_dm', false)
            ->where('is_mpim', false)
            ->orderBy('name')
            ->get(['id', 'name', 'is_private', 'member_count']);

        return response()->json([
            'channels' => $channels
        ]);
    }
}
