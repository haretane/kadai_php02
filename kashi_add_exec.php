<?php
// config.php（DB接続設定）を読み込む
require_once __DIR__ . '/config.php';

// 入力フォームからデータがPOSTで届いているかチェック
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('直接このページにアクセスすることはできません。');
}

// フォームから送られてきたテキストデータを取得・整理
$name          = isset($_POST['name'])          ? trim($_POST['name'])          : '';
$maker         = isset($_POST['maker'])         ? trim($_POST['maker'])         : '';
$comment       = isset($_POST['comment'])       ? trim($_POST['comment'])       : '';
$ingredients   = isset($_POST['ingredients'])   ? trim($_POST['ingredients'])   : '';
$capacity      = isset($_POST['capacity'])      ? trim($_POST['capacity'])      : '';
$energy_kcal   = isset($_POST['energy_kcal'])   ? trim($_POST['energy_kcal'])   : '';
$protein_g     = isset($_POST['protein_g'])     ? trim($_POST['protein_g'])     : '';
$fat_g         = isset($_POST['fat_g'])         ? trim($_POST['fat_g'])         : '';
$carbo_g       = isset($_POST['carbo_g'])       ? trim($_POST['carbo_g'])       : '';
$salt_g        = isset($_POST['salt_g'])        ? trim($_POST['salt_g'])        : '';
$allergen      = isset($_POST['allergen'])      ? trim($_POST['allergen'])      : '';

$parent_tag = isset($_POST['parent_tag']) ? trim($_POST['parent_tag']) : '';
$child_tag  = isset($_POST['child_tag'])  ? trim($_POST['child_tag'])  : '';

// 、の処理を半角スペースへ変換
$child_tag = str_replace(['、', ','], ' ', $child_tag);

// 商品名とメーカー名は必須チェック
if ($name === '' || $maker === '') {
    exit('商品名とメーカー名は必ず入力してください。');
}

// 大分類（ジャンル）が選択されていない場合のエラーチェック
if ($parent_tag === '') {
    exit('ジャンル（大分類）は必ず選択してください。');
}

// 入力された小分類（タグ）を辞書と照らし合わせて正規化する処理
function normalize_tag($input_tag) {
    $dictionary = [
        'ポテチ'   => ['ポテチ', 'ポテトチップス', 'ポテトチップ'],
        'チョコ'   => ['チョコ', 'チョコレート', 'チョコレイト'],
        'せんべい' => ['せんべい', 'せんべえ', '煎餅'],
        'あめ'     => ['あめ', '飴', 'キャンディ', 'キャンディー', 'キャンデー'],
        'グミ'     => ['グミ', 'ソフトキャンディ', 'ソフトキャンデー', 'ソフトキャンディー']
    ];

    foreach ($dictionary as $standard_tag => $variations) {
        foreach ($variations as $var) {
            if (mb_strpos($input_tag, $var) !== false) {
                return $standard_tag;
            }
        }
    }
    return 'その他';
}

// 大分類と小分類をスラッシュで連結して category_name を作成（URL階層用）
if ($child_tag !== '') {
    $normalized_sub = normalize_tag($child_tag);
    $category_name = $parent_tag . '/' . $normalized_sub;
    $child_tag = $normalized_sub; 
} else {
    $category_name = $parent_tag;
}

// kashi_code をシステム側で全自動生成
$kashi_code = 'kashi_' . date('YmdHis') . '_' . substr(uniqid(), -4);

