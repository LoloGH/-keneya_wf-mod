<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Api;

use Keneya\Dme\Dme;
use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification de l'API par jeton (§43).
 *
 * Le jeton hérite strictement du rôle du porteur : les policies restent
 * la seule autorité d'autorisation. Un compte désactivé ne peut obtenir
 * aucun jeton.
 */
class AuthApiController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = Dme::userQuery()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password) || ! $user->isActive()) {
            AuditLog::record(
                action: 'login_failed',
                outcome: 'denied',
                description: 'Échec d\'authentification API pour '.$data['email'],
            );

            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte actif.',
            ]);
        }

        AuditLog::record(action: 'login', subject: $user, description: 'S\'est authentifié par API');

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->displayName(),
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->displayName(),
            'email' => $user->email,
            'service' => $user->service?->name,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Jeton révoqué.']);
    }
}
