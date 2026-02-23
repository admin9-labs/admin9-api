<?php

namespace Tests\Feature\System;

use App\Models\AuditLog;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    private function createLog(string $logName, string $description, array $extra = []): AuditLog
    {
        return AuditLog::forceCreate(array_merge([
            'log_name' => $logName,
            'description' => $description,
        ], $extra));
    }

    public function test_can_list_audit_logs(): void
    {
        $this->actingAsUser(['auditLogs.read']);

        $this->createLog('user', 'created');
        $this->createLog('role', 'updated');

        $response = $this->getJson('/api/system/audit-logs');

        $this->assertBusinessSuccess($response)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination', 'page', 'page_size', 'total'],
            ]);
    }

    public function test_can_show_audit_log(): void
    {
        $this->actingAsUser(['auditLogs.read']);

        $log = $this->createLog('user', 'created', [
            'properties' => json_encode(['name' => 'test']),
        ]);

        $response = $this->getJson("/api/system/audit-logs/{$log->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.log_name', 'user');
    }

    public function test_can_filter_by_log_name(): void
    {
        $this->actingAsUser(['auditLogs.read']);
        AuditLog::query()->delete();

        $this->createLog('user', 'created');
        $this->createLog('role', 'updated');

        $response = $this->getJson('/api/system/audit-logs?log_name[]=user');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('user', $data[0]['log_name']);
    }

    public function test_log_includes_causer_relation(): void
    {
        $user = $this->actingAsUser(['auditLogs.read']);

        $log = $this->createLog('user', 'created', [
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->id,
        ]);

        $response = $this->getJson("/api/system/audit-logs/{$log->id}");

        $this->assertBusinessSuccess($response)
            ->assertJsonPath('data.causer.id', $user->id);
    }

    public function test_user_without_permission_cannot_list_logs(): void
    {
        $this->actingAsUser([]);

        $response = $this->getJson('/api/system/audit-logs');

        $this->assertBusinessError($response, 403);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $response = $this->getJson('/api/system/audit-logs');

        $this->assertBusinessError($response, -1);
    }

    public function test_can_filter_by_event(): void
    {
        $this->actingAsUser(['auditLogs.read']);
        AuditLog::query()->delete();

        $this->createLog('user', 'created', ['event' => 'created']);
        $this->createLog('user', 'updated', ['event' => 'updated']);

        $response = $this->getJson('/api/system/audit-logs?event=created');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('created', $data[0]['event']);
    }

    public function test_can_filter_by_date_range(): void
    {
        $this->actingAsUser(['auditLogs.read']);
        AuditLog::query()->delete();

        $this->createLog('user', 'old', ['created_at' => '2025-01-01 00:00:00']);
        $this->createLog('user', 'recent', ['created_at' => '2026-02-20 00:00:00']);

        $response = $this->getJson('/api/system/audit-logs?date_from=2026-01-01&date_to=2026-12-31');

        $this->assertBusinessSuccess($response);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('recent', $data[0]['description']);
    }

    public function test_audit_log_persisted_on_role_event(): void
    {
        $this->actingAsUser(['roles.create']);

        $this->postJson('/api/system/roles', [
            'name' => 'test-audit-role',
            'menu_ids' => [],
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'role',
            'event' => 'created_with_menus',
        ]);
    }
}
