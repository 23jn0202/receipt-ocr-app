<?php
require 'vendor/autoload.php'; // ComposerでAzure SDKをインストールする必要があります
use Microsoft\Azure\AI\Vision\VisionServiceClient;

// Azureの設定
$endpoint = getenv('https://receipt-ocr-service.cognitiveservices.azure.com/');
$apiKey = getenv('6MgeNRKrq80r3Fc3REHwxTRbLhHLTSxGrOqWlfjdZng1HHdgM0vNJQQJ99BAACi0881XJ3w3AAALACOGnNfz');

// ファイルアップロード処理
if (!empty($_FILES['receipts'])) {
    $uploadedFiles = $_FILES['receipts']['tmp_name'];
    $results = [];

    foreach ($uploadedFiles as $index => $filePath) {
        // Azure OCRを呼び出し
        $client = new VisionServiceClient($endpoint, $apiKey);
        $ocrResult = $client->analyzeImage($filePath, ['Read']);

        // OCR結果の解析（例: 商品名、値段、合計金額を抽出）
        $items = [];
        foreach ($ocrResult->analyzeResult->lines as $line) {
            if (preg_match('/(.+?)\s+¥(\d+)/u', $line->text, $matches)) {
                $items[] = [
                    'name' => $matches[1],
                    'price' => $matches[2]
                ];
            } elseif (preg_match('/合計\s+¥(\d+)/u', $line->text, $matches)) {
                $total = $matches[1];
            }
        }

        // 結果保存
        $results[] = [
            'items' => $items,
            'total' => $total ?? null
        ];

        // ログに書き込み
        file_put_contents('ocr.log', json_encode($results, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }

    // 結果表示＆CSVダウンロードリンク
    echo "<h2>抽出結果</h2>";
    foreach ($results as $result) {
        foreach ($result['items'] as $item) {
            echo "{$item['name']} ¥{$item['price']}<br>";
        }
        echo "合計 ¥{$result['total']}<br><br>";
    }

    // CSV生成リンク
    $csvFile = 'output.csv';
    $fp = fopen($csvFile, 'w');
    foreach ($results as $result) {
        foreach ($result['items'] as $item) {
            fputcsv($fp, [$item['name'], $item['price']]);
        }
        fputcsv($fp, ['合計', $result['total']]);
    }
    fclose($fp);

    echo "<a href='$csvFile'>CSVファイルをダウンロード</a>";

    if (!file_exists('ocr.log')) {
        touch('ocr.log'); // ファイルがなければ作成
        chmod('ocr.log', 0666); // 書き込み可能に設定
    }
}
?>