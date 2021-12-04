<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\User;
use App\Jobs\CreateAuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CreateAuditLogTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_logs_an_audit_log(): void
    {
        $regis = User::factory()->create();

        $request = [
            'account_id' => $regis->account_id,
            'author_id' => $regis->id,
            'author_name' => $regis->name,
            'action_name' => 'contact_created',
            'objects' => json_encode([
                'contact_name' => $regis->name,
            ]),
        ];

        CreateAuditLog::dispatch($request);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $regis->account_id,
            'author_id' => $regis->id,
            'author_name' => $regis->name,
            'action_name' => 'contact_created',
            'objects' => json_encode([
                'contact_name' => $regis->name,
            ]),
        ]);
    }
}
