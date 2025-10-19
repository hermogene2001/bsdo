<?php
require_once("../includes/db.php");

// Get parameters
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$limit = 6;
$offset = ($page - 1) * $limit;

$category = $_POST['category'] ?? '';
$location = $_POST['location'] ?? '';
$min_price = $_POST['min_price'] ?? 0;
$max_price = $_POST['max_price'] ?? 999999999;

// Build query
$sql = "SELECT * FROM products WHERE status='available' 
        AND price BETWEEN :min_price AND :max_price";

$params = [
    ':min_price' => $min_price,
    ':max_price' => $max_price
];

if($category !== '') {
    $sql .= " AND category=:category";
    $params[':category'] = $category;
}
if($location !== '') {
    $sql .= " AND location LIKE :location";
    $params[':location'] = "%$location%";
}

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($products as $p):
    $imgQ = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id=? LIMIT 1");
    $imgQ->execute([$p['id']]);
    $img = $imgQ->fetch(PDO::FETCH_ASSOC);
    ?>
    <div class="col-md-4 mb-4">
      <div class="card product-card">
        <img src="<?= $img ? $img['image_path'] : 'assets/images/noimage.png' ?>" class="card-img-top" alt="">
        <div class="card-body">
          <h5 class="card-title"><?= htmlspecialchars($p['title']) ?></h5>
          <?php if ($p['product_type'] === 'rental'): ?>
            <p class="text-muted">$<?= number_format($p['rental_price_per_day'],2) ?>/day</p>
          <?php else: ?>
            <p class="text-muted">$<?= number_format($p['price'],2) ?></p>
          <?php endif; ?>
          <p class="small"><?= htmlspecialchars($p['location']) ?></p>
          <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm">View</a>
        </div>
      </div>
    </div>
<?php endforeach; ?>
