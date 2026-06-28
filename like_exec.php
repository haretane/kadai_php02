<?php
// config.phpを安全に読み込む
require_once __DIR__ . '/config.php';

// POSTメソッド以外からのアクセスはindex.phpへ弾く
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// データの受け取り（お菓子コードとアクション種別）
$kashi_code = isset($_POST['kashi_code']) ? trim($_POST['kashi_code']) : '';
$action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';

// ★ハードコーディング：テスト用の固定ユーザーID（仮でID:1を指定）
$user_id = 1;

// 最低限のバリデーションチェック
if ($kashi_code === '' || ($action_type !== 'heart' && $action_type !== 'look')) {
    exit('不正なリクエストです。');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // データベースへログを記録（INSERT）
    // アクション種別（heart または look）を記録し、評価値は空欄（NULL）にします
    $sql = "
        INSERT INTO user_action_log (user_id, kashi_code, action_type, evaluation) 
        VALUES (:user_id, :kashi_code, :action_type, NULL)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
    $stmt->bindValue(':action_type', $action_type, PDO::PARAM_STR);
    $stmt->execute();

    // お菓子コード（kashi_code）から該当レコードのidを引き当てて詳細ページへリダイレクト
    $id_sql = "SELECT id FROM kashi_master WHERE kashi_code = :kashi_code LIMIT 1";
    $id_stmt = $pdo->prepare($id_sql);
    $id_stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
    $id_stmt->execute();
    $kashi_row = $id_stmt->fetch();
    
    $redirect_id = $kashi_row ? $kashi_row['id'] : 1;

    // 処理完了後、元の詳細ページへリダイレクト
    header('Location: detail.php?id=' . $redirect_id);
    exit;

} catch (PDOException $e) {
    exit('データベースエラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}