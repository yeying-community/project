<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebSocketDialog;
use App\Models\WebSocketDialogMsg;
use App\Models\WebSocketDialogMsgRead;
use App\Services\DialogMessageRecipients;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DialogMessageRecipientsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_group_receipts_are_inserted_in_batches_without_duplicates(): void
    {
        $msgId = random_int(1_000_000_000, 1_999_999_999);
        $msg = WebSocketDialogMsg::createInstance([
            'id' => $msgId,
            'dialog_id' => $msgId,
            'userid' => -1,
            'type' => 'notice',
        ]);
        $dialog = WebSocketDialog::createInstance(['id' => $msgId]);
        $userids = range(2_000_000, 2_000_250);
        $silences = array_fill_keys($userids, false);
        $updateds = array_fill_keys($userids, Carbon::now());

        DB::enableQueryLog();
        try {
            [$recipients, $botIds] = DialogMessageRecipients::prepare(
                $msg, $dialog, $userids, [], $updateds, $silences, false
            );
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertCount(251, $recipients);
        $this->assertSame([], $botIds);
        $this->assertSame(251, WebSocketDialogMsgRead::whereMsgId($msgId)->count());
        $inserts = array_filter($queries, function ($query) {
            $sql = strtolower($query['query']);
            return str_starts_with($sql, 'insert') && str_contains($sql, 'web_socket_dialog_msg_reads');
        });
        $this->assertCount(2, $inserts);

        DialogMessageRecipients::prepare($msg, $dialog, $userids, [], $updateds, $silences, false);
        $this->assertSame(251, WebSocketDialogMsgRead::whereMsgId($msgId)->count());
    }

    public function test_session_reads_mentions_and_bot_recipients_keep_their_behavior(): void
    {
        $bot = User::createInstance([
            'email' => 'fanout_bot_' . uniqid() . '@example.test',
            'userimg' => '',
            'nickname' => 'Fanout Bot',
            'profession' => '',
            'password' => md5('test-password'),
            'bot' => 1,
        ]);
        $bot->save();

        $msgId = random_int(1_000_000_000, 1_999_999_999);
        $msg = WebSocketDialogMsg::createInstance([
            'id' => $msgId,
            'dialog_id' => $msgId,
            'session_id' => 1,
            'userid' => 2_100_000,
            'type' => 'record',
        ]);
        $dialog = WebSocketDialog::createInstance(['id' => $msgId, 'session_id' => 2]);
        $userids = [$msg->userid, $bot->userid, 2_100_001];
        $updateds = array_fill_keys($userids, Carbon::now());
        $silences = array_fill_keys($userids, true);

        [$recipients, $botIds] = DialogMessageRecipients::prepare(
            $msg, $dialog, $userids, [$bot->userid], $updateds, $silences, false
        );

        $this->assertSame(2, WebSocketDialogMsgRead::whereMsgId($msgId)->count());
        $this->assertFalse(WebSocketDialogMsgRead::whereMsgId($msgId)->whereUserid($msg->userid)->exists());
        $this->assertNotNull(WebSocketDialogMsgRead::whereMsgId($msgId)->whereUserid($bot->userid)->first()->read_at);
        $this->assertSame(1, (int) $recipients[$bot->userid]['mention']);
        $this->assertFalse((bool) $recipients[$bot->userid]['silence']);
        $this->assertSame(1, (int) $recipients[2_100_001]['dot']);
        $this->assertTrue((bool) $recipients[2_100_001]['silence']);
        $this->assertTrue($botIds[$bot->userid]);
    }
}
