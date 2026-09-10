<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CoursePic;
use App\Models\Lecturer;
use App\Models\Schedule;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicReminderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_api_ping(): void
    {
        $response = $this->getJson('/api/ping');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_get_semesters_list(): void
    {
        $response = $this->getJson('/api/semesters');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [['id', 'name', 'is_active']]]);
    }

    public function test_get_lecturers_list(): void
    {
        $response = $this->getJson('/api/lecturers');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_get_courses_list(): void
    {
        $response = $this->getJson('/api/courses');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals(9, count($response->json('data')));
    }

    public function test_get_schedules_list(): void
    {
        $response = $this->getJson('/api/schedules?day=monday');
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_trigger_morning_reminder_dry_run(): void
    {
        $response = $this->postJson('/api/reminders/trigger-morning', [
            'dry_run' => true,
            'day' => 'monday',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 2);
    }

    public function test_trigger_d_minus_one_reminder_dry_run(): void
    {
        $response = $this->postJson('/api/reminders/trigger-d-minus-one', [
            'dry_run' => true,
            'day' => 'tuesday',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.status', 'dry_run');
    }

    public function test_swagger_documentation_endpoint(): void
    {
        $response = $this->get('/api/documentation');
        $response->assertStatus(200);
    }
}
