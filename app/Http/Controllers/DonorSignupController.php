<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDonorRegistrationRequest;
use App\Models\BloodType;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class DonorSignupController extends Controller
{
    /**
     * Display donor sign-up form.
     */
    public function create()
    {
        return view('signup');
    }

    /**
     * Store donor registration.
     */
    public function store(StoreDonorRegistrationRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $bloodType = BloodType::firstOrCreate([
                'blood_type' => $validated['blood_type'],
            ]);

            $location = Location::create([
                'street_address' => $validated['street_address'],
                'barangay_name' => $validated['barangay'],
                'city' => $validated['city'],
                'province' => $validated['province'],
            ]);

            $donor = Donor::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'gender' => $validated['gender'],
                'birthdate' => $validated['birthdate'],
                'contact_number' => $validated['phone'],
                'blood_type_id' => $bloodType->blood_type_id,
                'location_id' => $location->location_id,
                'date_registered' => now(),
            ]);

            DonorAuthentication::create([
                'donor_id' => $donor->donor_id,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_verified' => false,
                'created_at' => now(),
            ]);

            DB::commit();

            return redirect('/login')->with('success', 'Registration completed successfully. Please log in to continue.');
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'We could not complete your registration at the moment. Please try again.');
        }
    }
}
