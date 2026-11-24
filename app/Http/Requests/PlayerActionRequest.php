<?php

namespace App\Http\Requests;

class PlayerActionRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:give_money,give_gold,give_fame,spawn_item,chest_full,spawn_vehicle,starter_pack,wargm_check,god_mode,immortality,show_nameplates,show_player_info,suicide,unstuck,silence,unsilence,knockout,announce,notify,chat,teleport_to,teleport_to_me,teleport_to_vehicle,set_all_skills,airdrop_at,cargo_drop,spawn_npc',
            'steam_id' => 'required|string|max:32',
            'amount' => 'nullable|numeric|min:0',
            'item' => 'nullable|string|max:255',
            'chest_item' => 'nullable|string|max:255',
            'count' => 'nullable|integer|min:1',
            'vehicle' => 'nullable|string|max:255',
            'npc' => 'nullable|string|max:255',
            'seconds' => 'nullable|integer|min:1',
            'message' => 'nullable|string|max:5000',
            'type' => 'nullable|integer|min:0',
            'color' => 'nullable|string|max:64',
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'z' => 'nullable|numeric',
            'enabled' => 'nullable',
        ];
    }
}
