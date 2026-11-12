<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    private const PROFILE_FIELDS = ['phone', 'document_type', 'document_number'];

    public function index(): AnonymousResourceCollection
    {
        $users = User::query()->orderByDesc('created_at')->get();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $data = $request->validated();
            $user = User::query()->create(Arr::except($data, self::PROFILE_FIELDS));
            $this->ensureCustomerProfile($user, Arr::only($data, self::PROFILE_FIELDS));

            return $user;
        });

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $user->fill(Arr::except($data, self::PROFILE_FIELDS));
        $user->save();
        $this->ensureCustomerProfile($user, Arr::only($data, self::PROFILE_FIELDS));

        return new UserResource($user->refresh());
    }

    public function destroy(Request $request, User $user): Response
    {
        if ((int) $request->user()->id === (int) $user->id) {
            throw new AuthorizationException('You cannot delete your own account.');
        }

        // customers.user_id uses nullOnDelete — keep Customer rows.
        $user->delete();

        return response()->noContent();
    }

    /**
     * Un usuario CUSTOMER necesita su perfil de cliente para usar carrito y checkout.
     * Solo AuthController::register lo creaba; los usuarios creados desde el panel quedaban sin perfil.
     */
    private function ensureCustomerProfile(User $user, array $profile = []): void
    {
        if (! $user->isCustomer()) {
            return;
        }

        $customer = $user->customer()->first();
        if ($customer === null) {
            Customer::query()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $profile['phone'] ?? null,
                'document_type' => $profile['document_type'] ?? null,
                'document_number' => $profile['document_number'] ?? null,
            ]);

            return;
        }

        $changes = array_filter($profile, fn ($v) => $v !== null);
        if ($changes !== []) {
            $customer->fill($changes)->save();
        }
    }
}
