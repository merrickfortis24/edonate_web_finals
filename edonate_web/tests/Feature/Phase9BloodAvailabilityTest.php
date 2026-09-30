<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\EnsureAdminRole;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase9BloodAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    public function test_completed_population_includes_scheduled_and_deferred_donors_and_keeps_aggregates_consistent(): void
    {
        $balintawak = $this->createLocation([
            'barangay_code' => '042101001',
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.952100,
            'longitude' => 121.123400,
        ]);
        $pangao = $this->createLocation([
            'barangay_name' => 'Pangao',
            'city' => 'Lipa City',
            'province' => 'Batangas',
        ]);

        $this->createQualifiedDonor($balintawak, 1);
        $this->createQualifiedDonor($balintawak, 2, [
            'donor_id' => 2,
            'first_name' => 'Scheduled',
        ]);
        DB::table('appointments')->insert([
            'donor_id' => 2,
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->createQualifiedDonor($pangao, 3, [
            'donor_id' => 3,
            'blood_type_id' => 3,
        ]);

        $this->createQualifiedDonor($balintawak, 1, [
            'donor_id' => 4,
            'verification_status' => 'pending',
        ]);
        $this->createQualifiedDonor($balintawak, 1, [
            'donor_id' => 5,
            'blood_type_status' => 'self_reported',
        ]);
        $this->createQualifiedDonor($balintawak, 1, [
            'donor_id' => 6,
        ], [
            'eligibility_id' => 6,
            'status' => 'eligible',
        ]);
        DB::table('eligibility_status')->insert([
            'eligibility_id' => 7,
            'donor_id' => 6,
            'status' => 'temporary_deferred',
        ]);
        DB::table('donation_records')->insert([
            ['donor_id' => 1, 'donation_status' => 'completed'],
            ['donor_id' => 2, 'donation_status' => 'completed'],
            ['donor_id' => 3, 'donation_status' => 'completed'],
            ['donor_id' => 4, 'donation_status' => 'completed'],
            ['donor_id' => 5, 'donation_status' => 'completed'],
            ['donor_id' => 6, 'donation_status' => 'completed'],
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data');

        $response->assertOk();
        $payload = $response->json();

        $this->assertSame(6, $payload['summary']['completed_donors']);
        $this->assertSame(6, $payload['summary']['verified_donors']);
        $this->assertSame(6, $payload['summary']['available_donors']);
        $this->assertSame(1, $payload['summary']['scheduled_donors']);
        $this->assertSame(4, $payload['blood_types']['A+']);
        $this->assertSame(1, $payload['blood_types']['O+']);
        $this->assertSame(1, $payload['blood_types']['O-']);
        $this->assertSame(1, $payload['blood_type_confidence']['unconfirmed']);
        $this->assertSame(1, $payload['blood_type_confidence_by_type']['A+']['unconfirmed']);
        $this->assertCount(2, $payload['barangays']);
        $this->assertCount(2, $payload['map_points']);
        $this->assertSame(6, array_sum(array_column($payload['barangays'], 'completed_donors')));
        $this->assertSame(6, array_sum(array_column($payload['map_points'], 'completed_donors')));
        $this->assertStringNotContainsString('Scheduled', $response->getContent());
        $this->assertStringNotContainsString('donor_id', $response->getContent());
        $this->assertStringNotContainsString('first_name', $response->getContent());
    }

    public function test_blood_type_filter_returns_aggregate_data_only(): void
    {
        $locationId = $this->createLocation([
            'barangay_code' => null,
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.952100,
            'longitude' => 121.123400,
        ]);
        $this->createQualifiedDonor($locationId, 1);
        $this->createQualifiedDonor($locationId, 2, ['donor_id' => 2]);
        DB::table('donation_records')->insert([
            ['donor_id' => 1, 'donation_status' => 'completed'],
            ['donor_id' => 2, 'donation_status' => 'completed'],
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data?blood_type=O%2B');

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(1, $payload['summary']['available_donors']);
        $this->assertSame(1, $payload['blood_types']['O+']);
        $this->assertSame(0, $payload['blood_types']['A+']);
    }

    public function test_completed_donor_is_included_during_waiting_period_without_duplicate_counts(): void
    {
        $locationId = $this->createLocation([
            'barangay_code' => '042101001',
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
            'latitude' => 13.9521,
            'longitude' => 121.1234,
        ]);
        $donorId = $this->createQualifiedDonor($locationId, 4, [
            'donor_id' => 8,
            'first_name' => 'Private',
            'last_name' => 'Donor',
        ], [
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::today()->addDays(56)->toDateString(),
        ]);

        // Multiple completed records must still contribute only one donor.
        DB::table('donation_records')->insert([
            ['donor_id' => $donorId, 'donation_status' => 'completed', 'verified_blood_type_id' => 4],
            ['donor_id' => $donorId, 'donation_status' => 'Completed', 'verified_blood_type_id' => 4],
        ]);
        DB::table('appointments')->insert([
            'donor_id' => $donorId,
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data?blood_type=AB%2B');

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $payload = $response->json();
        $this->assertSame(1, $payload['summary']['available_donors']);
        $this->assertSame(1, $payload['summary']['verified_donors']);
        $this->assertSame(1, $payload['summary']['scheduled_donors']);
        $this->assertSame(1, $payload['blood_types']['AB+']);
        $this->assertSame('Balintawak', $payload['barangays'][0]['barangay_name']);
        $this->assertSame(1, $payload['barangays'][0]['verified_donors']);
        $this->assertSame(1, $payload['barangays'][0]['blood_types']['AB+']);
        $this->assertSame(13.95759, $payload['map_points'][0]['latitude']);
        $this->assertSame(121.15555, $payload['map_points'][0]['longitude']);
        $this->assertSame('AB+', $payload['filters']['blood_type']);
        $this->assertStringNotContainsString('donor_id', $response->getContent());
        $this->assertStringNotContainsString('Private', $response->getContent());
        $this->assertStringNotContainsString('first_name', $response->getContent());
    }

    public function test_completed_donor_without_supported_blood_type_is_counted_as_unknown(): void
    {
        $locationId = $this->createLocation([
            'barangay_code' => '042101002',
            'barangay_name' => 'Salagao',
            'city' => 'Ivana',
            'province' => 'Batanes',
        ]);
        DB::table('blood_types')->insert(['blood_type_id' => 5, 'blood_type' => 'Unknown']);
        $donorId = $this->createQualifiedDonor($locationId, 5, ['donor_id' => 11]);
        DB::table('donation_records')->insert([
            'donor_id' => $donorId,
            'donation_status' => 'completed',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data');

        $response->assertOk()
            ->assertJsonPath('summary.completed_donors', 1)
            ->assertJsonPath('summary.unknown_type_donors', 1)
            ->assertJsonPath('blood_types.Unknown', 1)
            ->assertJsonPath('summary.barangays', 1)
            ->assertJsonPath('data_quality.mapped_completed_donors', 1)
            ->assertJsonPath('data_quality.unmapped_completed_donor_count', 0)
            ->assertJsonCount(1, 'barangays')
            ->assertJsonPath('map_points.0.barangay_name', 'Salagao')
            ->assertJsonPath('map_points.0.latitude', 20.38352)
            ->assertJsonPath('map_points.0.longitude', 121.93425)
            ->assertJsonCount(1, 'map_points');
    }

    public function test_completed_donor_without_a_profile_type_is_mapped_under_unknown(): void
    {
        $locationId = $this->createLocation([
            'barangay_code' => '042101008',
            'barangay_name' => 'Balintawak',
            'city' => 'City of Lipa',
            'province' => 'Batangas',
            'latitude' => null,
            'longitude' => null,
        ]);
        $donorId = $this->createQualifiedDonor($locationId, 1, [
            'donor_id' => 14,
            'blood_type_id' => null,
            'blood_type_status' => 'not_yet_determined',
        ]);
        DB::table('donation_records')->insert([
            'donor_id' => $donorId,
            'donation_status' => 'completed',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data?blood_type=Unknown');

        $response->assertOk()
            ->assertJsonPath('summary.completed_donors', 1)
            ->assertJsonPath('summary.unknown_type_donors', 1)
            ->assertJsonPath('blood_types.Unknown', 1)
            ->assertJsonPath('blood_type_confidence.unknown', 1)
            ->assertJsonPath('blood_type_confidence_by_type.Unknown.unknown', 1)
            ->assertJsonPath('barangays.0.completed_donors', 1)
            ->assertJsonPath('barangays.0.blood_types.Unknown', 1)
            ->assertJsonPath('map_points.0.completed_donors', 1)
            ->assertJsonPath('map_points.0.latitude', 13.95759)
            ->assertJsonPath('map_points.0.longitude', 121.15555)
            ->assertJsonPath('data_quality.unmapped_completed_donor_count', 0);
    }

    public function test_local_barangay_boundary_dataset_maps_completed_donors_without_writing_location_coordinates(): void
    {
        $locationId = $this->createLocation([
            'barangay_name' => 'Salagao',
            'city' => 'Ivana',
            'province' => 'Batanes',
            'latitude' => null,
            'longitude' => null,
        ]);
        $donorId = $this->createQualifiedDonor($locationId, 1, ['donor_id' => 21]);
        DB::table('donation_records')->insert([
            'donor_id' => $donorId,
            'donation_status' => 'completed',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->getJson('/admin/blood-availability/map-data')
            ->assertOk()
            ->assertJsonPath('summary.completed_donors', 1)
            ->assertJsonPath('summary.mapped_completed_donors', 1)
            ->assertJsonPath('data_quality.unmapped_completed_donor_count', 0)
            ->assertJsonPath('map_points.0.barangay_name', 'Salagao')
            ->assertJsonPath('map_points.0.completed_donors', 1)
            ->assertJsonPath('map_points.0.latitude', 20.38352)
            ->assertJsonPath('map_points.0.longitude', 121.93425);

        $this->assertDatabaseHas('locations', [
            'location_id' => $locationId,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    public function test_local_boundary_index_maps_the_currently_unmapped_barangay_variants_in_their_own_localities(): void
    {
        $cases = [
            ['Limbobung', 'Maluso', 'Basilan', 1, 31, 'Limbubong', 6.48673, 121.93312],
            ['Bayanan', 'City of Bacoor', 'Cavite', 2, 32, 'Bayanan', 14.42696, 120.96565],
            ['Bone-languyan', 'Sumisip', 'Basilan', 3, 33, 'Bohe-languyan', 6.45320, 122.07982],
            ['Salagao', 'Ivana', 'Batanes', 4, 34, 'Salagao', 20.38352, 121.93425],
        ];

        foreach ($cases as [$barangay, $city, $province, $bloodTypeId, $donorId]) {
            $locationId = $this->createLocation([
                'barangay_name' => $barangay,
                'city' => $city,
                'province' => $province,
                'latitude' => null,
                'longitude' => null,
            ]);
            $this->createQualifiedDonor($locationId, $bloodTypeId, ['donor_id' => $donorId]);
            DB::table('donation_records')->insert([
                'donor_id' => $donorId,
                'donation_status' => 'completed',
            ]);
        }

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data');
        $response->assertOk()
            ->assertJsonPath('summary.completed_donors', 4)
            ->assertJsonPath('summary.mapped_completed_donors', 4)
            ->assertJsonPath('data_quality.unmapped_completed_donor_count', 0)
            ->assertJsonCount(4, 'map_points');

        $points = collect($response->json('map_points'))->keyBy(fn (array $point): string => $point['city'].'|'.$point['barangay_name']);
        foreach ($cases as [$barangay, $city, $province, $bloodTypeId, $donorId, $boundaryName, $latitude, $longitude]) {
            $point = $points->get($city.'|'.$barangay);
            $this->assertNotNull($point, "Missing map point for {$barangay}, {$city}.");
            $this->assertSame(1, $point['completed_donors']);
            $this->assertSame($boundaryName, $point['boundary_match_name']);
            $this->assertEqualsWithDelta($latitude, $point['latitude'], 0.00001);
            $this->assertEqualsWithDelta($longitude, $point['longitude'], 0.00001);
        }

        $this->getJson('/admin/blood-availability/map-data?blood_type=AB%2B&barangay=Salagao')
            ->assertOk()
            ->assertJsonPath('summary.completed_donors', 1)
            ->assertJsonPath('summary.mapped_completed_donors', 1)
            ->assertJsonPath('map_points.0.blood_types.A+', 0)
            ->assertJsonPath('map_points.0.blood_types.AB+', 1)
            ->assertJsonPath('map_points.0.latitude', 20.38352);

        $this->assertStringNotContainsString('donor_id', $response->getContent());
        $this->assertStringNotContainsString('first_name', $response->getContent());
    }

    public function test_geocoded_centers_are_limited_to_completed_donor_locations_and_philippines(): void
    {
        $locationId = $this->createLocation([
            'barangay_name' => 'Salagao',
            'city' => 'Ivana',
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->createQualifiedDonor($locationId, 1, ['donor_id' => 22]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->postJson(route('admin.map.locations.coordinates', ['location' => $locationId]), [
            'latitude' => 20.73,
            'longitude' => 121.97,
            'query' => 'Salagao, Ivana, Philippines',
        ])->assertNotFound();
        $this->assertDatabaseHas('locations', ['location_id' => $locationId, 'latitude' => null, 'longitude' => null]);

        DB::table('donation_records')->insert(['donor_id' => 22, 'donation_status' => 'completed']);
        $query = 'Salagao, Ivana, Philippines';
        $this->cacheBarangayGeocoderResult($query, 20.73, 121.97, [
            'suburb' => 'Salagao', 'city' => 'Ivana', 'state' => 'Batanes', 'country' => 'Philippines',
        ]);
        $this->postJson(route('admin.map.locations.coordinates', ['location' => $locationId]), [
            'latitude' => 40.73,
            'longitude' => -73.97,
            'query' => $query,
        ])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);

        $this->postJson(route('admin.map.locations.coordinates', ['location' => $locationId]), [
            'latitude' => 20.73,
            'longitude' => 121.97,
            'query' => $query,
        ])->assertOk();
        $this->postJson(route('admin.map.locations.coordinates', ['location' => $locationId]), [
            'latitude' => 20.70,
            'longitude' => 121.90,
            'query' => $query,
        ])->assertStatus(409);
        $this->assertDatabaseHas('locations', [
            'location_id' => $locationId,
            'latitude' => 20.73,
            'longitude' => 121.97,
        ]);
    }

    public function test_geocoder_proxy_accepts_only_registered_barangay_and_city_and_hides_precise_address_fields(): void
    {
        $locationId = $this->createLocation([
            'barangay_name' => 'Sabang',
            'city' => 'City of Lipa',
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->createQualifiedDonor($locationId, 1, ['donor_id' => 23]);
        DB::table('donation_records')->insert(['donor_id' => 23, 'donation_status' => 'Completed']);

        $query = 'Sabang, City of Lipa, Philippines';
        Cache::forget('geocoding:nominatim:barangay:'.hash('sha256', mb_strtolower($query)));
        Cache::forget('geocoding:provider-request');
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([[
                'place_id' => 991,
                'licence' => 'Data © OpenStreetMap contributors',
                'osm_type' => 'relation',
                'osm_id' => 992,
                'boundingbox' => ['13.90', '13.95', '121.10', '121.15'],
                'lat' => '13.925',
                'lon' => '121.125',
                'display_name' => '12 Private Road, Sabang, Lipa, Batangas, Philippines',
                'address' => [
                    'house_number' => '12',
                    'road' => 'Private Road',
                    'suburb' => 'Sabang',
                    'city' => 'Lipa',
                    'state' => 'Batangas',
                    'country' => 'Philippines',
                    'country_code' => 'ph',
                ],
            ]]),
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $url = route('admin.map.locations.geocoder-search', ['location' => $locationId]);
        $response = $this->getJson($url.'?q='.urlencode($query));
        $response->assertOk()
            ->assertJsonPath('0.lat', '13.925')
            ->assertJsonPath('0.lon', '121.125')
            ->assertJsonPath('0.address.suburb', 'Sabang')
            ->assertJsonMissingPath('0.address.road')
            ->assertJsonMissingPath('0.address.house_number');
        $this->assertStringNotContainsString('Private Road', $response->getContent());

        $this->getJson($url.'?q='.urlencode($query))->assertOk();
        $this->getJson($url.'?q='.urlencode('Pangao, City of Lipa, Philippines'))->assertUnprocessable();
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'nominatim.openstreetmap.org/search')
            && $request['q'] === $query
            && $request['format'] === 'jsonv2'
            && $request['countrycodes'] === 'ph'
            && $request->hasHeader('User-Agent'));
    }

    public function test_coordinate_save_rejects_values_not_returned_for_the_location_search(): void
    {
        $locationId = $this->createLocation([
            'barangay_name' => 'Sabang',
            'city' => 'City of Lipa',
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->createQualifiedDonor($locationId, 1, ['donor_id' => 24]);
        DB::table('donation_records')->insert(['donor_id' => 24, 'donation_status' => 'completed']);
        $query = 'Sabang, City of Lipa, Philippines';
        $this->cacheBarangayGeocoderResult($query, 13.925, 121.125, [
            'suburb' => 'Sabang', 'city' => 'Lipa', 'state' => 'Batangas', 'country' => 'Philippines',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $this->postJson(route('admin.map.locations.coordinates', ['location' => $locationId]), [
            'latitude' => 13.925,
            'longitude' => 121.126,
            'query' => $query,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('locations', ['location_id' => $locationId, 'latitude' => null, 'longitude' => null]);
    }

    public function test_completed_balintawak_donor_maps_once_during_waiting_period_and_matches_all_endpoints(): void
    {
        $locationId = $this->createLocation([
            'barangay_code' => '042101008',
            'barangay_name' => 'Balintawak',
            'city' => 'City of Lipa',
            'province' => 'Batangas',
            'latitude' => null,
            'longitude' => null,
        ]);
        $donorId = $this->createQualifiedDonor($locationId, 4, ['donor_id' => 12], [
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::today()->addDays(56)->toDateString(),
        ]);
        $unconfirmedDonorId = $this->createQualifiedDonor($locationId, 4, [
            'donor_id' => 13,
            'blood_type_status' => 'self_reported',
            'verification_status' => 'pending',
        ]);
        DB::table('donation_records')->insert([
            ['donor_id' => $donorId, 'donation_status' => 'completed', 'verified_blood_type_id' => 4],
            ['donor_id' => $donorId, 'donation_status' => 'completed', 'verified_blood_type_id' => 4],
            ['donor_id' => $unconfirmedDonorId, 'donation_status' => 'completed', 'verified_blood_type_id' => null],
        ]);
        DB::table('appointments')->insert([
            'donor_id' => $donorId,
            'appointment_date' => Carbon::tomorrow()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data?blood_type=AB%2B&barangay=Balintawak');

        $response->assertOk()
            ->assertJsonPath('summary.completed_donors', 2)
            ->assertJsonPath('summary.mapped_completed_donors', 2)
            ->assertJsonPath('summary.verified_donors', 2)
            ->assertJsonPath('summary.available_donors', 2)
            ->assertJsonPath('summary.unconfirmed_donors', 1)
            ->assertJsonPath('summary.barangays', 1)
            ->assertJsonPath('summary.scheduled_donors', 1)
            ->assertJsonPath('blood_types.AB+', 2)
            ->assertJsonPath('blood_type_confidence_by_type.AB+.confirmed', 1)
            ->assertJsonPath('blood_type_confidence_by_type.AB+.unconfirmed', 1)
            ->assertJsonPath('barangays.0.barangay_name', 'Balintawak')
            ->assertJsonPath('barangays.0.completed_donors', 2)
            ->assertJsonPath('barangays.0.verified_donors', 2)
            ->assertJsonPath('barangays.0.available_donors', 2)
            ->assertJsonPath('barangays.0.blood_types.AB+', 2)
            ->assertJsonPath('barangays.0.blood_type_confidence_by_type.AB+.unconfirmed', 1)
            ->assertJsonPath('map_points.0.barangay_name', 'Balintawak')
            ->assertJsonPath('map_points.0.completed_donors', 2)
            ->assertJsonPath('map_points.0.verified_donors', 2)
            ->assertJsonPath('map_points.0.blood_type_confidence_by_type.AB+.unconfirmed', 1)
            ->assertJsonPath('map_points.0.latitude', 13.95759)
            ->assertJsonPath('map_points.0.longitude', 121.15555)
            ->assertJsonPath('data_quality.mapped_verified_donors', 2)
            ->assertJsonPath('data_quality.verified_donors_missing_coordinates', 0);

        $this->getJson('/admin/map/barangays?blood_type=AB%2B&barangay=Balintawak')
            ->assertOk()
            ->assertJsonPath('0.completed_donors', 2)
            ->assertJsonPath('0.verified_donors', 2)
            ->assertJsonPath('0.blood_types.AB+', 2)
            ->assertJsonPath('0.blood_type_confidence_by_type.AB+.unconfirmed', 1);
        $this->getJson('/admin/map/donors?blood_type=AB%2B&barangay=Balintawak')
            ->assertOk()
            ->assertJsonPath('0.verified_donors', 2)
            ->assertJsonPath('0.completed_donors', 2)
            ->assertJsonPath('0.latitude', 13.95759)
            ->assertJsonPath('0.longitude', 121.15555);
        $this->getJson('/admin/map/summary?blood_type=AB%2B&barangay=Balintawak')
            ->assertOk()
            ->assertJsonPath('completed_donors', 2)
            ->assertJsonPath('total_donors', 2)
            ->assertJsonPath('verified_donors', 2)
            ->assertJsonPath('mapped_locations', 1)
            ->assertJsonPath('unmapped_donors', 0);
    }

    public function test_pangao_uses_the_local_barangay_boundary_center_when_location_coordinates_are_missing(): void
    {
        $pangao = $this->createLocation([
            'barangay_name' => 'Pangao',
            'city' => 'City of Lipa',
            'province' => 'Batangas',
            'latitude' => null,
            'longitude' => null,
        ]);
        $otherPangao = $this->createLocation([
            'barangay_name' => 'Pangao',
            'city' => 'San Juan',
            'province' => 'Batangas',
            'latitude' => null,
            'longitude' => null,
        ]);

        $firstDonorId = $this->createQualifiedDonor($pangao, 4, ['donor_id' => 9], [
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::today()->addDays(56)->toDateString(),
        ]);
        $secondDonorId = $this->createQualifiedDonor($otherPangao, 4, ['donor_id' => 10], [
            'status' => 'temporary_deferred',
            'next_eligible_date' => Carbon::today()->addDays(56)->toDateString(),
        ]);
        DB::table('donation_records')->insert([
            ['donor_id' => $firstDonorId, 'donation_status' => 'completed'],
            ['donor_id' => $secondDonorId, 'donation_status' => 'completed'],
        ]);

        $this->withoutMiddleware([EnsureAdminAuthenticated::class, EnsureAdminRole::class]);
        $response = $this->getJson('/admin/blood-availability/map-data?blood_type=AB%2B');

        $response->assertOk();
        $payload = $response->json();
        $this->assertSame(2, $payload['summary']['available_donors']);
        $this->assertSame(1, $payload['data_quality']['mapped_available_donors']);
        $this->assertSame(1, $payload['data_quality']['available_donors_missing_coordinates']);
        $this->assertCount(1, $payload['map_points']);
        $this->assertSame('Pangao', $payload['map_points'][0]['barangay_name']);
        $this->assertSame('City of Lipa', $payload['map_points'][0]['city']);
        $this->assertSame(13.91889, $payload['map_points'][0]['latitude']);
        $this->assertSame(121.12465, $payload['map_points'][0]['longitude']);
        $this->assertStringNotContainsString('donor_id', $response->getContent());
        $this->assertStringNotContainsString('first_name', $response->getContent());
    }

    public function test_unauthenticated_user_cannot_access_map_endpoint(): void
    {
        $this->get('/admin/blood-availability/map-data')->assertRedirect(route('admin.login'));
    }

    private function buildSchema(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['donation_records', 'appointments', 'eligibility_status', 'donors', 'locations', 'blood_types'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('blood_types', function (Blueprint $table): void {
            $table->increments('blood_type_id');
            $table->string('blood_type', 5);
        });
        DB::table('blood_types')->insert([
            ['blood_type_id' => 1, 'blood_type' => 'A+'],
            ['blood_type_id' => 2, 'blood_type' => 'O+'],
            ['blood_type_id' => 3, 'blood_type' => 'O-'],
            ['blood_type_id' => 4, 'blood_type' => 'AB+'],
        ]);

        Schema::create('locations', function (Blueprint $table): void {
            $table->increments('location_id');
            $table->string('barangay_code')->nullable();
            $table->string('barangay_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
        });

        Schema::create('donors', function (Blueprint $table): void {
            $table->increments('donor_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('blood_type_id')->nullable();
            $table->string('blood_type_status')->nullable();
            $table->integer('location_id')->nullable();
            $table->string('verification_status')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('eligibility_status', function (Blueprint $table): void {
            $table->increments('eligibility_id');
            $table->integer('donor_id')->nullable();
            $table->date('next_eligible_date')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->increments('appointment_id');
            $table->integer('donor_id')->nullable();
            $table->date('appointment_date')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('donation_records', function (Blueprint $table): void {
            $table->increments('donation_id');
            $table->integer('donor_id')->nullable();
            $table->integer('appointment_id')->nullable();
            $table->integer('verified_blood_type_id')->nullable();
            $table->string('donation_status')->nullable();
        });
    }

    private function createLocation(array $overrides = []): int
    {
        return (int) DB::table('locations')->insertGetId(array_merge([
            'barangay_name' => 'Balintawak',
            'city' => 'Lipa City',
            'province' => 'Batangas',
        ], $overrides), 'location_id');
    }

    private function cacheBarangayGeocoderResult(string $query, float $latitude, float $longitude, array $address): void
    {
        Cache::put('geocoding:nominatim:barangay:'.hash('sha256', mb_strtolower($query)), [[
            'lat' => (string) $latitude,
            'lon' => (string) $longitude,
            'display_name' => implode(', ', array_values($address)),
            'address' => $address,
        ]], now()->addDays(30));
    }

    private function createQualifiedDonor(int $locationId, int $bloodTypeId, array $donor = [], array $eligibility = []): int
    {
        $donorId = (int) DB::table('donors')->insertGetId(array_merge([
            'first_name' => 'Private',
            'last_name' => 'Donor',
            'blood_type_id' => $bloodTypeId,
            'blood_type_status' => 'verified',
            'location_id' => $locationId,
            'verification_status' => 'verified',
            'is_active' => true,
        ], $donor), 'donor_id');

        DB::table('eligibility_status')->insert(array_merge([
            'donor_id' => $donorId,
            'status' => 'eligible',
            'next_eligible_date' => null,
        ], $eligibility, ['donor_id' => $donorId]));

        return $donorId;
    }
}
