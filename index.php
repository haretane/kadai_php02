<?php
// config.phpを安全に読み込む
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 検索キーワードとカテゴリーの取得
    $search   = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    // 表示切り替えパラメータの取得（デフォルトはグリッド表示 'grid'）
    $view_mode = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'grid';

    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM kashi_master WHERE name LIKE :search OR maker LIKE :search ORDER BY created_at DESC");
        $stmt->execute(['search' => '%' . $search . '%']);
        $kashi_list = $stmt->fetchAll();
        $is_search_mode = true;
    } elseif ($category !== '') {
        $search_target = $category;
        if (strpos($category, '/') !== false) {
            $parts = explode('/', $category);
            $search_target = end($parts);
        }

        $tags = preg_split('/\s+/', trim($search_target));
        
        if (!empty($tags) && $tags[0] !== '') {
            $placeholders = [];
            $params = [];
            foreach ($tags as $index => $tag) {
                $placeholders[] = ":tag{$index}";
                $params["tag{$index}"] = $tag;
            }
            $in_clause = implode(',', $placeholders);

            $sql = "
                SELECT DISTINCT km.* FROM kashi_master km
                JOIN kashi_tag_relations ktr ON km.kashi_code = ktr.kashi_code
                JOIN tags_master tm ON ktr.tag_id = tm.id
                WHERE tm.tag_name IN ($in_clause)
                ORDER BY km.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $kashi_list = $stmt->fetchAll();
        } else {
            $kashi_list = [];
        }
        $is_search_mode = true;
    } else {
        // 初期状態（新着順で全件取得）
        $stmt = $pdo->query("SELECT * FROM kashi_master ORDER BY created_at DESC");
        $kashi_list = $stmt->fetchAll();
        $is_search_mode = false;
    }

    // 各お菓子に紐づく画像を取得するヘルパー関数
    function getThumbnailUrl($pdo, $kashi_code) {
        $sql = "SELECT image_url FROM kashi_images WHERE kashi_code = :kashi_code AND is_main = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return ($row && !empty($row['image_url'])) ? $row['image_url'] : null;
    }

} catch (PDOException $e) {
    exit('データベースエラー: ' . h($e->getMessage()));
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// 遷移用URLを構築するヘルパー関数（現在のパラメータを維持します）
function build_view_url($view_mode, $search, $category) {
    $params = [];
    if ($search !== '') $params['search'] = $search;
    if ($category !== '') $params['category'] = $category;
    $params['view'] = ($view_mode === 'grid') ? 'list' : 'grid';
    return 'index.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>菓子ペディア</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif; 
            margin: 0; padding: 0; background: #f0f4f8; -webkit-text-size-adjust: 100%; 
        }

        .smartphone-screen {
            max-width: 480px;
            min-height: 100vh;
            margin: 0 auto;
            background: #fff;
            position: relative;
            padding-bottom: 80px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header-area { background: #fff; padding: 16px; border-bottom: 1px solid #e1e8ed; }
        .top-title { text-align: center; font-size: 22px; color: #333; margin: 0 0 16px; font-weight: bold; }
        
        .search-sort-row { display: flex; gap: 8px; margin-bottom: 4px; }

        .search-form { 
            flex: 1; display: flex; position: relative;
        }

        .search-form input { 
            width: 100%; padding: 12px; padding-right: 40px; 
            border: 1px solid #ccd1d9; border-radius: 6px; font-size: 16px; background-color: #f5f7fa;
        }

        .search-form input:focus { outline: none; border-color: #1a5f7a; background: #fff; }        

        .search-inside-btn {
            position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
            background: none; border: none; font-size: 18px; cursor: pointer; padding: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .search-inside-btn:active { opacity: 0.6; }

        .action-btn { 
            padding: 0 16px; background: #fff; border: 1px solid #ccd1d9; 
            border-radius: 6px; font-size: 16px; color: #434955; cursor: pointer; 
            display: flex; align-items: center; justify-content: center; text-decoration: none;
        }
        .action-btn:active { background: #e6edf2; }
        
        /* 大きくて丸いカテゴリーボタン */
        .category-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 20px; 
            padding: 30px 20px; 
            max-width: 400px; 
            margin: 0 auto; 
        }
        .category-btn { 
            width: 100%; aspect-ratio: 1; border-radius: 50%; 
            background: #1a5f7a; color: #fff; border: none; 
            font-size: 16px; font-weight: bold; cursor: pointer; 
            display: flex; align-items: center; justify-content: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.15); text-decoration: none; 
            transition: transform 0.1s;
        }
        .category-btn:active { 
            background: #10375c; transform: scale(0.96);
        }
        
        .section-heading { 
            font-size: 16px; font-weight: bold; color: #1a5f7a; 
            padding: 15px 16px 5px 16px; 
        }

        /* グリッド表示 */
        .grid-view {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding: 16px;
        }
        .grid-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .grid-card:active { background: #fafbfc; }
        .grid-img-box {
            width: 100%;
            aspect-ratio: 1;
            background: #eef2f7;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #64748b;
            margin-bottom: 6px;
            overflow: hidden;
        }
        .grid-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .grid-name { font-size: 12px; font-weight: bold; margin: 0 0 2px 0; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .grid-sub { font-size: 10px; color: #64748b; margin: 0 0 4px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .grid-stats { display: flex; gap: 6px; font-size: 9px; color: #64748b; justify-content: space-between; }

        /* リスト表示 */
        .list-view { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .list-card { 
            background: #fff; border-radius: 10px; padding: 12px; 
            border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
            display: flex; gap: 14px; align-items: center; text-decoration: none; color: inherit;
        }
        .list-card:active { background: #fafbfc; }
        
        .list-img-box { 
            width: 70px; height: 70px; background: #eef2f7; 
            border-radius: 6px; display: flex; align-items: center; 
            justify-content: center; font-size: 11px; color: #8c9ba5; flex-shrink: 0;
            overflow: hidden;
        }
        .list-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .list-info { flex: 1; min-width: 0; }
        .list-name { font-weight: bold; font-size: 16px; margin: 0 0 4px; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .list-sub { font-size: 13px; color: #4b5563; margin: 0 0 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .list-stats { font-size: 12px; color: #6b7280; display: flex; gap: 12px; }

        /* フッタータブメニュー */
        .footer-menu { 
            position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); 
            width: 100%; max-width: 480px; height: 65px; background: #1a5f7a; 
            display: flex; z-index: 100;
        }
        .footer-tab { 
            flex: 1; color: #fff; border: none; background: none; 
            font-size: 15px; font-weight: bold; cursor: pointer; 
            display: flex; align-items: center; justify-content: center; 
            text-decoration: none; border-right: 1px solid #154c63; 
        }
        .footer-tab:last-child { border-right: none; }
        .footer-tab:active { background: #10375c; }
        
        .back-link-box { text-align: center; margin: 20px 0; }
        .back-link { color: #1a5f7a; text-decoration: none; font-size: 15px; font-weight: 500; }
    </style>
</head>
<body>

<div class="smartphone-screen">
    <div class="header-area">
        <h1 class="top-title">菓子ペディア</h1>
        <div class="search-sort-row">
            <form action="index.php" method="GET" class="search-form">
                <input type="text" name="search" placeholder="検索" value="<?= h($search) ?>">
                <button type="submit" class="search-inside-btn">🔍</button>
            </form>
            <button class="action-btn">ソート</button>
            <a href="<?= build_view_url($view_mode, $search, $category) ?>" class="action-btn" title="表示切り替え">
                <?= ($view_mode === 'grid') ? '⊞' : '⊟' ?>
            </a>
            </div>
    </div>

    <?php if (!$is_search_mode): ?>
        <div class="category-grid">
            <a href="category.php?category=スナック" class="category-btn">スナック</a>
            <a href="category.php?category=チョコ" class="category-btn">チョコ</a>
            <a href="category.php?category=グミ" class="category-btn">グミ</a>
            <a href="category.php?category=あめ" class="category-btn">あめ</a>
            <a href="category.php?category=ガム" class="category-btn">ガム</a>
            <a href="category.php?category=タブレット" class="category-btn">タブレット</a>
            <a href="category.php?category=クッキー" class="category-btn">クッキー</a>
            <a href="category.php?category=アイス" class="category-btn">アイス</a>
            <a href="category.php?category=その他" class="category-btn">その他</a>
        </div>
    <?php endif; ?>

    <div class="section-heading">
        <?php 
        if ($is_search_mode) {
            echo '検索・絞り込み結果';
        } else {
            echo '新着のお菓子一覧';
        }
        ?>
    </div>

    <?php if (empty($kashi_list)): ?>
        <p style="text-align: center; color: #4b5563; padding: 40px 0;">該当するお菓子が見つかりません。</p>
    <?php else: ?>
        
        <?php if ($view_mode === 'grid'): ?>
            <div class="grid-view">
                <?php foreach ($kashi_list as $kashi): ?>
                    <a href="detail.php?id=<?= h($kashi['id']) ?>" class="grid-card">
                        <div class="grid-img-box">
                            <?php $thumb = getThumbnailUrl($pdo, $kashi['kashi_code']); ?>
                            <?php if ($thumb && file_exists($thumb)): ?>
                                <img src="<?= h($thumb) ?>" alt="<?= h($kashi['name']) ?>">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 10px; color: #64748b; text-align: center; margin-bottom: 2px;">
                            <?= h(date('Y.m.d', strtotime($kashi['created_at']))) ?>
                        </div>
                        <div class="grid-stats" style="justify-content: center; gap: 8px;">
                            <span>★ 4</span>
                            <span>🤍 250</span>
                            <span>👀 1.2K</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="list-view">
                <?php foreach ($kashi_list as $kashi): ?>
                    <a href="detail.php?id=<?= h($kashi['id']) ?>" class="list-card">
                        <div class="list-img-box">
                            <?php $thumb = getThumbnailUrl($pdo, $kashi['kashi_code']); ?>
                            <?php if ($thumb && file_exists($thumb)): ?>
                                <img src="<?= h($thumb) ?>" alt="<?= h($kashi['name']) ?>">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </div>
                        <div class="list-info">
                            <div style="font-size: 11px; color: #64748b; margin-bottom: 2px;">
                                更新: <?= h(date('Y.m.d', strtotime($kashi['created_at']))) ?>
                            </div>
                            <div class="list-stats">
                                <span>★ 4</span>
                                <span>🤍 250</span>
                                <span>👁️ 1.2K</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
    
    <?php if ($is_search_mode): ?>
        <div class="back-link-box">
            <a href="index.php" class="back-link">◀ カテゴリー一覧（トップ）に戻る</a>
        </div>
    <?php endif; ?>

    <div class="footer-menu">
        <a href="index.php" class="footer-tab" style="color: #fff; background: #1a5f7a; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">検索</a>
        <a href="mypage.php" class="footer-tab">MY page</a>
        <a href="add.php" class="footer-tab">登録</a>
    </div>
</div>

</body>
</html>