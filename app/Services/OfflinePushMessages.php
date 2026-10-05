<?php

namespace App\Services;

use App\Models\WebSocketTmpMsg;
use App\Module\Base;
use Carbon\Carbon;

/**
 * Persist retryable notifications for offline users in bounded batches.
 */
class OfflinePushMessages
{
    private const INSERT_CHUNK_SIZE = 100;
    private const INSERT_CHUNK_BYTES = 512 * 1024;

    /**
     * @param array<int, array{userid: int, msg: array<string, mixed>}> $messages
     */
    public static function store(array $messages): void
    {
        $rows = [];
        $bytes = 0;
        foreach ($messages as $item) {
            $msgString = Base::array2json($item['msg']);
            $rowBytes = strlen($msgString);
            if ($rows && (count($rows) >= self::INSERT_CHUNK_SIZE || $bytes + $rowBytes > self::INSERT_CHUNK_BYTES)) {
                WebSocketTmpMsg::query()->insertOrIgnore($rows);
                $rows = [];
                $bytes = 0;
            }
            $now = Carbon::now();
            $rows[] = [
                'md5' => md5($item['userid'] . '-' . $msgString),
                'msg' => $msgString,
                'send' => 0,
                'create_id' => $item['userid'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $bytes += $rowBytes;
        }
        if ($rows) {
            WebSocketTmpMsg::query()->insertOrIgnore($rows);
        }
    }
}
