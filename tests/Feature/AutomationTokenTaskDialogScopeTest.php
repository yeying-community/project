<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\AutomationToken;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUser;
use App\Models\User;
use App\Models\WebSocketDialog;
use App\Services\AutomationTokenService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class AutomationTokenTaskDialogScopeTest extends TestCase
{
    use DatabaseTransactions;

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

    private function makeTask(User $owner): array
    {
        $project = Project::createInstance([
            'name' => 'Token task scope test',
            'desc' => '',
            'userid' => $owner->userid,
            'personal' => 0,
        ]);
        $project->save();
        ProjectUser::updateInsert([
            'project_id' => $project->id,
            'userid' => $owner->userid,
        ], ['owner' => 1]);

        $dialog = WebSocketDialog::createGroup(
            'Token task scope dialog',
            [$owner->userid],
            'task',
            $owner->userid
        );
        $task = ProjectTask::createInstance([
            'project_id' => $project->id,
            'parent_id' => 0,
            'name' => 'Token task scope test',
            'dialog_id' => $dialog->id,
            'userid' => $owner->userid,
        ]);
        $task->save();

        return [$project, $task, $dialog];
    }

    private function makeToken(User $user, array $projectIds): AutomationToken
    {
        $token = AutomationToken::createInstance([
            'userid' => $user->userid,
            'access_key' => 'yyak_task_' . bin2hex(random_bytes(6)),
            'secret_hash' => hash('sha256', 'test-secret'),
            'name' => 'task-scope-test',
            'scopes' => [],
            'project_ids' => $projectIds,
            'expires_at' => Carbon::now()->addDay(),
            'status' => AutomationToken::STATUS_ACTIVE,
        ]);
        $token->save();
        return $token->fresh();
    }

    public function test_task_dialog_file_upload_uses_project_scope_without_file_cabinet_scope(): void
    {
        $user = $this->makeUser('token-task-allow@test.local');
        [$project, $task, $dialog] = $this->makeTask($user);
        $token = $this->makeToken($user, [$project->id]);
        $request = Request::create('/api/dialog/msg/sendfile', 'POST', ['dialog_id' => $dialog->id]);

        AutomationTokenService::authorizeStandardRequest($token, $request);

        $this->assertTrue(true);
    }

    public function test_task_dialog_file_upload_rejects_unbound_project(): void
    {
        $user = $this->makeUser('token-task-deny@test.local');
        [$project, $task, $dialog] = $this->makeTask($user);
        $token = $this->makeToken($user, [$project->id + 100000]);
        $request = Request::create('/api/dialog/msg/sendfile', 'POST', ['dialog_id' => $dialog->id]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('访问令牌无权调用此接口');

        AutomationTokenService::authorizeStandardRequest($token, $request);
    }

    public function test_file_upload_to_non_task_dialog_is_not_opened_by_task_scope(): void
    {
        $user = $this->makeUser('token-user-dialog-deny@test.local');
        [$project] = $this->makeTask($user);
        $dialog = WebSocketDialog::createGroup(
            'Token ordinary dialog',
            [$user->userid],
            'user',
            $user->userid
        );
        $token = $this->makeToken($user, [$project->id]);
        $request = Request::create('/api/dialog/msg/sendfile', 'POST', ['dialog_id' => $dialog->id]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('访问令牌无权调用此接口');

        AutomationTokenService::authorizeStandardRequest($token, $request);
    }
}
