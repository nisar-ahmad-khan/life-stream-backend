<?php

namespace App\Http\Controllers;

use App\Models\Donor;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    /**
     * Fetch all donors with optional filtering
     */
    public function index(Request $request)
    {
        try {
            $query = Donor::with('user');

            // Filter by blood group
            if ($request->filled('blood_group') && strtoupper($request->blood_group) !== 'ALL') {
                $query->where('blood_group', $request->blood_group);
            }

            // Filter by status / availability
            if ($request->filled('status') && strtoupper($request->status) !== 'ALL') {
                if (strtolower($request->status) === 'available') {
                    $query->where('is_available', true);
                }
            }

            // Search by name, phone, address, blood group
            if ($request->filled('search')) {
                $term = trim($request->search);
                $query->where(function ($q) use ($term) {
                    $q->where('address', 'like', "%{$term}%")
                      ->orWhere('blood_group', 'like', "%{$term}%")
                      ->orWhereHas('user', function ($uq) use ($term) {
                          $uq->where('name', 'like', "%{$term}%")
                             ->orWhere('phone_number', 'like', "%{$term}%");
                      });
                });
            }

            $donors = $query->orderBy('created_at', 'desc')->get();

            if ($donors->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "Be the first to give — because somewhere, someone's life is waiting on your courage to show up.",
                    'donors' => []
                ], 200);
            }

            return response()->json([
                'success' => true,
                'donors' => $donors
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => $err->getMessage(),
                'donors' => []
            ], 500);
        }
    }

    /**
     * Store or update donor profile for authenticated user
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $donor = Donor::updateOrCreate(
            ['user_id' => $user->id],
            [
                'blood_group' => $request->input('blood_group', $request->input('bloodGroup', 'O+')),
                'gender' => $request->input('gender'),
                'address' => $request->input('address', $request->input('location', 'Location not specified')),
                'last_donation_date' => $request->input('last_donation_date', $request->input('lastDonationDate')),
                'is_available' => $request->boolean('is_available', $request->boolean('isAvailable', true)),
                'hep_b' => $request->boolean('hep_b', $request->boolean('hepB', true)),
                'hep_c' => $request->boolean('hep_c', $request->boolean('hepC', true)),
                'hiv' => $request->boolean('hiv', true),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Donor profile registered successfully.',
            'donor' => $donor->load('user'),
        ], 201);
    }
}
