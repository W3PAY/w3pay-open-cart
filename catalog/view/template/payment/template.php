<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= (!empty($data['title']))?$data['title']:'' ?></title>
    <meta name="description" content="<?= (!empty($data['description']))?$data['description']:'' ?>">
</head>
<body>
<?= (!empty($data['content']))?$data['content']:'' ?>
</body>
</html>