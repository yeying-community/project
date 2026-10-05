<?php

namespace Tests\Feature;

use App\Models\WebSocket;
use App\Services\OnlineUserSockets;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnlineUserSocketsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_push_batch_resolves_multiple_connections_per_user_in_bounded_queries(): void
    {
        $firstUser = random_int(3_000_000, 4_000_000);
        $secondUser = $firstUser + 1;
        foreach ([
            [$firstUser, 'fd-one'],
            [$firstUser, 'fd-two'],
            [$secondUser, 'fd-three'],
        ] as [$userid, $fd]) {
            $socket = WebSocket::createInstance([
                'key' => uniqid('fanout_', true),
                'userid' => $userid,
                'fd' => $fd,
            ]);
            $socket->save();
        }

        $allUsers = range($firstUser, $firstUser + 500);
        $lists = [
            ['userid' => $allUsers],
            ['userid' => $firstUser],
            ['fd' => 'direct-fd'],
        ];
        DB::enableQueryLog();
        try {
            $fdsByUser = OnlineUserSockets::forPushLists($lists);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertEqualsCanonicalizing(['fd-one', 'fd-two'], $fdsByUser[$firstUser]);
        $this->assertSame(['fd-three'], $fdsByUser[$secondUser]);
        $this->assertArrayNotHasKey($firstUser + 2, $fdsByUser);
        $socketQueries = array_filter($queries, function ($query) {
            $sql = strtolower($query['query']);
            return str_starts_with($sql, 'select') && str_contains($sql, 'web_sockets');
        });
        $this->assertCount(2, $socketQueries);
    }
}