try {
    // データベース接続
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // トランザクションを開始
    $pdo->beginTransaction();

    // 1. kashi_master へお菓子情報を登録
    $sql_master = "INSERT INTO kashi_master (
                kashi_code, name, maker, comment, category_name, 
                ingredients, capacity, energy_kcal, protein_g, 
                fat_g, carbo_g, salt_g, allergen, created_at, updated_at
            ) VALUES (
                :kashi_code, :name, :maker, :comment, :category_name, 
                :ingredients, :capacity, :energy_kcal, :protein_g, 
                :fat_g, :carbo_g, :salt_g, :allergen, NOW(), NOW()
            )";

    $stmt_master = $pdo->prepare($sql_master);

    $stmt_master->bindValue(':kashi_code',    $kashi_code,    PDO::PARAM_STR);
    $stmt_master->bindValue(':name',          $name,          PDO::PARAM_STR);
    $stmt_master->bindValue(':maker',         $maker,         PDO::PARAM_STR);
    $stmt_master->bindValue(':comment',       $comment,       PDO::PARAM_STR);
    $stmt_master->bindValue(':category_name', $category_name, PDO::PARAM_STR);
    $stmt_master->bindValue(':ingredients',   $ingredients,   PDO::PARAM_STR);
    $stmt_master->bindValue(':capacity',      $capacity,      PDO::PARAM_STR);
    
    if ($energy_kcal !== '') {
        $stmt_master->bindValue(':energy_kcal', (int)$energy_kcal, PDO::PARAM_INT);
    } else {
        $stmt_master->bindValue(':energy_kcal', null, PDO::PARAM_NULL);
    }

    $stmt_master->bindValue(':protein_g',     $protein_g,     PDO::PARAM_STR);
    $stmt_master->bindValue(':fat_g',         $fat_g,         PDO::PARAM_STR);
    $stmt_master->bindValue(':carbo_g',       $carbo_g,       PDO::PARAM_STR);
    $stmt_master->bindValue(':salt_g',        $salt_g,        PDO::PARAM_STR);
    $stmt_master->bindValue(':allergen',      $allergen,      PDO::PARAM_STR);

    $stmt_master->execute();

    // 2. タグを分解して tags_master と kashi_tag_relations に登録
    $tags_to_register = [$parent_tag];
    if ($child_tag !== '') {
        $sub_tags = preg_split('/\s+/', trim($child_tag));
        foreach ($sub_tags as $sub_tag) {
            if ($sub_tag !== '') {
                $tags_to_register[] = $sub_tag;
            }
        }
    }

    foreach ($tags_to_register as $tag_name) {
        // 既にタグマスターに登録されているか確認
        $check_tag_sql = "SELECT id FROM tags_master WHERE tag_name = :tag_name";
        $check_stmt = $pdo->prepare($check_tag_sql);
        $check_stmt->bindValue(':tag_name', $tag_name, PDO::PARAM_STR);
        $check_stmt->execute();
        $tag_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($tag_row) {
            $tag_id = $tag_row['id'];
        } else {
            $insert_tag_sql = "INSERT INTO tags_master (tag_name) VALUES (:tag_name)";
            $insert_stmt = $pdo->prepare($insert_tag_sql);
            $insert_stmt->bindValue(':tag_name', $tag_name, PDO::PARAM_STR);
            $insert_stmt->execute();
            $tag_id = $pdo->lastInsertId();
        }

        // 中間テーブル（kashi_tag_relations）へ紐付け登録
        $relation_sql = "INSERT INTO kashi_tag_relations (kashi_code, tag_id) VALUES (:kashi_code, :tag_id)";
        $relation_stmt = $pdo->prepare($relation_sql);
        $relation_stmt->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
        $relation_stmt->bindValue(':tag_id',     $tag_id,     PDO::PARAM_INT);
        $relation_stmt->execute();
    }


    
    // 3. 画像ファイルのアップロード処理（ファイルが選択され、エラーが無い場合のみ実行）
    if (isset($_FILES['kashi_image']) && $_FILES['kashi_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['kashi_image']['tmp_name'];
        $fileName = $_FILES['kashi_image']['name'];
        
        // 保存先の物理ディレクトリパスを構築
        $uploadFileDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $dest_path = $uploadFileDir . $newFileName;

        // テンポラリファイルをuploadsディレクトリへ移動
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $image_path = 'uploads/' . $newFileName;

            $sql_image = "INSERT INTO kashi_images (kashi_code, version, user_id, image_url, is_main, created_at) 
                          VALUES (:kashi_code, 1, 0, :image_url, 1, NOW())";
            
            $stmt_image = $pdo->prepare($sql_image);
            $stmt_image->bindValue(':kashi_code', $kashi_code, PDO::PARAM_STR);
            $stmt_image->bindValue(':image_url',  $image_path,  PDO::PARAM_STR);
            
            // ★DBへのインサート実行処理（これが抜けていたため追加）
            $stmt_image->execute();
        }
    }

    // トランザクションを確定
    $pdo->commit();

    // トップページへ自動で戻す
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exit('データベース登録エラー: ' . $e->getMessage());
}