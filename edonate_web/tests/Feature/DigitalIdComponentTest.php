<?php

namespace Tests\Feature;

use Tests\TestCase;

class DigitalIdComponentTest extends TestCase
{
    public function test_digital_id_component_renders_donor_details_without_a_fake_verification_qr(): void
    {
        $donor = (object) [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'donor_id' => 'DN-000123',
            'blood_type' => 'O+',
            'address' => 'Barangay Tambo, Lipa City, Batangas',
            'contact_number' => '09171234567',
            'last_donation_date' => '2026-01-15',
            'next_eligible_date' => '2026-03-12',
            'photo_url' => null,
        ];

        $html = view('components.digital-id', compact('donor'))->render();

        $this->assertStringContainsString('eDonate Digital ID', $html);
        $this->assertStringContainsString('City Health Office, Lipa City', $html);
        $this->assertStringContainsString('Juan Dela Cruz', $html);
        $this->assertStringContainsString('O+', $html);
        $this->assertStringContainsString('DN-000123', $html);
        $this->assertStringContainsString('Jan 15, 2026', $html);
        $this->assertStringContainsString('Mar 12, 2026', $html);
        $this->assertStringContainsString('text-emerald-600', $html);
        $this->assertStringContainsString('Verify this ID with an authorized eDonate administrator.', $html);
        $this->assertStringNotContainsString('Scan to verify donor', $html);
    }

    public function test_digital_id_component_marks_a_future_eligible_date_as_waiting(): void
    {
        $donor = (object) [
            'name' => 'Maria Santos',
            'donor_id' => 45,
            'blood_type' => 'A+',
            'next_eligible_date' => now()->addWeek()->toDateString(),
        ];

        $html = view('components.digital-id', compact('donor'))->render();

        $this->assertStringContainsString('Maria Santos', $html);
        $this->assertStringContainsString('text-amber-600', $html);
        $this->assertStringContainsString('Address not provided', $html);
    }
}
