<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->addresses()->latest()->get(['id', 'label', 'street', 'city', 'phone']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|in:Maison,Bureau,Autre',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
        ]);

        if ($request->user()->addresses()->count() >= 10) {
            return response()->json(['message' => 'Tu ne peux pas enregistrer plus de 10 adresses.'], 422);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json($address->only(['id', 'label', 'street', 'city', 'phone']), 201);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->addresses()->findOrFail($id)->delete();

        return response()->json(['message' => 'Adresse supprimée']);
    }
}