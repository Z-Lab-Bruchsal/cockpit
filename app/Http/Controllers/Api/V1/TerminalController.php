<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Terminal\TerminalBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function __construct(private TerminalBookingService $bookingService) {}

    public function book(Request $request, string $chip): JsonResponse
    {
        return response()->json(
            $this->bookingService->book($request->attributes->get('terminal'), $chip)->toArray(),
        );
    }

    public function bookingInfo(Request $request, string $chip): JsonResponse
    {
        return response()->json(
            $this->bookingService->info($request->attributes->get('terminal'), $chip)->toArray(),
        );
    }
}
