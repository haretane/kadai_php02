<?php
// config.phpを安全に読み込む
require_once __DIR__ . '/config.php';

// POSTメソッド以外からのアクセスは弾く
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// データの受け取り
$kashi_code = isset($_POST['kashi_code']) ? trim($_POST['kashi_code']) : '';
$user_id    = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
$evaluation = isset($_POST['evaluation']) ? trim($_POST['evaluation']) : '';

// 最低限のバリデーションチェック
if ($kashi_code === '' || $user_id === '' || $evaluation === '') {
    exit('不正なリクエストです（必須パラメータが不足しています）。');
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // データベースへログを記録（INSERT）
    // action_type は評価であることを示す 'star' とし、action_value（既存の evaluation カラム）に評価内容を保存します
    $sql = "
        INSERT INTO user_action_log (user_id, kashi_code, action_type, evaluation) 
        VALUES (:user_id, :kashi_code, 'star', :evaluation)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
    $stmt->bindValue(':evaluation', $evaluation, PDO::PARAM_STR);
    $stmt->execute();

    // 処理完了後、元の詳細ページへリダイレクト（パラメータとしてお菓子のidを渡すため、コードからidを引くか、もしくはidをPOSTで渡すように調整してください）
    // ※今回はkashi_codeから該当レコードのidを取得してリダイレクトさせます
    $id_sql = "SELECT id FROM kashi_master WHERE kashi_code = :kashi_code LIMIT 1";
    $id_stmt = $pdo->prepare($id_sql);
    $id_stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
    $id_stmt->execute();
    $kashi_row = $id_stmt->fetch();
    
    $redirect_id = $kashi_row ? $kashi_row['id'] : 1;

    // リダイレクト時にアクションが成功したことを伝えるフラグを挟んでも良いでしょう
    header('Location: detail.php?id=' . $redirect_id . '&evaluated=1');
    exit;

} catch (PDOException $e) {
    exit('データベースエラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}