<?php
// config.phpを安全に読み込む（必要に応じてパスを調整）
require_once __DIR__ . '/config.php';

// 仮のユーザー名（変更機能のベース）
$username = "お菓子大好きユーザー";

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MY page - 菓子ペディア</title>
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

        /* 開発中バナー */
        .under-construction {
            background-color: #f59e0b;
            color: #ffffff;
            text-align: center;
            padding: 10px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .header-area { background: #fff; padding: 20px 16px; border-bottom: 1px solid #e1e8ed; }
        .page-title { font-size: 22px; color: #333; margin: 0; font-weight: bold; text-align: center; }

        /* ユーザープロフィール */
        .profile-box { display: flex; align-items: center; gap: 16px; padding: 20px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .avatar { width: 60px; height: 60px; border-radius: 50%; background: #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .user-info { flex: 1; }
        .username { font-size: 16px; font-weight: bold; color: #1e293b; margin-bottom: 4px; }
        .edit-name-btn { font-size: 12px; color: #1a5f7a; text-decoration: none; font-weight: 500; }

        /* タブメニュー（ダミー） */
        .mypage-tabs { display: flex; border-bottom: 1px solid #e2e8f0; background: #fff; }
        .tab-item { flex: 1; padding: 16px 0; text-align: center; font-size: 14px; font-weight: bold; color: #64748b; text-decoration: none; border-bottom: 2px solid transparent; }
        .tab-item.active { color: #1a5f7a; border-bottom-color: #1a5f7a; }

        /* コンテンツエリア（開発中メッセージ） */
        .dev-content { padding: 40px 20px; text-align: center; color: #475569; }
        .dev-icon { font-size: 48px; margin-bottom: 16px; }
        .dev-text { font-size: 15px; line-height: 1.6; margin-bottom: 20px; }

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
        .footer-tab:nth-child(2) { background: #13465a; } /* MY pageをアクティブ風にハイライト */
        .footer-tab:last-child { border-right: none; }
        .footer-tab:active { background: #10375c; }
    </style>
</head>
<body>

<div class="smartphone-screen">
    
    <div class="under-construction">⚡ 現在開発中・実装準備中の機能です ⚡</div>

    <div class="header-area">
        <h1 class="page-title">MY page</h1>
    </div>

    <div class="profile-box">
        <div class="avatar">👤</div>
        <div class="user-info">
            <div class="username"><?= h($username) ?></div>
            <a href="#" class="edit-name-btn">✎ ユーザーネームを変更（※ダミー）</a>
        </div>
    </div>

    <div class="mypage-tabs">
        <a href="#" class="tab-item active">評価したお菓子</a>
        <a href="#" class="tab-item">いいね</a>
        <a href="#" class="tab-item">ルック</a>
    </div>

    <div class="dev-content">
        <div class="dev-icon">🛠️</div>
        <div class="dev-text">
            ここに「自分が評価したお菓子」「いいねした一覧」「ルック（見たよ）した履歴」が一覧で表示される予定です。
        </div>
        <div class="dev-text" style="font-size: 13px; color: #94a3b8;">
            ※将来的には、他ユーザーの好みが見られるタイムラインや、「これめっちゃうまい！」等のX（旧Twitter）のような一言コメント・つぶやき機能も追加統合される予定です。
        </div>
        <a href="index.php" style="display: inline-block; background: #1a5f7a; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px;">トップページに戻る</a>
    </div>

    <div class="footer-menu">
        <a href="index.php" class="footer-tab">検索</a>
        <a href="mypage.php" class="footer-tab" style="color: #fff;">MY page</a>
        <a href="add.php" class="footer-tab">登録</a>
    </div>

</div>

</body>
</html>