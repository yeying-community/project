<?php

namespace Tests\Feature;

use App\Models\AutomationToken;
use App\Models\Project;
use App\Models\ProjectColumn;
use App\Models\ProjectTaskUser;
use App\Models\ProjectUser;
use App\Models\User;
use App\Services\AutomationTokenService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AutomationTokenTaskOwnerDefaultTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'yysk_task_owner_default_test';

    private function makeUser(string $email): User
    {
        $user = User::createInstance([
            'email' => $email,
            'userimg' => '',
            'nickname' => 'TestUser_' . substr(md5($email), 0, 6),
            'profession' => '',
            'password' => md5('123456'),
        ]);
        $user->save();
        return $user;
    }

    private function makeProject(User $tokenOwner): array
    {
        $project = Project::createInstance([
            'name' => 'Token owner default ' . bin2hex(random_bytes(4)),
            'desc' => '',
            'userid' => $tokenOwner->userid,
            'personal' => 0,
        ]);
        $project->save();
        ProjectUser::updateInsert([
            'project_id' => $project->id,
            'userid' => $tokenOwner->userid,
        ], ['owner' => ProjectUser::OWNER_PRIMARY]);
        $column = ProjectColumn::createInstance([
            'project_id' => $project->id,
            'name' => 'Default',
            'sort' => 1,
        ]);
        $column->save();
        return [$project, $column];
    }

    private function makeToken(User $user, int $projectId): AutomationToken
    {
        $token = AutomationToken::createInstance([
            'userid' => $user->userid,
            'access_key' => 'yyak_owner_' . bin2hex(random_bytes(6)),
            'secret_hash' => hash('sha256', self::SECRET),
            'name' => 'task-owner-default-test',
            'scopes' => [],
            'project_ids' => [$projectId],
            'expires_at' => Carbon::now()->addDay(),
            'status' => AutomationToken::STATUS_ACTIVE,
        ]);
        $token->save();
        return $token->fresh();
    }

    private function signedTaskCreate(AutomationToken $token, array $payload): TestResponse
    {
        $path = '/api/project/task/add';
        $payload = array_merge([
            'content' => '',
            'times' => [],
            'add_assist' => 0,
            'assist' => [],
            'subtasks' => [],
            'p_level' => 0,
            'p_name' => '',
            'p_color' => '',
            'top' => 0,
            'visibility' => 1,
            'visibility_appointor' => [],
        ], $payload);
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = Carbon::now()->toIso8601String();
        $nonce = bin2hex(random_bytes(16));
        $canonical = implode("\n", [
            'POST',
            $path,
            '',
            hash('sha256', $content),
            $timestamp,
            $nonce,
        ]);
        $signature = hash_hmac('sha256', $canonical, $token->secret_hash);

        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_YY_AK' => $token->access_key,
            'HTTP_X_YY_TIMESTAMP' => $timestamp,
            'HTTP_X_YY_NONCE' => $nonce,
            'HTTP_X_YY_SIGNATURE' => $signature,
        ], $content);
    }

    public function test_ak_sk_task_without_owner_defaults_to_token_owner(): void
    {
        $tokenOwner = $this->makeUser('token-owner-default@test.local');
        [$project, $column] = $this->makeProject($tokenOwner);
        $token = $this->makeToken($tokenOwner, $project->id);

        $response = $this->signedTaskCreate($token, [
            'project_id' => $project->id,
            'column_id' => $column->id,
            'name' => 'Default to token owner',
        ]);

        $response->assertOk()->assertJsonPath('ret', 1);
        $taskId = (int) $response->json('data.id');
        $this->assertTrue(ProjectTaskUser::whereTaskId($taskId)
            ->whereUserid($tokenOwner->userid)
            ->whereOwner(1)
            ->exists());
    }

    public function test_explicit_owner_is_not_overridden_by_token_owner(): void
    {
        $request = Request::create('/api/project/task/add', 'POST');
        $token = new AutomationToken();
        $token->userid = 123;
        $request->attributes->set('automation_token', $token);

        $data = AutomationTokenService::applyTaskCreateDefaults($request, [
            'project_id' => 1,
            'name' => 'Explicit owner',
            'owner' => 456,
        ]);

        $this->assertSame(456, $data['owner']);
    }

    public function test_explicit_empty_owner_keeps_task_unassigned(): void
    {
        $tokenOwner = $this->makeUser('token-owner-empty@test.local');
        [$project, $column] = $this->makeProject($tokenOwner);
        $token = $this->makeToken($tokenOwner, $project->id);

        $response = $this->signedTaskCreate($token, [
            'project_id' => $project->id,
            'column_id' => $column->id,
            'name' => 'Explicitly unassigned',
            'owner' => [],
        ]);

        $response->assertOk()->assertJsonPath('ret', 1);
        $taskId = (int) $response->json('data.id');
        $this->assertFalse(ProjectTaskUser::whereTaskId($taskId)->whereOwner(1)->exists());
    }

    public function test_non_ak_sk_request_does_not_receive_task_owner_default(): void
    {
        $request = Request::create('/api/project/task/add', 'POST');
        $data = AutomationTokenService::applyTaskCreateDefaults($request, [
            'project_id' => 1,
            'name' => 'Web request behavior',
        ]);

        $this->assertArrayNotHasKey('owner', $data);
    }
}
