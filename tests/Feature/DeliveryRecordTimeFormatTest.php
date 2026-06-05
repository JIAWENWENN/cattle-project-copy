<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DeliveryRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class DeliveryRecordTimeFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_store_delivery_record_with_12_hour_time_format()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $data = [
            'date' => '2026-01-29',
            'time' => '06:54 PM',
            'user_id' => $user->id,
            'vehicle' => 'JMM 5321',
            'origin' => 'SKLC Integrated',
            'destination' => 'Butchery SKLC',
            'cargo_type' => 'Cattle',
            'cargo_weight' => '888kg',
            'status' => 'pending',
            'delivery_notes' => 'nil',
            'customer' => 'Butchery SKLC',
        ];

        $response = $this->post(route('driver.delivery-history.store'), $data);

        if ($response->status() !== 302) {
            dump($response->getContent());
        }

        $response->assertStatus(302);
        
        $expectedTime = \Illuminate\Support\Facades\DB::getDriverName() === 'sqlite' ? '18:54' : '18:54:00';
        $this->assertDatabaseHas('delivery_records', [
            'time' => $expectedTime,
        ]);
    }

    public function test_it_can_update_delivery_record_with_12_hour_time_format()
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->actingAs($user);

        $delivery = DeliveryRecord::create([
            'delivery_number' => 'DEL-001',
            'date' => '2026-01-29',
            'time' => '10:00:00',
            'user_id' => $user->id,
            'vehicle' => 'JMM 5321',
            'origin' => 'SKLC Integrated',
            'destination' => 'Butchery SKLC',
            'cargo_type' => 'Cattle',
            'cargo_weight' => '888kg',
            'status' => 'pending',
            'delivery_notes' => 'nil',
            'customer' => 'Butchery SKLC',
        ]);

        $updateData = [
            'date' => '2026-01-29',
            'time' => '07:15 PM',
            'user_id' => $user->id,
            'vehicle' => 'JMM 5321',
            'origin' => 'SKLC Integrated',
            'destination' => 'Butchery SKLC',
            'cargo_type' => 'Cattle',
            'cargo_weight' => '888kg',
            'status' => 'pending',
            'delivery_notes' => 'nil',
            'customer' => 'Butchery SKLC',
        ];

        $response = $this->put(route('driver.delivery-history.update', $delivery), $updateData);

        if ($response->status() !== 302) {
            dump($response->getContent());
        }

        $response->assertStatus(302);
        
        $expectedTime = \Illuminate\Support\Facades\DB::getDriverName() === 'sqlite' ? '19:15' : '19:15:00';
        $this->assertDatabaseHas('delivery_records', [
            'id' => $delivery->id,
            'time' => $expectedTime,
        ]);
    }
}
