<?php

namespace App\Services;

use App\Models\WebSocket;

/**
 * Resolve all user connections for a push batch with bounded queries.
 */
class OnlineUserSockets
{
    private const LOOKUP_CHUNK_SIZE = 500;

    /**
     * @return array<int, array<int, string>>
     */
    public static function forPushLists(array $lists): array
    {
        $userids = [];
        foreach ($lists as $item) {
            if (!is_array($item) || empty($item['userid'])) {
                continue;
            }
            foreach (is_array($item['userid']) ? $item['userid'] : [$item['userid']] as $userid) {
                if ($userid) {
                    $userids[$userid] = true;
                }
            }
        }

        $fdsByUser = [];
        foreach (array_chunk(array_keys($userids), self::LOOKUP_CHUNK_SIZE) as $chunk) {
            foreach (WebSocket::whereIn('userid', $chunk)->get(['userid', 'fd']) as $socket) {
                $fdsByUser[$socket->userid][] = $socket->fd;
            }
        }

        return $fdsByUser;
    }
}
