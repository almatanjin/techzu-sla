<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SearchCustomersRequest;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(SearchCustomersRequest $request): AnonymousResourceCollection
    {
        $search = $request->input('search');

        $query = User::query()
            ->where('role', UserRole::Customer->value)
            ->where('is_active', true);

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->limit(20)->get();

        return CustomerResource::collection($customers);
    }

    public function show(Request $request, User $user): CustomerResource
    {
        Gate::authorize('searchCustomers', User::class);

        if ($user->role !== UserRole::Customer) {
            abort(404);
        }

        return new CustomerResource($user);
    }
}

