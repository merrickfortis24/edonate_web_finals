<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonorRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:150', 'unique:donor_authentication,email'],
            'phone' => ['required', 'string', 'regex:/^(\\+63|0)\\d{10}$/'],
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:Male,Female,Other,Prefer not to say'],
            'blood_type' => ['required', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'street_address' => ['required', 'string', 'max:150'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/', 'confirmed'],
            'terms' => ['accepted'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered. Please use another email or log in.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be in 11-digit local format (e.g., 09171234567) or +63 format.',
            'birthdate.required' => 'Date of birth is required.',
            'birthdate.date' => 'Please provide a valid birthdate.',
            'birthdate.before_or_equal' => 'Birthdate cannot be in the future.',
            'gender.required' => 'Please select a gender.',
            'gender.in' => 'Selected gender is invalid.',
            'blood_type.required' => 'Please select a blood type.',
            'blood_type.in' => 'Selected blood type is invalid.',
            'street_address.required' => 'Street address is required.',
            'barangay.required' => 'Barangay is required.',
            'city.required' => 'Municipality/City is required.',
            'province.required' => 'Province is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include at least one uppercase letter and one number.',
            'password.confirmed' => 'Password confirmation does not match.',
            'terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'city' => 'municipality/city',
            'terms' => 'terms agreement',
        ];
    }
}
