<?php
// config.phpを安全に読み込む
require_once __DIR__ . '/config.php';

$kashi = null;
$image_url = 'images/no_image.png'; // ダミー画像のデフォルトパス（必要に応じてフォルダ構成に合わせてください）

// アクション済み判定用の変数（デフォルト未選択）
$is_evaluated = false;
$eval_value = '';
$is_liked = false;
$is_looked = false;

// ★ハードコーディング：テスト用の固定ユーザーID（仮でID:1を指定）
$test_user_id = 1;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // GETパラメータからお菓子のidを取得
    $id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) : null;

    if ($id) {
        // 1. お菓子詳細情報の取得
        $sql = "SELECT * FROM kashi_master WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $kashi = $stmt->fetch();

        // 2. 紐づくメイン画像の取得・アクション状況の判定
        if ($kashi) {
            $kashi_code = $kashi['kashi_code'];

            $image_sql = "SELECT image_url FROM kashi_images WHERE kashi_code = :kashi_code AND is_main = 1 LIMIT 1";
            $image_stmt = $pdo->prepare($image_sql);
            $image_stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
            $image_stmt->execute();
            $img_row = $image_stmt->fetch();
            
            if ($img_row && !empty($img_row['image_url']) && file_exists($img_row['image_url'])) {
                $image_url = $img_row['image_url'];
            }

            // アクションログを読み込み、すでにボタンが押されているか判定する
            $log_sql = "SELECT action_type, evaluation FROM user_action_log WHERE user_id = :user_id AND kashi_code = :kashi_code";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->bindValue(':user_id', $test_user_id, PDO::PARAM_INT);
            $log_stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
            $log_stmt->execute();
            $logs = $log_stmt->fetchAll();

            foreach ($logs as $log) {
                if ($log['action_type'] === 'star') {
                    $is_evaluated = true;
                    $eval_value = $log['evaluation'];
                } elseif ($log['action_type'] === 'heart') {
                    $is_liked = true;
                } elseif ($log['action_type'] === 'look') {
                    $is_looked = true;
                }
            }
        }
    }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $kashi ? h($kashi['name']) : '詳細情報' ?> - 菓子ペディア</title>
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
        .top-title { text-align: center; font-size: 22px; color: #333; margin: 0; font-weight: bold; }
        
        /* 詳細画像エリア */
        .detail-img-area { width: 100%; aspect-ratio: 4/3; background: #f8fafc; position: relative; overflow: hidden; }
        .detail-img { width: 100%; height: 100%; object-fit: contain; }
        
        .content-body { padding: 16px; }
        .kashi-name { font-size: 20px; font-weight: bold; color: #10375c; margin-bottom: 4px; }
        .kashi-maker { font-size: 14px; color: #5c6b73; margin-bottom: 12px; }

        /* 画像下：枠線・背景なしの控えめなテキストカウンター */
        .stats-minimal-row { 
            display: flex; 
            justify-content: flex-start; 
            align-items: center; 
            gap: 20px; 
            font-size: 13px; 
            color: #475569; 
            margin-bottom: 20px; 
            padding-bottom: 12px; 
            border-bottom: 1px solid #e2e8f0; 
        }
        .stats-minimal-item { display: flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 4px; border: 1px solid transparent; }
        
        /* アクション済みの場合は枠線ハイライトを点灯させる */
        .stats-minimal-item.active { border-color: #1a5f7a; background: #f0f4f8; font-weight: bold; }

        /* ユーザー操作アクションボタン：丸い紺ベース白抜きのボタン群 */
        .user-action-circle-row { 
            display: flex; 
            justify-content: space-around; 
            align-items: center; 
            margin-bottom: 24px; 
            padding: 8px 0; 
        }
        .btn-action-circle { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            background: #1a5f7a; /* 紺ベース */
            color: #ffffff;      /* 白文字 */
            border: none; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            text-decoration: none;
            cursor: pointer;
            position: relative;
        }
        .btn-action-circle:active { background: #10375c; }
        
        /* ▼▼▼ 押下済みの場合：サイズはそのままで内側に白の二重丸線を描く ▼▼▼ */
        .btn-action-circle.checked { 
            background: #1a5f7a; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-action-circle.checked::after {
            content: "";
            position: absolute;
            top: 4px; left: 4px; width: 72px; height: 72px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-sizing: border-box;
            pointer-events: none;
        }
        /* ▲▲▲ ▲▲▲ ▲▲▲ */

        .circle-icon-txt { font-size: 16px; margin-bottom: 2px; }
        .circle-label { font-size: 12px; font-weight: bold; }

        /* 評価選択用ポップアップ（モーダル）のスタイル */
        .modal-overlay {
            display: none; 
            position: fixed; 
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #fff;
            width: 90%;
            max-width: 320px;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            text-align: center;
        }
        .modal-title { font-size: 16px; font-weight: bold; color: #10375c; margin-bottom: 16px; }
        .eval-options-grid { display: flex; flex-direction: column; gap: 10px; }
        .btn-eval-option {
            background: #f0f4f8; border: 1px solid #d1d5db; border-radius: 8px;
            padding: 14px; font-size: 15px; font-weight: bold; color: #10375c;
            text-align: center; text-decoration: none; cursor: pointer;
        }
        .btn-eval-option:active { background: #e2e8f0; }
        .modal-close-btn { margin-top: 16px; font-size: 13px; color: #64748b; background: none; border: none; cursor: pointer; text-decoration: underline; }

        /* 処理完了通知用ポップアップのスタイル */
        .completion-overlay {
            display: none; 
            position: fixed; 
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .completion-content {
            background: #fff; width: 90%; max-width: 280px; border-radius: 12px;
            padding: 24px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); text-align: center;
        }
        .completion-icon { font-size: 40px; margin-bottom: 12px; }
        .completion-msg { font-size: 16px; font-weight: bold; color: #10375c; margin-bottom: 20px; }
        .btn-completion-close {
            background: #1a5f7a; color: #fff; border: none; border-radius: 8px;
            padding: 12px; width: 100%; font-size: 15px; font-weight: bold; cursor: pointer;
        }
        .btn-completion-close:active { background: #10375c; }

        /* 閲覧系ボタンエリア */
        .view-buttons-container { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
        .btn-view { 
            background: #1a5f7a; color: #fff; padding: 14px 8px; border-radius: 8px; 
            text-align: center; font-weight: bold; font-size: 15px; text-decoration: none; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .btn-view:active { background: #10375c; }

        /* アクション・更新系エリア */
        .action-buttons-container { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
        
        /* 再評価ボタン（白ベースに紺文字＋枠線） */
        .btn-re-evaluate { 
            background: #ffffff; color: #1a5f7a; padding: 16px; border-radius: 8px; 
            text-align: center; font-weight: bold; font-size: 16px; text-decoration: none; 
            border: 2px solid #1a5f7a; box-shadow: 0 2px 4px rgba(0,0,0,0.05); cursor: pointer;
        }
        .btn-re-evaluate:active { background: #f0f4f8; }

        /* 更新編集ボタン（ウィキペディア風の投稿・追記機能） */
        .btn-edit { 
            background: #475569; color: #fff; padding: 14px; border-radius: 8px; 
            text-align: center; font-weight: bold; font-size: 15px; text-decoration: none; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: none; cursor: pointer;
        }
        .btn-edit:active { background: #334155; }

        /* 栄養成分表示 */
        .nutritional-heading { font-size: 16px; font-weight: bold; color: #10375c; border-bottom: 2px solid #10375c; padding-bottom: 4px; margin-bottom: 12px; }
        .nutritional-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 8px; }
        .nutritional-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; text-align: center; }
        .nutritional-label { font-size: 11px; color: #64748b; display: block; margin-bottom: 4px; }
        .nutritional-value { font-size: 16px; font-weight: bold; color: #1e293b; }

        /* 感想・インプレッション */
        .comment-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-top: 20px; }
        .comment-heading { font-size: 14px; font-weight: bold; color: #1a5f7a; margin-bottom: 8px; }

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
        
        .back-link-box { text-align: center; margin: 30px 0 20px 0; }
        .back-link { color: #1a5f7a; text-decoration: none; font-size: 15px; font-weight: 500; }
    </style>
</head>
<body>

<div class="smartphone-screen">
    <div class="header-area">
        <h1 class="top-title">菓子ペディア</h1>
    </div>

    <?php if (!$kashi): ?>
        <p style="text-align: center; padding: 40px 0;">お菓子が見つかりませんでした。</p>
    <?php else: ?>

        <div class="detail-img-area">
            <img src="<?= h($image_url) ?>" alt="<?= h($kashi['name']) ?>" class="detail-img">
        </div>

        <div class="content-body">
            <div class="kashi-name"><?= h($kashi['name']) ?></div>
            <div class="kashi-maker"><?= h($kashi['maker']) ?></div>

            <div class="stats-minimal-row">
                <div class="stats-minimal-item <?= $is_evaluated ? 'active' : '' ?>">★ <span>4.0</span></div>
                <div class="stats-minimal-item <?= $is_liked ? 'active' : '' ?>">🤍 <span>250</span></div>
                <div class="stats-minimal-item <?= $is_looked ? 'active' : '' ?>">👀 <span>1.2K</span></div>
            </div>

            <div class="user-action-circle-row">
                <button type="button" class="btn-action-circle <?= $is_evaluated ? 'checked' : '' ?>" onclick="openEvalModal()">
                    <span class="circle-icon-txt">★</span>
                    <span class="circle-label">評価する</span>
                </button>
                
                <form action="like_exec.php" method="POST" style="margin:0;">
                    <input type="hidden" name="kashi_code" value="<?= h($kashi['kashi_code']) ?>">
                    <input type="hidden" name="action_type" value="heart">
                    <button type="submit" class="btn-action-circle <?= $is_liked ? 'checked' : '' ?>" style="text-decoration:none; cursor:pointer;">
                        <span class="circle-icon-txt">🤍</span>
                        <span class="circle-label">いいね</span>
                    </button>
                </form>

                <form action="like_exec.php" method="POST" style="margin:0;">
                    <input type="hidden" name="kashi_code" value="<?= h($kashi['kashi_code']) ?>">
                    <input type="hidden" name="action_type" value="look">
                    <button type="submit" class="btn-action-circle <?= $is_looked ? 'checked' : '' ?>" style="text-decoration:none; cursor:pointer;">
                        <span class="circle-icon-txt">👀</span>
                        <span class="circle-label">ルック</span>
                    </button>
                </form>
            </div>

            <div class="view-buttons-container">
                <a href="history.php?code=<?= h($kashi['kashi_code']) ?>" class="btn-view">歴史</a>
                <a href="graph.php?code=<?= h($kashi['kashi_code']) ?>" class="btn-view">推移グラフ</a>
            </div>

            <div class="action-buttons-container">
                <a href="re_evaluate.php?id=<?= h($kashi['id']) ?>" class="btn-re-evaluate">✏️ このお菓子を再評価する</a>
                <a href="edit.php?id=<?= h($kashi['id']) ?>" class="btn-edit">📝 未入力項目を更新・編集する</a>
            </div>

            <?php if ($kashi['calorie_kcal'] !== '' || $kashi['protein_g'] !== '' || $kashi['fat_g'] !== '' || $kashi['carbo_g'] !== ''): ?>
                <div style="margin-bottom: 24px;">
                    <div class="nutritional-heading">栄養成分表示</div>
                    <div class="nutritional-grid">
                        <div class="nutritional-item">
                            <span class="nutritional-label">カロリー</span>
                            <span class="nutritional-value"><?= $kashi['calorie_kcal'] !== '' ? h($kashi['calorie_kcal']) . '<span style="font-size:10px;">kcal</span>' : '-' ?></span>
                        </div>
                        <div class="nutritional-item">
                            <span class="nutritional-label">たんぱく質</span>
                            <span class="nutritional-value"><?= $kashi['protein_g'] !== '' ? h($kashi['protein_g']) . '<span style="font-size:10px;">g</span>' : '-' ?></span>
                        </div>
                        <div class="nutritional-item">
                            <span class="nutritional-label">脂質</span>
                            <span class="nutritional-value"><?= $kashi['fat_g'] !== '' ? h($kashi['fat_g']) . '<span style="font-size:10px;">g</span>' : '-' ?></span>
                        </div>
                    </div>
                    <div class="nutritional-grid">
                        <div class="nutritional-item" style="grid-column: span 3;">
                            <span class="nutritional-label">炭水化物</span>
                            <span class="nutritional-value"><?= $kashi['carbo_g'] !== '' ? h($kashi['carbo_g']) . '<span style="font-size:10px;">g</span>' : '-' ?></span>
                        </div>
                    </div>
                    <?php if ($kashi['salt_g'] !== ''): ?>
                        <div style="margin-top: 8px; text-align: right; font-size: 12px; color: #374151;">
                            食塩相当量: <strong><?= h($kashi['salt_g']) ?>g</strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($kashi['comment'] !== ''): ?>
                <div class="comment-section">
                    <div class="comment-heading">インプレッション・感想</div>
                    <div><?= nl2br(h($kashi['comment'])) ?></div>
                </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>
    
    <div class="back-link-box">
        <a href="index.php" class="back-link">◀ トップへ戻る</a>
    </div>

    <div class="footer-menu">
        <a href="index.php" class="footer-tab" style="color: #fff; background: #1a5f7a; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">検索</a>
        <a href="mypage.php" class="footer-tab">MY page</a>
        <a href="add.php" class="footer-tab">登録</a>
    </div>
</div>

<div id="evalModal" class="modal-overlay" onclick="closeEvalModal(event)">
    <div class="modal-content" onclick="event.stopPropagation();">
        <div class="modal-title">どのくらいおいしい？</div>
        <form action="evaluate_exec.php" method="POST" class="eval-options-grid">
            <input type="hidden" name="kashi_code" value="<?= h($kashi['kashi_code'] ?? '') ?>">
            <input type="hidden" name="user_id" value="<?= $test_user_id ?>"> 
            
            <button type="submit" name="evaluation" value="最高" class="btn-eval-option">👑 最高</button>
            <button type="submit" name="evaluation" value="うまい" class="btn-eval-option">😋 うまい</button>
            <button type="submit" name="evaluation" value="まあまあ" class="btn-eval-option">🙂 まあ就是（まあまあ）</button>
            <button type="submit" name="evaluation" value="そうでもない" class="btn-eval-option">🤫 そうでもない</button>
        </form>
        <button type="button" class="modal-close-btn" onclick="closeEvalModal()">キャンセル</button>
    </div>
</div>

<div id="completionModal" class="completion-overlay" style="display: <?= (isset($_GET['evaluated']) && $_GET['evaluated'] === '1') ? 'flex' : 'none' ?>;">
    <div class="completion-content">
        <div class="completion-icon">🎉</div>
        <div class="completion-msg">
            評価しました<br>
            <span style="color: #e0aa3e; font-size: 18px;">
                <?= isset($_GET['val']) ? h(urldecode($_GET['val'])) : '' ?>
            </span>
        </div>
        <button type="button" class="btn-completion-close" onclick="closeCompletionModal(<?= h($id ?? 1) ?>)">× 閉じる</button>
    </div>
</div>

<script>
    function openEvalModal() {
        document.getElementById('evalModal').style.display = 'flex';
    }

    function closeEvalModal(event) {
        if (!event || event.target.id === 'evalModal' || event.target.classList.contains('modal-close-btn')) {
            document.getElementById('evalModal').style.display = 'none';
        }
    }

    function closeCompletionModal(kashiId) {
        document.getElementById('completionModal').style.display = 'none';
        // パラメータなしの詳細画面へ戻る
        location.href = 'detail.php?id=' + kashiId;
    }
</script>

</body>
</html>