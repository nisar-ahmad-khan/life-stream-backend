<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Donor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Helper to normalize phone number to standard international format (e.g. +923001234567)
     */
    private function normalizePhone(?string $phone): string
    {
        if (!$phone) {
            return '';
        }
        $trimmed = preg_replace('/\s+/', '', trim($phone));
        if (str_starts_with($trimmed, '+')) {
            return $trimmed;
        }
        return '+92' . ltrim($trimmed, '0');
    }

    /**
     * Helper to format User & Donor composite response
     */
    private function formatUserResponse(User $user, ?Donor $donor = null): array
    {
        $donor = $donor ?? $user->donor;

        $lastDonationDateStr = null;
        if ($donor && $donor->last_donation_date) {
            $lastDonationDateStr = is_string($donor->last_donation_date)
                ? substr($donor->last_donation_date, 0, 10)
                : $donor->last_donation_date->format('Y-m-d');
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'fullName' => $user->name,
            'full_name' => $user->name,
            'phone' => $user->phone_number,
            'phone_number' => $user->phone_number,
            'phoneNumber' => $user->phone_number,
            'bloodGroup' => $donor?->blood_group ?? 'O+',
            'blood_group' => $donor?->blood_group ?? 'O+',
            'address' => $donor?->address ?? 'Location not specified',
            'gender' => $donor?->gender ?? 'Not specified',
            'lastDonationDate' => $lastDonationDateStr ?? '',
            'last_donation_date' => $lastDonationDateStr ?? '',
            'isAvailable' => (bool)($donor?->is_available ?? true),
            'is_available' => (bool)($donor?->is_available ?? true),
            'healthStatus' => [
                'hepB' => (bool)($donor?->hep_b ?? true),
                'hepC' => (bool)($donor?->hep_c ?? true),
                'hiv' => (bool)($donor?->hiv ?? true),
            ],
            'donor' => $donor,
        ];
    }

    /**
     * Register a new Donor (Creates User AND Donor record in DB)
     */
    public function register(Request $request)
    {
        $rawPhone = $request->input('phoneNumber', $request->input('phone_number', $request->input('phone')));
        $cleanPhone = $this->normalizePhone($rawPhone);

        // Check if phone already registered
        $existing = User::where('phone_number', $cleanPhone)
            ->orWhere('phone_number', $rawPhone)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A donor account with this phone number (' . $cleanPhone . ') is already registered. Please sign in instead.'
            ], 422);
        }

        $fullName = trim($request->input('fullName', $request->input('name', $request->input('full_name', ''))));
        if (empty($fullName)) {
            return response()->json([
                'success' => false,
                'message' => 'Full Name is required.'
            ], 422);
        }

        $password = $request->input('password');
        if (empty($password) || strlen($password) < 8) {
            return response()->json([
                'success' => false,
                'message' => 'Password must be at least 8 characters long.'
            ], 422);
        }

        // Donor specific fields
        $bloodGroup = $request->input('bloodGroup', $request->input('blood_group', 'O+'));
        $address = $request->input('address', $request->input('location', $request->input('city', 'Location not specified')));
        $gender = $request->input('gender', null);
        $lastDonationDate = $request->input('lastDonationDate', $request->input('last_donation_date', null));
        $isAvailable = $request->boolean('isAvailable', $request->boolean('is_available', true));

        // Health clearance declarations
        $hepB = $request->boolean('hepB', $request->boolean('hep_b', true));
        $hepC = $request->boolean('hepC', $request->boolean('hep_c', true));
        $hiv = $request->boolean('hiv', true);

        try {
            $result = DB::transaction(function () use (
                $fullName, $cleanPhone, $password,
                $bloodGroup, $gender, $address, $lastDonationDate, $isAvailable,
                $hepB, $hepC, $hiv
            ) {
                // 1. Create User
                $user = User::create([
                    'name' => $fullName,
                    'phone_number' => $cleanPhone,
                    'password' => Hash::make($password),
                ]);

                // 2. Create Donor associated with this user
                $donor = Donor::create([
                    'user_id' => $user->id,
                    'blood_group' => $bloodGroup ?: 'O+',
                    'gender' => $gender,
                    'address' => $address ?: 'Location not specified',
                    'last_donation_date' => $lastDonationDate ?: null,
                    'is_available' => $isAvailable,
                    'hep_b' => $hepB,
                    'hep_c' => $hepC,
                    'hiv' => $hiv,
                ]);

                $token = $user->createToken('lifestream-app')->plainTextToken;

                return [
                    'user' => $user,
                    'donor' => $donor,
                    'token' => $token,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Donor registered successfully and profile saved in database.',
                'user' => $this->formatUserResponse($result['user'], $result['donor']),
                'token' => $result['token'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save donor to database: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * User Login
     */
    public function login(Request $request)
    {
        $rawPhone = $request->input('phoneNumber', $request->input('phone_number', $request->input('phone')));
        $cleanPhone = $this->normalizePhone($rawPhone);
        $password = $request->input('password');

        if (empty($rawPhone) || empty($password)) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number and password are required.'
            ], 422);
        }

        $user = User::where('phone_number', $cleanPhone)
            ->orWhere('phone_number', $rawPhone)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone number or password.'
            ], 401);
        }

        // Ensure donor record exists for this user
        if (!$user->donor) {
            $user->donor()->create([
                'blood_group' => 'O+',
                'address' => 'Location not specified',
                'is_available' => true,
                'hep_b' => true,
                'hep_c' => true,
                'hiv' => true,
            ]);
        }

        $user->load('donor');
        $token = $user->createToken('lifestream-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $this->formatUserResponse($user, $user->donor),
            'token' => $token
        ], 200);
    }

    /**
     * Get Authenticated User Profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (!$user->donor) {
            $user->donor()->create([
                'blood_group' => 'O+',
                'address' => 'Location not specified',
                'is_available' => true,
                'hep_b' => true,
                'hep_c' => true,
                'hiv' => true,
            ]);
        }

        $user->load('donor');

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user, $user->donor)
        ], 200);
    }

    /**
     * Update Authenticated User & Donor Profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $name = $request->input('fullName', $request->input('name', $request->input('full_name')));
        if (!empty($name)) {
            $user->name = trim($name);
            $user->save();
        }

        $donor = $user->donor ?: new Donor(['user_id' => $user->id]);

        if ($request->has('bloodGroup') || $request->has('blood_group')) {
            $donor->blood_group = $request->input('bloodGroup', $request->input('blood_group'));
        }
        if ($request->has('address') || $request->has('location') || $request->has('city')) {
            $donor->address = $request->input('address', $request->input('location', $request->input('city')));
        }
        if ($request->has('gender')) {
            $donor->gender = $request->input('gender');
        }
        if ($request->has('lastDonationDate') || $request->has('last_donation_date')) {
            $val = $request->input('lastDonationDate', $request->input('last_donation_date'));
            $donor->last_donation_date = !empty($val) ? $val : null;
        }
        if ($request->has('isAvailable') || $request->has('is_available')) {
            $donor->is_available = $request->boolean('isAvailable', $request->boolean('is_available', true));
        }
        if ($request->has('hepB') || $request->has('hep_b')) {
            $donor->hep_b = $request->boolean('hepB', $request->boolean('hep_b', true));
        }
        if ($request->has('hepC') || $request->has('hep_c')) {
            $donor->hep_c = $request->boolean('hepC', $request->boolean('hep_c', true));
        }
        if ($request->has('hiv')) {
            $donor->hiv = $request->boolean('hiv', true);
        }

        $donor->user_id = $user->id;
        $donor->save();

        $user->load('donor');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully in database.',
            'user' => $this->formatUserResponse($user, $donor)
        ], 200);
    }

    /**
     * Delete Authenticated User & Donor Profile
     */
    public function deleteProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $user->tokens()->delete();
        $user->donor()?->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Donor account deleted successfully from database.'
        ], 200);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
        }
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.'
        ], 200);
    }
}
