<?php
// config.phpを安全に読み込む
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // 大分類（ジャンル）の取得（例: category.php?category=スナック）
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    // 表示切り替えパラメータの取得（デフォルトはグリッド表示 'grid'）
    $view_mode = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'grid';

    if ($category === '') {
        exit('カテゴリーが指定されていません。');
    }

    // URLスラッシュからベースジャンル（大分類）と検索対象（中分類）を確定する
    $base_genre = $category;
    $search_target = $category;
    
    if (strpos($category, '/') !== false) {
        $parts = explode('/', $category);
        $base_genre = $parts[0];       // スラッシュより前（例: "スナック"）
        $search_target = end($parts);  // スラッシュより後ろ（例: "ポテチ"）
    }

    // 抽出すべきタグ名を使って該当するお菓子を全て取得する
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
        
        // 念のため重複レコードを排除（名寄せ）する
        $unique_list = [];
        $seen_codes = [];
        foreach ($kashi_list as $item) {
            if (!in_array($item['kashi_code'], $seen_codes, true)) {
                $seen_codes[] = $item['kashi_code'];
                $unique_list[] = $item;
            }
        }
        $kashi_list = $unique_list;
    } else {
        $kashi_list = [];
    }

    // 各お菓子に紐づくメイン画像URLを取得するヘルパー関数
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

// 遷移用URLを構築するヘルパー関数
function build_category_url($base_genre, $sub_tag) {
    return 'category.php?category=' . urlencode($base_genre . '/' . $sub_tag);
}

// 表示モード切替用のヘルパー関数
function build_category_view_url($view_mode, $category) {
    $params = [
        'category' => $category,
        'view' => ($view_mode === 'grid') ? 'list' : 'grid'
    ];
    return 'category.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>カテゴリー絞り込み - 菓子ペディア</title>
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
        
        .search-sort-row { display: flex; gap: 8px; margin-top: 12px; }

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
        
        /* ▼▼▼ 戻るボタンと左寄せジャンル名表示帯のコンテナ ▼▼▼ */
        .genre-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 16px 16px 4px 16px;
        }

        /* 「戻る」文字表記ボタン（角丸四角形、文字が見切れないよう横幅を可変・パディング確保） */
        .btn-back { 
            height: 44px;
            padding: 0 16px;
            border-radius: 8px;
            background: #1a5f7a; 
            color: #fff;      
            border: none; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            flex-shrink: 0;
            text-decoration: none;
        }
        .btn-back:active { background: #10375c; }

        /* 選択中の大分類（ジャンル）表示帯：左寄せ＆パディング設定 */
        .selected-genre-bar {
            display: flex;
            align-items: center;
            justify-content: flex-start; /* 左寄せに変更 */
            background: #1a5f7a;
            color: #fff;
            padding: 0 20px; /* パディングを左右にしっかり確保 */
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex-grow: 1;
            height: 44px;
        }
        /* ▲▲▲ ▲▲▲ ▲▲▲ */

        /* 中分類の丸いボタン群 */
        .sub-category-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 16px; 
            padding: 20px 16px; 
            max-width: 400px; 
            margin: 0 auto; 
        }
        .sub-category-btn { 
            width: 100%; aspect-ratio: 1; border-radius: 50%; 
            background: #1a5f7a; color: #fff; border: none; 
            font-size: 14px; font-weight: bold; cursor: pointer; 
            display: flex; align-items: center; justify-content: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.15); text-decoration: none; 
            transition: transform 0.1s; text-align: center; padding: 4px;
        }
        .sub-category-btn:active { 
            background: #10375c; transform: scale(0.96);
        }
        
        .section-heading { 
            font-size: 16px; font-weight: bold; color: #1a5f7a; 
            padding: 15px 16px 5px 16px; border-top: 2px solid #e1e8ed; margin-top: 10px;
        }

        /* Grid view & List view ... */
        .grid-view { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 16px; }
        .grid-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px; text-decoration: none; color: inherit; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .grid-card:active { background: #fafbfc; }
        .grid-img-box { width: 100%; aspect-ratio: 1; background: #eef2f7; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #64748b; margin-bottom: 6px; overflow: hidden; }
        .grid-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .grid-stats { display: flex; gap: 6px; font-size: 9px; color: #64748b; justify-content: space-between; }

        .list-view { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .list-card { background: #fff; border-radius: 10px; padding: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; gap: 14px; align-items: center; text-decoration: none; color: inherit; }
        .list-card:active { background: #fafbfc; }
        .list-img-box { width: 70px; height: 70px; background: #eef2f7; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #8c9ba5; flex-shrink: 0; overflow: hidden; }
        .list-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .list-info { flex: 1; min-width: 0; }
        .list-stats { font-size: 12px; color: #6b7280; display: flex; gap: 12px; }

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
                <input type="text" name="search" placeholder="検索" value="">
                <button type="submit" class="search-inside-btn">🔍</button>
            </form>
            <button class="action-btn">ソート</button>
            <a href="<?= build_category_view_url($view_mode, $category) ?>" class="action-btn" title="表示切り替え">
                <?= ($view_mode === 'grid') ? '⊞' : '⊟' ?>
            </a>
        </div>
    </div>

    <div class="genre-container">
        <button type="button" class="btn-back" onclick="history.back();">戻る</button>
        <div class="selected-genre-bar">
            ▼ <?= h($base_genre) ?>
        </div>
    </div>
    <?php
    // 大項目によって小項目（丸タブ）を出し分ける制御
    if ($base_genre === '«スナック»' || $base_genre === 'スナック'): 
    ?>
        <div class="sub-category-grid">
            <a href="<?= build_category_url($base_genre, 'ポテチ') ?>" class="sub-category-btn">ポテチ</a>
            <a href="<?= build_category_url($base_genre, '袋菓子') ?>" class="sub-category-btn">袋菓子</a>
            <a href="<?= build_category_url($base_genre, 'せんべえ') ?>" class="sub-category-btn">せんべえ</a>
            <a href="<?= build_category_url($base_genre, 'その他') ?>" class="sub-category-btn">その他</a>
        </div>
    <?php endif; ?>

    <div class="section-heading">
        該当するお菓子一覧
    </div>

    <?php if (empty($kashi_list)): ?>
        <p style="text-align: center; color: #4b5563; padding: 40px 0;">このカテゴリーにはまだお菓子が登録されていません。</p>
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
    
    <div class="back-link-box">
        <a href="index.php" class="back-link">◀ 検索結果をリセット（トップに戻る）</a>
    </div>

    <div class="footer-menu">
        <a href="index.php" class="footer-tab" style="color: #fff; background: #1a5f7a; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">検索</a>
        <a href="mypage.php" class="footer-tab">MY page</a>
        <a href="add.php" class="footer-tab">登録</a>
    </div>
</div>

</body>
</html>