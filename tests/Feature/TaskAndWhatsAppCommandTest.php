<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Semester;
use App\Models\Task;
use App\Services\FonnteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use Tests\TestCase;

class TaskAndWhatsAppCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_read_update_delete_and_toggle_task(): void
    {
        $course = Course::first();

        // 1. Create Task
        $createResponse = $this->postJson('/api/tasks', [
            'course_id' => $course->id,
            'title' => 'Tugas 1 Praktikum',
            'description' => 'Kerjakan modul 1 sampai 3',
            'due_date' => now()->addDays(3)->toDateTimeString(),
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Tugas 1 Praktikum');

        $taskId = $createResponse->json('data.id');

        // 2. Read Task
        $getResponse = $this->getJson("/api/tasks/{$taskId}");
        $getResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Tugas 1 Praktikum');

        // 3. Toggle Complete
        $toggleResponse = $this->patchJson("/api/tasks/{$taskId}/toggle-complete");
        $toggleResponse->assertStatus(200)
            ->assertJsonPath('data.is_completed', true);

        // 4. Update Task
        $updateResponse = $this->putJson("/api/tasks/{$taskId}", [
            'title' => 'Tugas 1 Praktikum (Revisi)',
        ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Tugas 1 Praktikum (Revisi)');

        // 5. Delete Task
        $deleteResponse = $this->deleteJson("/api/tasks/{$taskId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('tasks', ['id' => $taskId]);
    }

    public function test_whatsapp_webhook_tugas_command(): void
    {
        $course = Course::first();
        Task::create([
            'course_id' => $course->id,
            'title' => 'PR Matematika Diskrit Bab 2',
            'description' => 'Kumpulkan sebelum jam 12 malam',
            'due_date' => now()->addDays(2),
            'is_completed' => false,
        ]);

        $this->mock(FonnteService::class, function (MockInterface $mock) use ($course) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->withArgs(function ($target, $message) use ($course) {
                    return $target === '120363123456@g.us'
                        && str_contains($message, 'Daftar tugas kelas 1E-SIB')
                        && str_contains($message, 'PR Matematika Diskrit Bab 2');
                })
                ->andReturn([
                    'success' => true,
                    'status' => 'sent',
                    'message' => 'Sent',
                ]);
        });

        $response = $this->postJson('/api/webhook/fonnte', [
            'sender' => '120363123456@g.us',
            'message' => '!tugas',
            'name' => 'Ahmad',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.handled', true)
            ->assertJsonPath('data.command', '!tugas');
    }

    public function test_whatsapp_webhook_jadwal_and_help_command(): void
    {
        $this->mock(FonnteService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')
                ->twice()
                ->andReturn([
                    'success' => true,
                    'status' => 'sent',
                    'message' => 'Sent',
                ]);
        });

        $jadwalResponse = $this->postJson('/api/webhook/fonnte', [
            'sender' => '120363123456@g.us',
            'message' => '!jadwal',
            'name' => 'Ahmad',
        ]);

        $jadwalResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.handled', true);

        $helpResponse = $this->postJson('/api/webhook/fonnte', [
            'sender' => '120363123456@g.us',
            'message' => '!help',
            'name' => 'Ahmad',
        ]);

        $helpResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.handled', true);
    }

    public function test_whatsapp_group_restriction(): void
    {
        Config::set('services.fonnte.allowed_group_id', '120363999999@g.us');

        $response = $this->postJson('/api/webhook/fonnte', [
            'sender' => '120363111111@g.us', // Wrong group
            'message' => '!tugas',
            'name' => 'Stranger',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.handled', false)
            ->assertJsonPath('data.reason', 'Sender is not the authorized group');
    }

    public function test_daily_rate_limiting(): void
    {
        Config::set('services.fonnte.daily_command_limit', 2);
        Cache::flush();

        $this->mock(FonnteService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')
                ->times(3)
                ->andReturn([
                    'success' => true,
                    'status' => 'sent',
                    'message' => 'Sent',
                ]);
        });

        $group = '120363limit@g.us';

        // 1st request - allowed
        $r1 = $this->postJson('/api/webhook/fonnte', ['sender' => $group, 'message' => '!help']);
        $r1->assertJsonPath('data.handled', true);

        // 2nd request - allowed
        $r2 = $this->postJson('/api/webhook/fonnte', ['sender' => $group, 'message' => '!help']);
        $r2->assertJsonPath('data.handled', true);

        // 3rd request - rate limit reached
        $r3 = $this->postJson('/api/webhook/fonnte', ['sender' => $group, 'message' => '!help']);
        $r3->assertJsonPath('data.handled', true)
            ->assertJsonPath('data.status', 'rate_limited');
    }
}
