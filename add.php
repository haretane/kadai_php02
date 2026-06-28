<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>お菓子登録 - 菓子ペディア</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background: #f0f4f8; 
        }

        .smartphone-screen {
            max-width: 480px;
            min-height: 100vh;
            margin: 0 auto;
            background: #fff;
            position: relative;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding-bottom: 60px;
        }

        h2 { text-align: center; color: #1a5f7a; margin-bottom: 25px; font-size: 22px; font-weight: bold; }

        .form-group { margin-bottom: 18px; }

        .form-group label { 
            display: block; 
            font-weight: bold; 
            margin-bottom: 6px; 
            color: #333; 
            font-size: 14px; 
        }

        .form-group input, 
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd1d9;
            border-radius: 6px;
            font-size: 15px;
            background-color: #f5f7fa;
        }

        .form-group input:focus, 
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a5f7a;
            background: #fff;
        }

        /* アコーディオン（折りたたみ）の詳細入力エリア */
        .accordion-header {
            background: #eef2f7;
            padding: 14px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            color: #1a5f7a;
            font-size: 15px;
            margin-bottom: 18px;
            border: 1px solid #dbe2ea;
        }

        .accordion-content {
            display: none; /* 最初はたたまれている状態 */
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .sub-form-group { margin-bottom: 12px; }
        .sub-form-group label { font-size: 13px; color: #4b5563; display: block; margin-bottom: 4px; font-weight: 500; }
        .sub-form-group input { padding: 10px; font-size: 14px; background: #fff; }

        /* 栄養成分を横並びにするレイアウト（スマホ幅に合わせて2列×2に調整） */
        .nutrition-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 5px; }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #1a5f7a;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-submit:active { background: #10375c; }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #1a5f7a;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="smartphone-screen">
    <h2>お菓子を追加</h2>
    
    <form action="kashi_add_exec.php" method="POST" enctype="multipart/form-data">
        
        <div class="form-group">
            <label>商品名</label>
            <input type="text" name="name" placeholder="例: ポテトチップス うすしお味" required>
        </div>

        <div class="form-group">
            <label>メーカー名</label>
            <input type="text" name="maker" placeholder="例: カルビー" required>
        </div>

        <div class="form-group">
            <label>ジャンル（大分類）</label>
            <select name="parent_tag" required>
                <option value="" disabled selected>ジャンルを選択してください</option>
                <option value="スナック">スナック</option>
                <option value="チョコ">チョコ</option>
                <option value="グミ">グミ</option>
                <option value="あめ">あめ</option>
                <option value="ガム">ガム</option>
                <option value="タブレット">タブレット</option>
                <option value="クッキー">クッキー</option>
                <option value="アイス">アイス</option>
                <option value="その他">その他</option>
            </select>
        </div>

        <div class="form-group">
            <label>詳細タグ（小分類）※未入力OK</label>
            <input type="text" name="child_tag" placeholder="例: ポテチ、チョコなど">
            <div style="font-size: 11px; color: #666; margin-top: 4px;">※スペース区切りで複数指定可。未入力でも登録できます。</div>
        </div>

        <div class="form-group">
            <label>パッケージ画像（任意）</label>
            <input type="file" name="kashi_image" accept="image/*" style="background: #fff; padding: 10px;">
            <div style="font-size: 11px; color: #666; margin-top: 4px;">※当時のパッケージ画像などをアップロードできます（未選択でも登録可）。</div>
        </div>

        <div class="form-group">
            <label>解説・コメント</label>
            <textarea name="comment" rows="3" placeholder="商品の特徴や、パウダーの濃さ、食感などを入力できます"></textarea>
        </div>

        <div class="accordion-header" onclick="toggleAccordion()">
            <span>詳細を入力（任意）</span>
            <span id="arrow-icon">▶︎</span>
        </div>

        <div class="accordion-content" id="detail-content">
            
            <div class="form-group">
                <label>原材料名</label>
                <textarea name="ingredients" rows="3" placeholder="例: じゃがいも(遺伝子組換えでない)、植物油、食塩..."></textarea>
            </div>

            <div class="form-group">
                <label>内容量</label>
                <input type="text" name="capacity" placeholder="例: 60g">
            </div>

            <div class="form-group" style="border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 15px;">
                <label style="color: #1a5f7a;">栄養成分表示</label>
                <div style="font-size: 11px; color: #666; margin-bottom: 8px;">※数値や単位をそのまま入力できます（例：336、3.8gなど）</div>
                
                <div class="sub-form-group">
                    <label>エネルギー (カロリー)</label>
                    <input type="text" name="energy_kcal" placeholder="例: 336">
                </div>
                
                <div class="nutrition-grid">
                    <div class="sub-form-group">
                        <label>たんぱく質</label>
                        <input type="text" name="protein_g" placeholder="例: 3.1g">
                    </div>
                    <div class="sub-form-group">
                        <label>脂質</label>
                        <input type="text" name="fat_g" placeholder="例: 21.6g">
                    </div>
                    <div class="sub-form-group">
                        <label>炭水化物</label>
                        <input type="text" name="carbo_g" placeholder="例: 32.3g">
                    </div>
                    <div class="sub-form-group">
                        <label>食塩相当量</label>
                        <input type="text" name="salt_g" placeholder="例: 0.6g">
                    </div>
                </div>
            </div>

            <div class="form-group" style="border-top: 1px solid #e2e8f0; padding-top: 12px; margin-top: 15px;">
                <label>アレルゲン（特定原材料等）</label>
                <div style="font-size: 11px; color: #666; margin-bottom: 6px;">※後で「除外検索」できるように、含まれるものを入力</div>
                <input type="text" name="allergen" placeholder="例: 小麦、乳成分、大豆">
            </div>

        </div>

        <button type="submit" class="btn-submit">登録する</button>
    </form>

    <a href="index.php" class="back-link">◀ トップに戻る</a>
</div>

<script>
    // ▶︎ を ▼ に切り替え、詳細フォームを展開・格納するJS関数
    function toggleAccordion() {
        const content = document.getElementById('detail-content');
        const icon = document.getElementById('arrow-icon');
        
        if (content.style.display === 'block') {
            content.style.display = 'none';
            icon.textContent = '▶︎';
        } else {
            content.style.display = 'block';
            icon.textContent = '▼';
        }
    }
</script>

</body>
</html>