<?php
// Azureの設定
$endpoint = 'https://receipt-ocr-service.cognitiveservices.azure.com/'; // ご自身のAzureのエンドポイントを設定
$apiKey = '6MgeNRKrq80r3Fc3REHwxTRbLhHLTSxGrOqWlfjdZng1HHdgM0vNJQQJ99BAACi0881XJ3w3AAALACOGnNfz'; // ご自身のAPIキーを設定

// OCRリクエスト用のURL
$ocrUrl = $endpoint . 'vision/v3.2/read/analyze';

// ファイルアップロード処理
if (!empty($_FILES['receipts'])) {
    $uploadedFiles = $_FILES['receipts']['tmp_name'];
    $results = [];

    foreach ($uploadedFiles as $index => $filePath) {
        // ファイルをバイナリ形式で読み込む
        $imageData = file_get_contents($filePath);

        // cURLを使用してAzure APIにリクエスト
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ocrUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/octet-stream',
            'Ocp-Apim-Subscription-Key: ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode == 202) {
            // OCRの処理が開始されたら、結果を取得するためにポーリングします
            // 読み取り結果が取得できるまで待つ
            $operationLocation = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $result = null;
            while (true) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $operationLocation);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Ocp-Apim-Subscription-Key: ' . $apiKey
                ]);
                $response = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($statusCode == 200) {
                    $result = json_decode($response, true);
                    break;
                }

                // 一定時間待機してから再試行
                sleep(1);
            }

            // OCR結果の解析（例: 商品名、値段、合計金額を抽出）
            if (isset($result['analyzeResult']['readResults'])) {
                foreach ($result['analyzeResult']['readResults'] as $page) {
                    foreach ($page['lines'] as $line) {
                        if (preg_match('/(.+?)\s+¥(\d+)/u', $line['text'], $matches)) {
                            $items[] = [
                                'name' => $matches[1],
                                'price' => $matches[2]
                            ];
                        } elseif (preg_match('/合計\s+¥(\d+)/u', $line['text'], $matches)) {
                            $total = $matches[1];
                        }
                    }
                }

                // 結果表示
                echo "<h2>抽出結果</h2>";
                foreach ($items as $item) {
                    echo "{$item['name']} ¥{$item['price']}<br>";
                }
                echo "合計 ¥{$total}<br><br>";
            } else {
                echo "OCR結果の取得に失敗しました。";
            }
        } else {
            echo "OCRリクエストに失敗しました。ステータスコード: " . $statusCode;
        }
    }
}
?>
