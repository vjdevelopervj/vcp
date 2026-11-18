<?php

require_once __DIR__ . '/_common.php';

$user = api_user();
$uid = (int) $user['id'];

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$type = valid_type($_GET['type'] ?? null);
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_out(['error' => 'Method not allowed'], 405);
}

/** Ringkasan sebaris untuk kartu history: di sekitar kata yang dicari kalau ada query. */
function history_snippet(string $content, string $needle): string
{
    $text = trim((string) preg_replace('/[#*`>|]+/u', ' ', $content));
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($needle !== '') {
        $pos = mb_stripos($text, $needle);
        if ($pos !== false) {
            $start = max(0, $pos - 60);
            return ($start > 0 ? '… ' : '') . rtrim(mb_substr($text, $start, 200)) . (mb_strlen($text) > $start + 200 ? ' …' : '');
        }
    }

    return mb_strlen($text) > 200 ? rtrim(mb_substr($text, 0, 200)) . ' …' : $text;
}

$where = 'd.user_id = ?';
$params = [$uid];
if ($type) {
    $where .= ' AND d.type = ?';
    $params[] = $type;
}
$like = '%' . addcslashes($q, '%_\\') . '%';
if ($q !== '') {
    $where .= ' AND (d.title LIKE ? OR EXISTS (SELECT 1 FROM messages m WHERE m.document_id = d.id AND m.content LIKE ?))';
    $params[] = $like;
    $params[] = $like;
}

$stmt = db()->prepare("SELECT COUNT(*) FROM documents d WHERE $where");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();

$stmt = db()->prepare("SELECT d.id, d.title, d.type, d.pinned, d.updated_at,
        (SELECT COUNT(*) FROM messages m WHERE m.document_id = d.id) AS message_count
    FROM documents d WHERE $where
    ORDER BY d.pinned DESC, d.updated_at DESC, d.id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

if ($items) {
    $ids = array_column($items, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT document_id, role, content FROM (
            SELECT m.document_id, m.role, m.content,
                   ROW_NUMBER() OVER (PARTITION BY m.document_id ORDER BY m.id) AS rn
            FROM messages m WHERE m.document_id IN ($in)" . ($q !== '' ? ' AND m.content LIKE ?' : '') . '
        ) t WHERE t.rn = 1';
    $snip = db()->prepare($sql);
    $snip->execute($q !== '' ? array_merge($ids, [$like]) : $ids);
    $byDoc = array_column($snip->fetchAll(), null, 'document_id');

    foreach ($items as &$item) {
        $row = $byDoc[$item['id']] ?? null;
        $item['snippet'] = $row ? history_snippet((string) $row['content'], $q) : '';
        $item['snippet_role'] = $row ? $row['role'] : null;
    }
    unset($item);
}

json_out([
    'total' => $total,
    'offset' => $offset,
    'limit' => $limit,
    'items' => $items,
]);
