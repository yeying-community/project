<?php

namespace Tests\Feature;

use App\Models\WebSocketTmpMsg;
use App\Module\Base;
use App\Services\OfflinePushMessages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfflinePushMessagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_offline_notifications_are_inserted_in_batches_without_duplicates(): void
    {
        $firstUser = random_int(4_000_000, 5_000_000);
        $msg = ['type' => 'dialog', 'mode' => 'add', 'data' => ['id' => uniqid('fanout_', true)]];
        $messages = [];
        foreach (range($firstUser, $firstUser + 250) as $userid) {
            $messages[] = ['userid' => $userid, 'msg' => $msg];
        }

        DB::enableQueryLog();
        try {
            OfflinePushMessages::store($messages);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $inserts = array_filter($queries, function ($query) {
            $sql = strtolower($query['query']);
            return str_starts_with($sql, 'insert') && str_contains($sql, 'web_socket_tmp_msgs');
        });
        $this->assertCount(3, $inserts);
        $md5 = md5($firstUser . '-' . Base::array2json($msg));
        $saved = WebSocketTmpMsg::whereMd5($md5)->firstOrFail();
        $this->assertSame($firstUser, (int) $saved->create_id);
        $this->assertSame(Base::array2json($msg), $saved->msg);
        $this->assertSame(0, (int) $saved->send);

        OfflinePushMessages::store($messages);
        $this->assertSame(1, WebSocketTmpMsg::whereMd5($md5)->count());
        $this->assertSame(251, WebSocketTmpMsg::whereIn('create_id', range($firstUser, $firstUser + 250))
            ->where('msg', Base::array2json($msg))->count());
    }
}
