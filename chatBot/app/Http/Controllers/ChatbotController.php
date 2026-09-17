<?php

namespace App\Http\Controllers;

use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ChatbotController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function index(): View
    {
        return view('chatbot.index');
    }

    public function respond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        try {
            return response()->json($this->chatbot->respond($validated['message']));
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
