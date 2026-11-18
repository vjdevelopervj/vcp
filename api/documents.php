<?php

require_once __DIR__ . '/_common.php';

$user = api_user();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $doc = find_document($uid, (int) $_GET['id']);
        $stmt = db()->prepare('SELECT id, role, content, attachments, created_at FROM messages WHERE document_id = ? ORDER BY id');
        $stmt->execute([$doc['id']]);
        $messages = array_map(static function (array $m): array {
            $m['attachments'] = is_string($m['attachments']) ? (json_decode($m['attachments'], true) ?: []) : [];
            return $m;
        }, $stmt->fetchAll());
        json_out(['document' => $doc, 'messages' => $messages]);
    }

    $sql = 'SELECT id, title, type, pinned, updated_at FROM documents WHERE user_id = ?';
    $params = [$uid];
    if ($type = valid_type($_GET['type'] ?? null)) {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }
    if (!empty($_GET['q']) && is_string($_GET['q'])) {
        $sql .= ' AND title LIKE ?';
        $params[] = '%' . addcslashes($_GET['q'], '%_\\') . '%';
    }
    $sql .= ' ORDER BY pinned DESC, updated_at DESC, id DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $counts = db()->prepare('SELECT type, COUNT(*) AS total FROM documents WHERE user_id = ? GROUP BY type');
    $counts->execute([$uid]);

    json_out([
        'documents' => $stmt->fetchAll(),
        'counts' => array_column($counts->fetchAll(), 'total', 'type'),
    ]);
}

$input = api_input();
$action = $input['action'] ?? '';

switch ($action) {
    case 'update':
        $doc = find_document($uid, (int) ($input['id'] ?? 0));
        $title = isset($input['title']) ? trim((string) $input['title']) : $doc['title'];
        $content = isset($input['content']) ? (string) $input['content'] : $doc['content'];
        if ($title === '' || mb_strlen($title) > 150) {
            json_out(['error' => 'Judul wajib diisi (maks 150 karakter).'], 422);
        }
        db()->prepare('UPDATE documents SET title = ?, content = ? WHERE id = ?')
            ->execute([$title, $content, $doc['id']]);
        json_out(['document' => find_document($uid, (int) $doc['id'])]);

    case 'pin':
        $doc = find_document($uid, (int) ($input['id'] ?? 0));
        $pinned = !empty($input['pinned']) ? 1 : 0;
        db()->prepare('UPDATE documents SET pinned = ? WHERE id = ?')->execute([$pinned, $doc['id']]);
        json_out(['document' => find_document($uid, (int) $doc['id'])]);

    case 'delete':
        $doc = find_document($uid, (int) ($input['id'] ?? 0));
        db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$doc['id']]);
        json_out(['ok' => true]);

    default:
        json_out(['error' => 'Unknown action'], 400);
}
