<?php

namespace App\Http\Requests;

class AutomationSaveRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'workflow_id' => 'nullable|string|max:64',
            'name' => 'required|string|max:255',
            'message' => 'nullable|string|max:5000',
            'enabled' => 'sometimes',
            'trigger_type' => 'required|string|in:chat_command,event,schedule,webhook',
            'trigger_chat_trigger' => 'nullable|string|max:64',
            'trigger_chat_aliases' => 'nullable|string|max:255',
            'trigger_chat_cooldown' => 'nullable|integer|min:0',
            'trigger_event_event' => 'nullable|string|max:64',
            'trigger_schedule_interval' => 'nullable|string|max:16',
            'trigger_schedule_minutes' => 'nullable|integer|min:1',
            'trigger_webhook_path' => 'nullable|string|max:255',
            'nodes_json' => 'nullable|string|max:500000',
            'connections_json' => 'nullable|string|max:100000',
        ];
    }
}
