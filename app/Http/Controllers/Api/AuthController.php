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

public function verifyEmail(EmailVerificationRequest $request)
{
    $request->fulfill();

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

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
{
    $request->validate(['email' => 'required|email']);

    $status = Password::sendResetLink(
        $request->only('email')
    );

    if ($status === Password::RESET_LINK_SENT) {
        return response()->json(['message' => 'Un lien de réinitialisation a été envoyé par email.']);
    }

    // Message volontairement vague pour ne pas révéler si l'email existe ou non (sécurité)
    return response()->json(['message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.']);
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
}