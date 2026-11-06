<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Services\Sla\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class HolidayController extends Controller
{
    public function __construct(
        private readonly HolidayService $holidayService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        if ($user === null || ! $user->isAdmin() || ! $user->is_active) {
            abort(403);
        }

        $year = $request->has('year') ? (int) $request->input('year') : null;
        $holidays = $this->holidayService->listHolidays($year);

        return HolidayResource::collection($holidays);
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = $this->holidayService->createHoliday(
            date: (string) $request->validated('date'),
            name: (string) $request->validated('name'),
        );

        return (new HolidayResource($holiday))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Holiday $holiday): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isAdmin() || ! $user->is_active) {
            abort(403);
        }

        $this->holidayService->deleteHoliday($holiday);

        return response()->noContent();
    }
}

