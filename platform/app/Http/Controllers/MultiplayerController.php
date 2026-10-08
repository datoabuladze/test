<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Services\Multiplayer\RoomManager;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MultiplayerController extends Controller
{
    public function index(): View
    {
        return view('rooms.index', [
            'seo' => Seo::make(__('Play with a friend'), __('Create a private room, share the link and play Tic-Tac-Toe or Connect Four with a friend in real time.')),
        ]);
    }

    public function show(Request $request, GameRoom $room, RoomManager $rooms): View
    {
        abort_if($room->expires_at->isPast(), 410);
        $seat = $rooms->seat($room, $request->cookie('room_'.$room->code));

        return view('rooms.show', [
            'room' => $room,
            'seat' => $seat,
            'seo' => Seo::make(__('Private game room'))->noindex(),
        ]);
    }
}
