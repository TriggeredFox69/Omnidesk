<?php
/** Persistent inbox and media adapter for WhatsApp Cloud API webhooks. */
function cloudInboxPath(): string {
    return __DIR__ . '/../data/whatsapp-cloud/inbox.json';
}

function cloudInboxRead(): array {
    $file = cloudInboxPath();
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0700, true);
    $handle = fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) fclose($handle);
        return ['epoch' => bin2hex(random_bytes(16)), 'cursor' => 0, 'messages' => [], 'receipts' => []];
    }
    $state = json_decode(stream_get_contents($handle) ?: '', true);
    flock($handle, LOCK_UN);
    fclose($handle);
    if (!is_array($state) || !isset($state['messages']) || !is_array($state['messages'])) {
        return ['epoch' => bin2hex(random_bytes(16)), 'cursor' => 0, 'messages' => [], 'receipts' => []];
    }
    if (!isset($state['receipts']) || !is_array($state['receipts'])) $state['receipts'] = [];
    return $state;
}

function cloudInboxChange(callable $change): array {
    $file = cloudInboxPath();
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0700, true);
    $handle = fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Could not lock the Cloud API inbox.');
    }
    $state = json_decode(stream_get_contents($handle) ?: '', true);
    if (!is_array($state) || !isset($state['messages']) || !is_array($state['messages'])) {
        $state = ['epoch' => bin2hex(random_bytes(16)), 'cursor' => 0, 'messages' => [], 'receipts' => []];
    }
    if (!isset($state['receipts']) || !is_array($state['receipts'])) $state['receipts'] = [];
    $change($state);
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function cloudInboxAppend(array $item): void {
    cloudInboxChange(function (&$state) use ($item) {
        $receipt = $state['receipts'][$item['waMessageId'] ?? ''] ?? null;
        if ($receipt && ($item['sender'] ?? '') === 'agent') {
            $item['deliveryStatus'] = $receipt['status'];
            $item['deliveryError'] = $receipt['error'];
        }
        foreach ($state['messages'] as &$existing) {
            if (($existing['waMessageId'] ?? '') === ($item['waMessageId'] ?? '')) {
                $existing = array_merge($existing, $item);
                $existing['revision'] = ++$state['cursor'];
                unset($existing);
                return;
            }
        }
        unset($existing);
        $item['revision'] = ++$state['cursor'];
        $state['messages'][] = $item;
        if (count($state['messages']) > 2000) array_shift($state['messages']);
    });
}

function cloudInboxEvents(int $after): array {
    $state = cloudInboxRead();
    return ['success' => true, 'epoch' => $state['epoch'], 'cursor' => (int)$state['cursor'],
        'messages' => array_values(array_filter($state['messages'], fn($item) => (int)($item['revision'] ?? 0) > $after))];
}

function cloudInboxStoreMessage(array $message, string $sender, string $phone, string $name = ''): void {
    $id = (string)($message['id'] ?? '');
    if ($id === '' || !preg_match('/^\d{10,15}$/', $phone)) return;
    $type = (string)($message['type'] ?? 'text');
    $data = $message[$type] ?? [];
    $hasMedia = in_array($type, ['image', 'audio', 'video', 'document', 'sticker'], true);
    if ($type === 'sticker') $type = 'image';
    if (!in_array($type, ['text', 'image', 'audio', 'video', 'document'], true)) {
        $body = $data['body'] ?? ($data['text'] ?? ucfirst((string)($message['type'] ?? 'Message')));
        $type = 'text';
    } else {
        $body = $data['text'] ?? $data['caption'] ?? '';
        if ($body === '' && $type !== 'text') $body = ucfirst($type);
    }
    cloudInboxAppend([
        'id' => 'wa_' . substr(hash('sha256', $id), 0, 24), 'waMessageId' => $id,
        'phone' => $phone, 'name' => $name !== '' ? $name : $phone, 'sender' => $sender, 'type' => $type,
        'text' => (string)$body, 'timestamp' => (int)($message['timestamp'] ?? time()),
        'hasMedia' => $hasMedia, 'mediaId' => $hasMedia ? (string)($data['id'] ?? '') : '',
        'filename' => (string)($data['filename'] ?? ''), 'deliveryStatus' => $sender === 'agent' ? 'sent' : null
    ]);
}

