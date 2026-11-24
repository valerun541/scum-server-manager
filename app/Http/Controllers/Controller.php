<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Единообразный ответ: JSON для AJAX/API-запросов, flash-сообщение + back() для форм.
     */
    protected function respondJson(Request $request, bool $ok, string $msg, array $extra = [])
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(array_merge(['success' => $ok, 'message' => $msg], $extra));
        }

        return back()->with($ok ? 'success' : 'error', $msg);
    }
}
