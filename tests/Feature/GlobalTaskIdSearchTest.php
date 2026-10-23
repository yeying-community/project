<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SearchController;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUser;
use App\Models\User;
use App\Services\RequestContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use Tests\TestCase;

class GlobalTaskIdSearchTest extends TestCase
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

    private function makeProject(User $owner, array $members = []): Project
    {
        $project = Project::createInstance([
            'name' => 'Task search ' . substr(md5(uniqid('', true)), 0, 8),
            'desc' => '',
            'userid' => $owner->userid,
            'personal' => 0,
        ]);
        $project->save();

        foreach (array_unique(array_merge([$owner->userid], $members)) as $userid) {
            ProjectUser::createInstance([
                'project_id' => $project->id,
                'userid' => $userid,
                'owner' => $userid === $owner->userid ? ProjectUser::OWNER_PRIMARY : ProjectUser::OWNER_MEMBER,
            ])->save();
        }

        return $project;
    }

    private function makeTask(Project $project, User $creator, string $name, int $visibility = 1): ProjectTask
    {
        $task = ProjectTask::createInstance([
            'project_id' => $project->id,
            'parent_id' => 0,
            'name' => $name,
            'desc' => 'Task search description',
            'userid' => $creator->userid,
            'visibility' => $visibility,
        ]);
        $task->save();

        return $task->fresh();
    }

    private function searchTasks(User $user, string $key): array
    {
        $request = Request::create('/api/search/task', 'GET', ['key' => $key, 'take' => 50]);
        $request->attributes->set('access_token_user', $user);
        $this->app->instance('request', $request);
        RequestFacade::swap($request);
        RequestContext::begin($request);
        RequestContext::save('app_installed_search', false);

        try {
            return app(SearchController::class)->task()['data'];
        } finally {
            RequestContext::clean();
        }
    }

    public function test_global_search_finds_task_id_without_project_context(): void
    {
        $owner = $this->makeUser('task-id-search-owner@test.local');
        $member = $this->makeUser('task-id-search-member@test.local');
        $project = $this->makeProject($owner, [$member->userid]);
        $task = $this->makeTask($project, $owner, 'Locate this task');

        $results = $this->searchTasks($member, (string)$task->id);

        $this->assertCount(1, $results);
        $this->assertSame($task->id, $results[0]['id']);
        $this->assertSame($project->name, $results[0]['project_name']);
    }

    public function test_global_search_does_not_return_private_task_to_unassigned_project_member(): void
    {
        $owner = $this->makeUser('task-id-private-owner@test.local');
        $member = $this->makeUser('task-id-private-member@test.local');
        $project = $this->makeProject($owner, [$member->userid]);
        $task = $this->makeTask($project, $owner, 'Private task');
        $task->name = 'Private task ' . $task->id;
        $task->visibility = 2;
        $task->save();

        $this->assertSame([], $this->searchTasks($member, (string)$task->id));
    }

    public function test_global_search_includes_archived_tasks_but_excludes_soft_deleted_tasks(): void
    {
        $owner = $this->makeUser('task-id-archive-owner@test.local');
        $project = $this->makeProject($owner);
        $archivedTask = $this->makeTask($project, $owner, 'Archived task');
        $archivedTask->archived_at = now();
        $archivedTask->save();
        $deletedTask = $this->makeTask($project, $owner, 'Deleted task');
        $deletedTask->delete();

        $archivedResults = $this->searchTasks($owner, (string)$archivedTask->id);
        $deletedResults = $this->searchTasks($owner, (string)$deletedTask->id);

        $this->assertCount(1, $archivedResults);
        $this->assertSame($archivedTask->id, $archivedResults[0]['id']);
        $this->assertSame([], $deletedResults);
    }

    public function test_numeric_search_still_falls_back_to_task_text(): void
    {
        $owner = $this->makeUser('task-id-search-fallback@test.local');
        $project = $this->makeProject($owner);
        $task = $this->makeTask($project, $owner, 'Reference 90817 in the title');

        $results = $this->searchTasks($owner, '90817');

        $this->assertCount(1, $results);
        $this->assertSame($task->id, $results[0]['id']);
    }

    public function test_global_search_does_not_find_task_from_an_unjoined_project(): void
    {
        $member = $this->makeUser('task-id-search-unjoined@test.local');
        $otherOwner = $this->makeUser('task-id-search-other-owner@test.local');
        $this->makeProject($member);
        $otherProject = $this->makeProject($otherOwner);
        $task = $this->makeTask($otherProject, $otherOwner, 'Outside project task');

        $this->assertSame([], $this->searchTasks($member, (string)$task->id));
    }
}