function cloudInboxApplyWebhook(array $payload): void {
    foreach (($payload['entry'] ?? []) as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            $value = $change['value'] ?? [];
            $profiles = [];
            foreach (($value['contacts'] ?? []) as $contact) {
                $waId = preg_replace('/\D+/', '', (string)($contact['wa_id'] ?? ''));
                if ($waId !== '') $profiles[$waId] = $contact['profile']['name'] ?? $waId;
            }
            $echoes = $value['message_echoes'] ?? [];
            if (($change['field'] ?? '') === 'smb_message_echoes' && !$echoes) $echoes = $value['messages'] ?? [];
            $items = array_merge(
                array_map(fn($message) => [$message, 'lead'], $value['messages'] ?? []),
                array_map(fn($message) => [$message, 'agent'], $echoes)
            );
            foreach ($items as [$message, $sender]) {
                $phone = preg_replace('/\D+/', '', (string)($sender === 'agent' ? ($message['to'] ?? '') : ($message['from'] ?? '')));
                cloudInboxStoreMessage($message, $sender, $phone, (string)($profiles[$phone] ?? ''));
            }
            // Coexistence can deliver the initial shared history in separate history chunks.
            foreach (($value['history'] ?? []) as $historyChunk) {
                foreach (($historyChunk['threads'] ?? []) as $thread) {
                    $threadPhone = preg_replace('/\D+/', '', (string)($thread['id'] ?? ''));
                    foreach (($thread['messages'] ?? []) as $message) {
                        $from = preg_replace('/\D+/', '', (string)($message['from'] ?? ''));
                        $to = preg_replace('/\D+/', '', (string)($message['to'] ?? ''));
                        $businessPhone = preg_replace('/\D+/', '', (string)($value['metadata']['display_phone_number'] ?? ''));
                        $sender = ($from !== '' && $from === $businessPhone) || ($to !== '' && $to !== $businessPhone) ? 'agent' : 'lead';
                        $phone = $threadPhone ?: ($sender === 'agent' ? $to : $from);
                        cloudInboxStoreMessage($message, $sender, $phone, (string)($profiles[$phone] ?? ''));
                    }
                }
            }
            foreach (($value['statuses'] ?? []) as $status) {
                $id = (string)($status['id'] ?? '');
                $stateName = (string)($status['status'] ?? '');
                if ($id === '' || !in_array($stateName, ['sent', 'delivered', 'read', 'failed'], true)) continue;
                $statusError = $stateName === 'failed' ? ($status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'WhatsApp reported delivery failure.') : null;
                cloudInboxChange(function (&$state) use ($id, $stateName, $statusError) {
                    $state['receipts'][$id] = ['status' => $stateName, 'error' => $statusError];
                    foreach ($state['messages'] as &$item) {
                        if (($item['waMessageId'] ?? '') === $id && ($item['sender'] ?? '') === 'agent') {
                            $item['deliveryStatus'] = $stateName;
                            $item['deliveryError'] = $statusError;
                            $item['revision'] = ++$state['cursor'];
                            break;
                        }
                    }
                    unset($item);
                });
            }
        }
    }
}

function cloudInboxDownloadMedia(string $mediaId, string $token, string $version): array {
    if ($mediaId === '' || $token === '' || !preg_match('/^v[0-9]+\.[0-9]+$/', $version)) {
        throw new RuntimeException('Cloud API media is not configured.');
    }
    $metaCurl = curl_init("https://graph.facebook.com/{$version}/" . rawurlencode($mediaId));
    curl_setopt_array($metaCurl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
    $metaBody = curl_exec($metaCurl);
    $metaCode = (int)curl_getinfo($metaCurl, CURLINFO_HTTP_CODE);
    curl_close($metaCurl);
    $meta = json_decode((string)$metaBody, true);
    if ($metaBody === false || $metaCode < 200 || $metaCode >= 300 || empty($meta['url'])) {
        throw new RuntimeException($meta['error']['message'] ?? 'Could not retrieve WhatsApp media details.');
    }
    $mediaCurl = curl_init($meta['url']);
    curl_setopt_array($mediaCurl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
    $bytes = curl_exec($mediaCurl);
    $mediaCode = (int)curl_getinfo($mediaCurl, CURLINFO_HTTP_CODE);
    $mime = curl_getinfo($mediaCurl, CURLINFO_CONTENT_TYPE) ?: ($meta['mime_type'] ?? 'application/octet-stream');
    curl_close($mediaCurl);
    if ($bytes === false || $mediaCode < 200 || $mediaCode >= 300 || strlen($bytes) > 16 * 1024 * 1024) {
        throw new RuntimeException('Could not download media, or it exceeds 16 MB.');
    }
    return ['success' => true, 'data_url' => 'data:' . $mime . ';base64,' . base64_encode($bytes), 'filename' => $meta['file_name'] ?? ''];
}
