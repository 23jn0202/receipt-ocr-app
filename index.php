<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>レシートアップロード</title>
</head>
<body>
    <h1>レシートOCRアップロード</h1>
    <form action="process.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="receipts[]" multiple accept="image/*">
        <button type="submit">アップロード</button>
    </form>
</body>
</html>