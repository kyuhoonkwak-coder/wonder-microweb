<?php
require __DIR__ . '/_auth.php';
admin_require_login();
require __DIR__ . '/../api/db.php';

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$pdo = wonder_db();

$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE name LIKE :q OR phone LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) c FROM leads $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM leads $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtPhone($digits) {
    $digits = preg_replace('/\D/', '', (string)$digits);
    if (strlen($digits) === 11) return substr($digits,0,3).'-'.substr($digits,3,4).'-'.substr($digits,7);
    if (strlen($digits) === 10) return substr($digits,0,3).'-'.substr($digits,3,3).'-'.substr($digits,6);
    return $digits;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>상담 신청 관리 | 원더</title>
<style>
  * { box-sizing:border-box; }
  body { font-family: -apple-system, "Pretendard", sans-serif; background:#F5F5ED; margin:0; color:#1a1a1a; }
  .wrap { max-width:1100px; margin:0 auto; padding:32px 20px 60px; }
  .head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
  h1 { font-size:20px; margin:0; }
  .head-actions { display:flex; gap:8px; align-items:center; }
  .count { font-size:13px; color:#666; }
  a.btn, button.btn { display:inline-block; padding:8px 14px; border-radius:6px; font-size:13px; text-decoration:none; border:1px solid #ccc; background:#fff; color:#1a1a1a; cursor:pointer; }
  a.btn.dark { background:#1a1a1a; color:#fff; border-color:#1a1a1a; }
  form.search { margin-bottom:16px; display:flex; gap:8px; }
  form.search input { flex:1; max-width:280px; padding:9px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
  table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.05); }
  th, td { padding:10px 12px; text-align:left; font-size:13px; border-bottom:1px solid #eee; white-space:nowrap; }
  th { background:#f0f0e8; font-weight:600; color:#444; }
  tr:last-child td { border-bottom:none; }
  td.calc { white-space:normal; max-width:260px; font-size:12px; color:#555; }
  .empty { padding:40px; text-align:center; color:#888; }
  .pager { display:flex; gap:6px; margin-top:20px; justify-content:center; }
  .pager a, .pager span { padding:6px 11px; border:1px solid #ddd; border-radius:6px; font-size:13px; text-decoration:none; color:#333; }
  .pager .current { background:#1a1a1a; color:#fff; border-color:#1a1a1a; }
  .tag { display:inline-block; padding:2px 8px; border-radius:20px; background:#eee; font-size:11px; }
  .tag.yes { background:#e6f4ea; color:#1e7e34; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>상담 신청 목록</h1>
    <div class="head-actions">
      <span class="count">총 <?= number_format($total) ?>건</span>
      <a class="btn" href="export.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">CSV 내보내기</a>
      <a class="btn" href="logout.php">로그아웃</a>
    </div>
  </div>

  <form class="search" method="get">
    <input type="text" name="q" placeholder="이름 또는 전화번호 검색" value="<?= h($q) ?>">
    <button class="btn" type="submit">검색</button>
    <?php if ($q !== ''): ?><a class="btn" href="index.php">초기화</a><?php endif; ?>
  </form>

  <?php if (!$leads): ?>
    <div class="empty">신청 내역이 없습니다.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>이름</th>
        <th>연락처</th>
        <th>연령대</th>
        <th>연락가능시간</th>
        <th>유입경로</th>
        <th>마케팅동의</th>
        <th>계산기 결과</th>
        <th>신청일시</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($leads as $l): ?>
      <tr>
        <td><?= (int)$l['id'] ?></td>
        <td><?= h($l['name']) ?></td>
        <td><a href="tel:<?= h($l['phone']) ?>"><?= h(fmtPhone($l['phone'])) ?></a></td>
        <td><?= h($l['age'] ?: '-') ?></td>
        <td><?= h($l['contact_time'] ?: '-') ?></td>
        <td><?= h($l['source'] ?: '-') ?></td>
        <td><span class="tag <?= $l['agree_marketing'] ? 'yes' : '' ?>"><?= $l['agree_marketing'] ? '동의' : '미동의' ?></span></td>
        <td class="calc"><?= h($l['calc_summary'] ?: '-') ?></td>
        <td><?= h($l['created_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pager">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php if ($p === $page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="?page=<?= $p ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
