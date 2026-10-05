<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebSocketDialog;
use App\Models\WebSocketDialogMsg;
use App\Models\WebSocketDialogMsgRead;
use Carbon\Carbon;

/**
 * Prepare a message's recipients without issuing queries for each group member.
 */
class DialogMessageRecipients
{
    private const INSERT_CHUNK_SIZE = 250;
    private const BOT_LOOKUP_CHUNK_SIZE = 500;

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, bool>}
     */
    public static function prepare(
        WebSocketDialogMsg $msg,
        WebSocketDialog $dialog,
        array $userids,
        array $mentions,
        array $updateds,
        array $silences,
        bool $forceSilence
    ): array {
        $recipients = [];
        $receipts = [];
        $botCandidates = [];
        $markRead = $dialog->session_id && $dialog->session_id != $msg->session_id;
        $readAt = $markRead ? Carbon::now()->toDateTimeString() : null;

        foreach ($userids as $userid) {
            $silence = $forceSilence || ($silences[$userid] ?? false);
            $updated = $updateds[$userid] ?? $msg->created_at;
            if ($userid == $msg->userid) {
                $recipients[$userid] = [
                    'userid' => $userid,
                    'mention' => 0,
                    'silence' => $silence,
                    'dot' => 0,
                    'updated' => $updated,
                ];
                continue;
            }

            $mention = array_intersect([0, $userid], $mentions) ? 1 : 0;
            $silence = $mention ? false : $silence;
            $dot = $msg->type === 'record' ? 1 : 0;
            $receipt = [
                'dialog_id' => $msg->dialog_id,
                'msg_id' => $msg->id,
                'userid' => $userid,
                'mention' => $mention,
                'silence' => $silence,
                'dot' => $dot,
            ];
            if ($markRead) {
                $receipt['read_at'] = $readAt;
            }
            $receipts[] = $receipt;
            $botCandidates[] = $userid;
            $recipients[$userid] = [
                'userid' => $userid,
                'mention' => $mention,
                'silence' => $silence,
                'dot' => $dot,
                'updated' => $updated,
            ];
        }

        // INSERT IGNORE preserves saveOrIgnore's duplicate handling. Batching
        // removes one transaction and fsync per recipient in large groups.
        foreach (array_chunk($receipts, self::INSERT_CHUNK_SIZE) as $chunk) {
            WebSocketDialogMsgRead::query()->insertOrIgnore($chunk);
        }

        $botIds = [];
        foreach (array_chunk($botCandidates, self::BOT_LOOKUP_CHUNK_SIZE) as $chunk) {
            foreach (User::whereIn('userid', $chunk)->whereBot(1)->pluck('userid') as $botId) {
                $botIds[(int) $botId] = true;
            }
        }

        return [$recipients, $botIds];
    }
}
