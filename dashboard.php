<?php
// config.phpを安全に読み込む（必要に応じてパスを調整してください）
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 登録済みのお菓子一覧を全取得（管理用）
    $sql = "SELECT * FROM kashi_master ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $kashi_list = $stmt->fetchAll();

} catch (PDOException $e) {
    exit('データベースエラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード（管理コンパネ） - 菓子ペディア</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; padding: 0; background: #f4f7f6; color: #333; 
        }
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        
        /* ヘッダー */
        .header { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 24px; color: #1a5f7a; }
        .nav-links { display: flex; gap: 16px; }
        .nav-btn { background: #1a5f7a; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .nav-btn:hover { background: #10375c; }

        /* グリッドレイアウト */
        .grid-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; margin-bottom: 24px; }
        
        /* カードコンポーネント */
        .card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid #1a5f7a; }
        .card h2 { margin-top: 0; font-size: 18px; color: #2d3748; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
        .card ul { padding-left: 20px; color: #4a5568; font-size: 14px; line-height: 1.8; margin: 0; }
        
        /* テーブル一覧 */
        .data-table-wrap { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .data-table-wrap h2 { margin-top: 0; font-size: 18px; color: #2d3748; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th, td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f8fafc; font-weight: bold; color: #475569; }
        tr:hover { background-color: #f1f5f9; }
        .action-links a { color: #1a5f7a; text-decoration: none; margin-right: 12px; font-weight: 500; }
        .action-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="dashboard-container">
    
    <header class="header">
        <h1>⚙️ 菓子ペディア ダッシュボード</h1>
        <div class="nav-links">
            <a href="index.php" class="nav-btn" style="background: #475569;">ユーザー画面へ</a>
            <a href="add.php" class="nav-btn">+ お菓子登録</a>
        </div>
    </header>

    <div class="grid-row">
        <div class="card" style="border-top-color: #2b6cb0;">
            <h2>🏷️ カテゴリー小項目タブ手動管理（準備中）</h2>
            <p style="color: #4a5568; font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                カテゴリの統制辞書（大項目・小項目の紐付け、類義語・シノニムの設定）を手動で行うマスター管理画面です。「その他」からの昇格や表記揺れによる無限増殖を防ぎます。
            </p>
        </div>

        <div class="card">
            <h2>📈 データ分析（準備中）</h2>
            <p style="color: #4a5568; font-size: 14px; line-height: 1.6; margin-bottom: 8px;">蓄積された美味評価データを多角的に確認・抽出できる機能の枠組みです。</p>
            <ul>
                <li>サマリー（全体の傾向や集計値の概要）</li>
                <li>表（詳細な数値データの確認）</li>
                <li>グラフ（推移の可視化）</li>
                <li>抽出（条件ごとのデータエクスポート・絞り込み）</li>
            </ul>
        </div>
    </div>

    <div class="data-table-wrap">
        <h2>🍬 登録済みお菓子一覧（マスターデータ）</h2>
        <table>
            <thead>
                <tr>
                    <th>コード</th>
                    <th>お菓子名</th>
                    <th>登録日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kashi_list)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 32px; color: #64748b;">お菓子データがありません。</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kashi_list as $kashi): ?>
                        <tr>
                            <td><?= h($kashi['kashi_code']) ?></td>
                            <td style="font-weight: 500;"><?= h($kashi['name']) ?></td>
                            <td><?= h(date('Y.m.d H:i', strtotime($kashi['created_at']))) ?></td>
                            <td class="action-links">
                                <a href="detail.php?id=<?= h($kashi['id']) ?>" target="_blank">詳細確認</a>
                                </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>