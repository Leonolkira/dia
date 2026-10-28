<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\password;
use Illuminate\support\str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    public function register(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
    ]);
    $user->assignRole('client');

    event(new Registered($user)); // déclenche l'envoi automatique de l'email de vérification

    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
    ], 201);
}

public function verifyEmail(Request $request, $id, $hash)
{
    $user = User::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Lien de vérification invalide.'], 403);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Cet email est déjà vérifié.']);
    }

    $user->markEmailAsVerified();

    return response()->json(['message' => 'Email vérifié avec succès.']);
}

public function resendVerificationEmail(Request $request)
{
    if ($request->user()->hasVerifiedEmail()) {
        return response()->json(['message' => 'Cet email est déjà vérifié.']);
    }

    $request->user()->sendEmailVerificationNotification();

    return response()->json(['message' => 'Email de vérification renvoyé.']);
}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        $user->load('roles');
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Déconnecté de tous les appareils']);
    }

    public function updateProfile(Request $request)
{
    $user = $request->user();

    $validated = $request->validate([
        'name' => 'sometimes|required|string|max:255',
        'phone' => 'nullable|string|max:20',
        'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
    ]);

    // Si l'email change, on doit re-demander une vérification
    if (isset($validated['email']) && $validated['email'] !== $user->email) {
        $user->email_verified_at = null;
    }

    $user->update($validated);

    if (isset($validated['email']) && $user->wasChanged('email')) {
        $user->sendEmailVerificationNotification();
    }

    return response()->json($user);
}

    public function changePassword(Request $request)
{
    $validated = $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = $request->user();

    if (! Hash::check($validated['current_password'], $user->password)) {
        return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
    }

    $user->update(['password' => Hash::make($validated['password'])]);

    // Révoque tous les tokens sauf celui utilisé pour cette requête (garde l'utilisateur connecté sur l'appareil actuel)
    $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

    return response()->json(['message' => 'Mot de passe modifié avec succès.']);
}

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
{
    $request->validate(['email' => 'required|email']);

    Password::sendResetLink($request->only('email'));

    // Même réponse dans tous les cas : on ne révèle pas si l'adresse existe
    return response()->json([
        'message' => "Si un compte existe pour cette adresse, un lien de réinitialisation vient d'être envoyé.",
    ]);
}

public function resetPassword(Request $request)
{
    $validated = $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $status = Password::reset(
        $validated,
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            // Révoque tous les anciens tokens Sanctum (sécurité : si le compte a été compromis)
            $user->tokens()->delete();
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }

    return response()->json(['message' => 'Ce lien de réinitialisation est invalide ou expiré.'], 422);
}

public function deleteAccount(Request $request)
{
    $validated = $request->validate([
        'password' => 'required|string',
    ]);

    $user = $request->user();

    if (! Hash::check($validated['password'], $user->password)) {
        return response()->json(['message' => 'Mot de passe incorrect.'], 422);
    }

    $user->tokens()->delete(); // révoque tous les accès immédiatement
    $user->delete(); // soft delete grâce au trait

    return response()->json(['message' => 'Compte supprimé avec succès.']);
}
}