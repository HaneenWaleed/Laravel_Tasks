<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatbotAskRequest;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    public function __construct(private ChatbotService $chatbot) {}

    public function index(): View
    {
        return view('chatbot.index', [
            'aiEnabled' => $this->chatbot->openAiEnabled() || $this->chatbot->geminiEnabled(),
        ]);
    }

    public function ask(ChatbotAskRequest $request): JsonResponse
    {
        $result = $this->chatbot->reply($request->validated('message'));

        return response()->json([
            'reply' => $result['reply'],
            'intent' => $result['intent'],
        ]);
    }
}
