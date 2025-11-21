<?php
require_once "config.php";

$sort = $_GET['sort'] ?? 'name';
$order = $_GET['order'] ?? 'asc';

$allowedSort = ['name','neighbourhood_group_cleansed','price','host_name'];
if (!in_array($sort, $allowedSort)) {
    die("Invalid sort");
}
if (!in_array($order, ['asc','desc'])) {
    die("Invalid order");
}

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = trim($_POST['name'] ?? '');
    $picture_url = trim($_POST['picture_url'] ?? '');
    $host_name = trim($_POST['host_name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $city = trim($_POST['neighbourhood_group_cleansed'] ?? '');
    $review = trim($_POST['review_scores_value'] ?? '');

    if ($name === '') $errors[] = "Le nom est requis.";
    if ($picture_url === '') $errors[] = "L'URL de l'image est requise.";
    if ($host_name === '') $errors[] = "Le nom du propriétaire est requis.";
    if ($price === '' || !is_numeric($price)) $errors[] = "Le prix doit être un nombre.";
    if ($review !== '' && !is_numeric($review)) $errors[] = "Le score de review doit être numérique si renseigné.";

    if (empty($errors)) {
      
        $idStmt = $dbh->query("SELECT MAX(id) AS m FROM listings");
        $max = $idStmt->fetchColumn();
        $newId = $max !== null ? intval($max) + 1 : 1;

        $sth = $dbh->prepare("
            INSERT INTO listings (id, name, picture_url, host_name, host_thumbnail_url, price, neighbourhood_group_cleansed, review_scores_value)
            VALUES (:id, :name, :picture_url, :host_name, :host_thumbnail_url, :price, :city, :review)
        ");

        $host_thumb = $host_name !== '' ? '' : '';
        $sth->execute([
            ':id' => $newId,
            ':name' => $name,
            ':picture_url' => $picture_url,
            ':host_name' => $host_name,
            ':host_thumbnail_url' => $host_thumb,
            ':price' => (int)$price,
            ':city' => $city,
            ':review' => $review === '' ? null : (float)$review
        ]);
        $success = "Annonce ajoutée avec succès.";

        header("Location: ?page=1&sort=" . urlencode($sort) . "&order=" . urlencode($order));
        exit;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) FROM listings";
$countStmt = $dbh->prepare($countSql);
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

$sql = "SELECT * FROM listings ORDER BY $sort $order LIMIT :limit OFFSET :offset";
$sth = $dbh->prepare($sql);
$sth->bindValue(':limit', $limit, PDO::PARAM_INT);
$sth->bindValue(':offset', $offset, PDO::PARAM_INT);
$sth->execute();
$data = $sth->fetchAll();

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Listings</title>
    <style>
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
    img.thumb { width: 150px; height: auto; }
    .pager a { margin: 0 4px; text-decoration: none; }
    .pager .current { color: red; font-weight: bold; }
  </style>
</head>
<body>

<h1>Liste des logements</h1>

<form method="get" id="sortForm">
  Trier par :
  <select name="sort" onchange="document.getElementById('sortForm').submit()">
    <option value="name" <?= $sort==='name' ? 'selected' : '' ?>>Nom</option>
    <option value="neighbourhood_group_cleansed" <?= $sort==='neighbourhood_group_cleansed' ? 'selected' : '' ?>>Ville</option>
    <option value="price" <?= $sort==='price' ? 'selected' : '' ?>>Prix</option>
    <option value="host_name" <?= $sort==='host_name' ? 'selected' : '' ?>>Proprio</option>
  </select>

  <select name="order" onchange="document.getElementById('sortForm').submit()">
    <option value="asc" <?= $order==='asc' ? 'selected' : '' ?>>asc</option>
    <option value="desc" <?= $order==='desc' ? 'selected' : '' ?>>desc</option>
  </select>

  <input type="hidden" name="page" value="<?= intval($page) ?>">
  <noscript><button type="submit">OK</button></noscript>
</form>

<hr>

<h2>Ajouter une annonce</h2>

<?php if (!empty($errors)): ?>
  <?php foreach ($errors as $e): ?>
    <p style="color:red"><?= h($e) ?></p>
  <?php endforeach; ?>
<?php endif; ?>

<?php if ($success): ?>
  <p style="color:green"><?= h($success) ?></p>
<?php endif; ?>

<form method="post">
  Nom : <br><input type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" required><br><br>
  URL image : <br><input type="url" name="picture_url" value="<?= h($_POST['picture_url'] ?? '') ?>" required><br><br>
  Proprietaire : <br><input type="text" name="host_name" value="<?= h($_POST['host_name'] ?? '') ?>" required><br><br>
  Prix : <br><input type="text" name="price" value="<?= h($_POST['price'] ?? '') ?>" required><br><br>
  Ville : <br><input type="text" name="neighbourhood_group_cleansed" value="<?= h($_POST['neighbourhood_group_cleansed'] ?? '') ?>"><br><br>
  Note (optionnel) : <br><input type="text" name="review_scores_value" value="<?= h($_POST['review_scores_value'] ?? '') ?>"><br><br>
  <input type="submit" name="add" value="Ajouter">
</form>

<hr>

<table>
  <thead>
    <tr>
      <th>Image</th>
      <th>Nom</th>
      <th>Ville</th>
      <th>Proprietaire</th>
      <th>Prix</th>
      <th>Note</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($data as $row): ?>
      <tr>
        <td>
          <?php if (!empty($row['picture_url'])): ?>
            <img class="thumb" src="<?= h($row['picture_url']) ?>" alt="<?= h($row['name']) ?>">
          <?php endif; ?>
        </td>
        <td><?= h($row['name']) ?></td>
        <td><?= h($row['neighbourhood_group_cleansed']) ?></td>
        <td><?= h($row['host_name']) ?></td>
        <td><?= h($row['price']) ?> €</td>
        <td><?= h($row['review_scores_value']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="pager">
  <?php
    $baseParams = "sort=" . urlencode($sort) . "&order=" . urlencode($order);
    if ($page > 1) {
        $prev = $page - 1;
        echo "<a href=\"?{$baseParams}&page={$prev}\">← Précédent</a>";
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            echo "<span class=\"current\"> {$i} </span>";
        } else {
            echo "<a href=\"?{$baseParams}&page={$i}\"> {$i} </a>";
        }
    }
    if ($page < $totalPages) {
        $next = $page + 1;
        echo "<a href=\"?{$baseParams}&page={$next}\">Suivant →</a>";
    }
  ?>
</div>

</body>
</html>