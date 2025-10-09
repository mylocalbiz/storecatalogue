<?php
// Include database connection
include 'db_connect.php';

// --- AJAX REQUEST LOGIC (Filter updates included) ---
if (isset($_GET['load_more']) && $_GET['load_more'] == '1') {
    // This part will only run for AJAX requests
    $limit = 12;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start = ($page - 1) * $limit;

    $sql = "SELECT p.id, p.name, p.description, p.price, p.sale_price, p.image_url, p.image_filename_thumb, p.on_sale, p.stock_quantity, p.tag, c.category_name, s.subcategory_name FROM products p JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON p.subcategory_id = s.id";
    $params = [];
    $types = "";
    $where_clause = " WHERE 1=1";

    // Replicate all filter logic for AJAX
    if (isset($_GET['category']) && $_GET['category'] != '') {
        $where_clause .= " AND c.category_name = ?";
        $params[] = $_GET['category'];
        $types .= "s";
    }
    // Subcategory Filter for AJAX
    if (isset($_GET['subcategory']) && $_GET['subcategory'] != '') {
        $where_clause .= " AND s.subcategory_name = ?";
        $params[] = $_GET['subcategory'];
        $types .= "s";
    }
    if (isset($_GET['in_stock'])) {
        $where_clause .= " AND p.stock_quantity > 0";
    }
    // New On Sale Filter for AJAX
    if (isset($_GET['on_sale'])) {
        $where_clause .= " AND p.on_sale = 1";
    }
    if (isset($_GET['brand']) && $_GET['brand'] != '') {
        $where_clause .= " AND p.brand = ?";
        $params[] = $_GET['brand'];
        $types .= "s";
    }
    if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
        $where_clause .= " AND p.price >= ?";
        $params[] = $_GET['min_price'];
        $types .= "d";
    }
    if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
        $where_clause .= " AND p.price <= ?";
        $params[] = $_GET['max_price'];
        $types .= "d";
    }

    $sort_order = "p.name ASC";
    if (isset($_GET['sort'])) {
        switch ($_GET['sort']) {
            case 'name_asc':
                $sort_order = "p.name ASC";
                break;
            case 'price_asc':
                $sort_order = "p.price ASC";
                break;
            case 'price_desc':
                $sort_order = "p.price DESC";
                break;
        }
    }
    $sql .= $where_clause . " ORDER BY " . $sort_order;

    $sql .= " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $limit;
    $types .= "ii";

    $stmt = $conn->prepare($sql);

    if (!empty($types)) {
        $bind_args = [$types];
        foreach ($params as $key => $value) {
            $bind_args[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_args);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Echo the HTML for a single product card
            ?>
            <div class="col">
                <a href="product.php?id=<?php echo $row["id"]; ?>" class="product-card-link">
                    <div class="card h-100 rounded-3">
                        <div class="card-img-container">
                            <img src="images/<?php echo htmlspecialchars($row["image_filename_thumb"]); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row["name"]); ?>" loading="lazy">
                            
                            <?php if ($row['stock_quantity'] <= 0) { ?>
                                <div class="product-badge out-of-stock-badge">Out of Stock</div>
                            <?php } else if ($row['on_sale'] == 1 && $row['sale_price'] !== null) { ?>
                                <div class="product-badge on-sale-badge">On Sale</div>
                            <?php } ?>
                            
                            <?php if ($row['tag']) { ?>
                                <div class="product-badge tag-badge"><?php echo htmlspecialchars($row['tag']); ?></div>
                            <?php } ?>
                            
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold text-dark mb-1"><?php echo htmlspecialchars($row["name"]); ?></h5>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($row['category_name'] . (isset($row['subcategory_name']) ? ' / ' . $row['subcategory_name'] : '')); ?></p>
                            <div class="mt-auto pt-3 border-top">
                                <?php if ($row['on_sale'] == 1 && $row['sale_price'] !== null) { ?>
                                    <p class="text-muted text-decoration-line-through mb-0">R<?php echo htmlspecialchars(number_format($row["price"], 2)); ?></p>
                                    <h4 class="text-primary mb-0">R<?php echo htmlspecialchars(number_format($row["sale_price"], 2)); ?></h4>
                                <?php } else { ?>
                                    <h4 class="text-primary mb-0">R<?php echo htmlspecialchars(number_format($row["price"], 2)); ?></h4>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <?php
        }
    }
    $stmt->close();
    $conn->close();
    exit;
}

// --- REGULAR PAGE LOAD START ---

$category_hero = null;
$active_category_name = $_GET['category'] ?? '';
$hero_bg_class = 'bg-light';
$hero_style_attribute = '';
$category_selected = !empty($active_category_name);

if ($category_selected) {
    // Fetch hero_images, description, and hero_bg_color for the selected category
    $stmt = $conn->prepare("SELECT category_name, description, hero_images, hero_bg_color FROM categories WHERE category_name = ?");
    if ($stmt) {
        $stmt->bind_param("s", $active_category_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $category_hero = $result->fetch_assoc();
            
            // --- START: Dynamic Background Color Logic ---
            $db_color = htmlspecialchars($category_hero['hero_bg_color']);

            // Check if the value is a hex code (starts with #)
            if (!empty($db_color) && str_starts_with($db_color, '#')) {
                // Apply as an inline style attribute value
                $hero_style_attribute = 'style="background-color: ' . $db_color . ' !important;"'; 
                $hero_bg_class = ''; 
            } else {
                // Otherwise, use it as a Bootstrap class (e.g., bg-primary, bg-light) or default to bg-light
                $hero_bg_class = !empty($db_color) ? 'bg-' . $db_color : 'bg-light';
                $hero_style_attribute = ''; 
            }
            // --- END: Dynamic Background Color Logic ---

        }
        $stmt->close();
    }
}

// Fetch all categories for the grid and offcanvas menu
$categories_for_display = [];
$categories_sql = "SELECT category_name, hero_images, hero_bg_color FROM categories WHERE category_name != '' ORDER BY category_name";
$categories_result = $conn->query($categories_sql);
if ($categories_result) {
    while ($category = $categories_result->fetch_assoc()) {
        $categories_for_display[] = $category;
    }
    $categories_result->free();
}

// Fetch subcategories for the filter sidebar if a category is selected
$subcategories_for_filter = [];
if ($category_selected) {
    // Assuming 'subcategories' table links to 'categories' via 'category_id'
    $subcategories_sql = "SELECT s.subcategory_name FROM subcategories s JOIN categories c ON s.category_id = c.id WHERE c.category_name = ? ORDER BY s.subcategory_name";
    $stmt_subcat = $conn->prepare($subcategories_sql);
    if ($stmt_subcat) {
        $stmt_subcat->bind_param("s", $active_category_name);
        $stmt_subcat->execute();
        $subcat_result = $stmt_subcat->get_result();
        while ($subcat = $subcat_result->fetch_assoc()) {
            $subcategories_for_filter[] = $subcat['subcategory_name'];
        }
        $stmt_subcat->close();
    }
}

// --- MODIFIED: Fetch unique brands for the filter sidebar, scoped to the active category if one is selected ---
$brands_for_filter = [];
$brands_sql = "SELECT DISTINCT p.brand FROM products p JOIN categories c ON p.category_id = c.id WHERE p.brand IS NOT NULL AND p.brand != ''";
$brand_params = [];
$brand_types = "";

if ($category_selected) {
    $brands_sql .= " AND c.category_name = ?";
    $brand_params[] = $active_category_name;
    $brand_types .= "s";
}

$brands_sql .= " ORDER BY p.brand";

$stmt_brand = $conn->prepare($brands_sql);

if ($stmt_brand && !empty($brand_types)) {
    $bind_args_brand = [$brand_types];
    foreach ($brand_params as $key => $value) {
        $bind_args_brand[] = &$brand_params[$key];
    }
    call_user_func_array([$stmt_brand, 'bind_param'], $bind_args_brand);
}

if ($stmt_brand) {
    $stmt_brand->execute();
    $brands_result = $stmt_brand->get_result();
    
    if ($brands_result) {
        while ($brand = $brands_result->fetch_assoc()) {
            $brands_for_filter[] = $brand['brand'];
        }
    }
    $stmt_brand->close();
}
// --- END MODIFIED BRAND FETCH ---

// --- Total product count logic (Now Dynamic) ---
$count_sql = "SELECT COUNT(*) AS total FROM products p JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON p.subcategory_id = s.id";
$count_params = [];
$count_types = "";
$count_where_clause = " WHERE 1=1";

if ($category_selected) {
    $count_where_clause .= " AND c.category_name = ?";
    $count_params[] = $active_category_name;
    $count_types .= "s";
}
if (isset($_GET['subcategory']) && $_GET['subcategory'] != '') {
    $count_where_clause .= " AND s.subcategory_name = ?";
    $count_params[] = $_GET['subcategory'];
    $count_types .= "s";
}
if (isset($_GET['in_stock'])) {
    $count_where_clause .= " AND p.stock_quantity > 0";
}
if (isset($_GET['on_sale'])) {
    $count_where_clause .= " AND p.on_sale = 1";
}
if (isset($_GET['brand']) && $_GET['brand'] != '') {
    $count_where_clause .= " AND p.brand = ?";
    $count_params[] = $_GET['brand'];
    $count_types .= "s";
}
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $count_where_clause .= " AND p.price >= ?";
    $count_params[] = $_GET['min_price'];
    $count_types .= "d";
}
if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $count_where_clause .= " AND p.price <= ?";
    $count_params[] = $_GET['max_price'];
    $count_types .= "d";
}

$count_sql .= $count_where_clause;

$stmt_count = $conn->prepare($count_sql);

if (!empty($count_types)) {
    $bind_args_count = [$count_types];
    foreach ($count_params as $key => $value) {
        $bind_args_count[] = &$count_params[$key];
    }
    call_user_func_array([$stmt_count, 'bind_param'], $bind_args_count);
}

$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_products = $count_result->fetch_assoc()['total'] ?? 0;
$stmt_count->close();
// --- End of Dynamic Total product count logic ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diphoko Hardware -
        <?php echo $category_selected ? htmlspecialchars($active_category_name) : 'Shop All'; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        
    </style>
</head>
<body>

    <!--
    <nav class="navbar navbar-dark sticky-top" style="background-color: #152990; padding-left: 5%; padding-right:5%;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bolder" href="index.php">Diphoko Sand & Stone</a>

            <div class="d-flex align-items-center gap-2">
                <?php if ($category_selected): ?>
                    <button class="btn btn-outline-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFilter" aria-controls="offcanvasFilter">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                <?php endif; ?>

                <button class="navbar-toggler d-flex align-items-center gap-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar" aria-label="Toggle navigation">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                    </svg>
                    <span>Products</span>
                </button>
            </div>
        </div>
    </nav>
    -->

    <!--<nav class="navbar navbar-expand-lg navbar-dark fixed-top shadow-sm" style="background: linear-gradient(90deg, #0d1b78, #152990); border-bottom: 2px solid rgba(255,255,255,0.1);">
  
  <div class="container-fluid" style="padding-left: 5%; padding-right: 5%;">
      
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php" style="font-size: 1.3rem;">
          <img src="images/logo.png" alt="Diphoko Logo" width="35" height="35" class="rounded-circle bg-light p-1">
          Diphoko Sand & Stone
      </a>

     
      <div class="d-flex align-items-center gap-2">
          <?php if ($category_selected): ?>
              <button class="btn btn-outline-light btn-sm d-lg-none rounded-pill px-3" 
                      type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFilter" 
                      aria-controls="offcanvasFilter">
                  <i class="fas fa-filter me-1"></i> Filter
              </button>
          <?php endif; ?>

          <button class="navbar-toggler d-flex align-items-center gap-2 rounded-pill px-3 py-2 border-0" 
                  type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" 
                  aria-controls="offcanvasNavbar" aria-label="Toggle navigation" 
                  style="background-color: rgba(255,255,255,0.15);">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="white" 
                   class="bi bi-list" viewBox="0 0 16 16">
                  <path fill-rule="evenodd" 
                        d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5
                           m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5
                           m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
              </svg>
              <span class="text-white fw-semibold">Menu</span>
          </button>
      </div>
  </div>
</nav>-->

    <!-- Top Contact Bar -->
    <div class="bg-dark text-light py-2 small">
        <div class="container-fluid d-flex justify-content-between align-items-center" style="padding-left: 5%; padding-right: 5%;">
            <div class="text-center"><i class="fas fa-phone-alt me-2 text-warning"></i> Call us now: <strong>+27 12 345 6789</strong></div>
        </div>
    </div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: #152990;">
  <div class="container-fluid" style="padding-left: 5%; padding-right: 5%;">
    <!-- Logo -->
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
      <img src="images/logo.png" alt="Diphoko Logo" width="45" height="45" class="rounded-circle bg-light p-1">
      <span class="text-warning">Diphoko</span> Sand & Stone
    </a>

    <!-- Search bar 
    <form class="d-none d-lg-flex mx-auto" role="search" style="max-width: 400px; width: 100%;">
      <input class="form-control me-2 rounded-pill" type="search" placeholder="Search..." aria-label="Search">
      <button class="btn btn-warning rounded-pill px-4 fw-semibold">Search</button>
    </form>-->

    <!-- Right buttons -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="offcanvasNavbar">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Menu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0 fw-semibold">
          <li class="nav-item d-none d-sm-block"><a class="nav-link" href="#">Home</a></li>
          <li class="nav-item d-none d-sm-block"><a class="nav-link" href="#">Shop</a></li>
          <li class="nav-item d-none d-sm-block"><a class="nav-link" href="#">Blog</a></li>
          <li class="nav-item d-none d-sm-block"><a class="nav-link" href="#">Contact</a></li>
          <li class="nav-item d-xxl-none">
                <a class="nav-link fw-bold  <?php echo (!$category_selected ? 'active' : ''); ?>" href="shop.php">
                    All Products
                </a>
            </li>
            <li class="nav-item d-xxl-none">
                <hr class="dropdown-divider">
            </li>
            <?php foreach ($categories_for_display as $category): ?>
                <li class="nav-item d-xxl-none">
                    <a class="nav-link <?php echo ($active_category_name == $category['category_name'] ? 'active' : ''); ?>" href="shop.php?category=<?php echo urlencode($category['category_name']); ?>">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- Category Dropdown Bar -->
<div class="bg-warning py-2 shadow-sm ">
  <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap gap-2" style="padding-left: 5%; padding-right: 5%;">
    <div class="dropdown d-none d-sm-block">
      <button class="btn btn-dark rounded-pill px-4 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-bars me-2"></i> All Categories
      </button>
      <ul class="dropdown-menu">
        <li>
                <a class="dropdown-item <?php echo (!$category_selected ? 'active' : ''); ?>" href="shop.php">
                    All Products
                </a>
            </li>
        <?php foreach ($categories_for_display as $category): ?>
                <li>
                    <a class="dropdown-item <?php echo ($active_category_name == $category['category_name'] ? 'active' : ''); ?>" href="shop.php?category=<?php echo urlencode($category['category_name']); ?>">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
      </ul>
      
    </div>
    
    <div class="text-dark fw-semibold">
      Upto <span class="text-danger">50%</span> Discount on Bulk Orders
    </div>
  </div>
</div>




<div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="offcanvasNavbarLabel">Product Departments</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="navbar-nav justify-content-end flex-grow-1 pe-3">
            <li class="nav-item">
                <a class="nav-link fw-bold <?php echo (!$category_selected ? 'active' : ''); ?>" href="shop.php">
                    <i class="fas fa-hammer me-2"></i> All Products
                </a>
            </li>
            <li class="nav-item">
                <hr class="dropdown-divider">
            </li>
            <?php foreach ($categories_for_display as $category): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($active_category_name == $category['category_name'] ? 'active' : ''); ?>" href="shop.php?category=<?php echo urlencode($category['category_name']); ?>">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if ($category_selected): ?>
<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasFilter" aria-labelledby="offcanvasFilterLabel">
    <div class="offcanvas-header bg-light">
        <h5 class="offcanvas-title" id="offcanvasFilterLabel">Filter Products</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="filter-form-mobile" action="shop.php" method="GET">
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($active_category_name); ?>">
            
            <?php if (!empty($subcategories_for_filter)): ?>
            <div class="mb-3">
                <label for="subcategory-select-mobile" class="form-label fw-bold">Subcategory</label>
                <select class="form-select" id="subcategory-select-mobile" name="subcategory">
                    <option value="">All <?php echo htmlspecialchars($active_category_name); ?></option>
                    <?php foreach ($subcategories_for_filter as $subcat): ?>
                        <option value="<?php echo htmlspecialchars($subcat); ?>" <?php echo (isset($_GET['subcategory']) && $_GET['subcategory'] == $subcat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subcat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Availability</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in-stock-mobile" <?php echo isset($_GET['in_stock']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="in-stock-mobile">Show only in stock</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="on_sale" value="1" id="on-sale-mobile" <?php echo isset($_GET['on_sale']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="on-sale-mobile">On Sale</label>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="brand-select-mobile" class="form-label fw-bold">Brand</label>
                <select class="form-select" id="brand-select-mobile" name="brand">
                    <option value="">All Brands</option>
                    <?php foreach ($brands_for_filter as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brand); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Price Range</label>
                <div class="row">
                    <div class="col"><input type="number" class="form-control" name="min_price" placeholder="Min" value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>"></div>
                    <div class="col"><input type="number" class="form-control" name="max_price" placeholder="Max" value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>"></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
            <a href="shop.php?category=<?php echo urlencode($active_category_name); ?>" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
// 1. Category Hero (if category is selected)
if ($category_hero): 
    // Determine the text color based on the background
    $text_color_class = 'text-dark'; 
    
    // Logic to set text to white for known dark Bootstrap colors
    if (in_array(str_replace('bg-', '', $hero_bg_class), ['dark', 'primary', 'secondary', 'success', 'danger', 'info'])) {
        $text_color_class = 'text-white';
    } 
?>

    <div class="col-xxl-11 p-3 mx-auto" style="margin-top: 50px;">
        <div class="py-5 mb-4 shadow-sm rounded-4 <?php echo $hero_bg_class; ?>" <?php echo $hero_style_attribute; ?>>
            <div class="container">
                <div class="row align-items-center">
                    
                    <div class="col-lg-7 text-center text-lg-start">
                        <h1 class="display-3 fw-bolder <?php echo $text_color_class; ?>"><?php echo htmlspecialchars($category_hero['category_name']); ?></h1>
                        <?php if (!empty($category_hero['description'])): ?>
                            <p class="lead <?php echo $text_color_class; ?>-50"><?php echo htmlspecialchars($category_hero['description']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($category_hero['hero_images'])): ?>
                        <div class="col-lg-5 mt-4 mt-lg-0">
                            <img src="images/assets/hero/<?php echo htmlspecialchars($category_hero['hero_images']); ?>" class="img-fluid rounded shadow-lg" alt="<?php echo htmlspecialchars($category_hero['category_name']); ?> Image">
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

<?php
// 2. Introductory Hero Section & Featured Categories Grid (Only if NO category is selected)
elseif (!$category_selected): 
?>
    <div class="py-5 mb-4 shadow-sm bg-dark">
        <div class="container text-center">
            <h1 class="display-3 fw-bolder text-white">Explore Our Full Inventory</h1>
            <p class="lead text-white-50">Find everything you need for your next project, from tools to tough materials. Start by selecting a department below!</p>
            <a href="#category-grid" class="btn btn-lg btn-warning mt-3">Browse Departments</a>
        </div>
    </div>
    
    <div class="container my-5" id="category-grid">
        <h2 class="text-center mb-4 fw-bold text-dark">Shop by Department</h2>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
            <?php
            foreach ($categories_for_display as $category):
                $category_link = 'shop.php?category=' . urlencode($category['category_name']);
                $bg_color = $category['hero_bg_color'];
                $bg_color_class = 'bg-light';
                $text_class = 'text-dark';

                if ($bg_color && !str_starts_with($bg_color, '#')) {
                    $bg_color_class = 'bg-' . htmlspecialchars($bg_color);
                }
                if (in_array(str_replace('bg-', '', $bg_color_class), ['dark', 'primary', 'secondary', 'success', 'danger', 'info'])) {
                     $text_class = 'text-white';
                }
            ?>
                <div class="col">
                    <a href="<?php echo $category_link; ?>" class="text-decoration-none">
                        <div class="card h-100 p-2 text-center shadow-sm border-0 <?php echo $bg_color_class; ?>">
                            <i class="fas fa-hammer fa-3x mb-2 <?php echo $text_class; ?>"></i>
                            <p class="mb-0 fw-bold <?php echo $text_class; ?>"><?php echo htmlspecialchars($category['category_name']); ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <hr class="my-5">
<?php endif; ?> 


<!-- Content Body -->
<div class="col-xxl-11 mx-auto">
<div class="container-fluid mt-4">
    <div class="row">
        
        <?php if ($category_selected): ?>

            <!-- =========== Left Content =====================================================================================================================-->
             
            <div class="col-lg-3 col-xxl-3 d-none d-lg-block">
                <div class="filter-sidebar p-3">
                    <!--<h5 class="fw-bold mb-3">Filter <?php echo htmlspecialchars($active_category_name); ?></h5>-->
                    <form id="filter-form" action="shop.php" method="GET">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($active_category_name); ?>">

                        <!--<?php if (!empty($subcategories_for_filter)): ?>
                        <div class="mb-4">
                            <label for="subcategory-select" class="form-label fw-bold fs-4">Subcategory</label>
                            <select class="form-select" id="subcategory-select" name="subcategory" onchange="this.form.submit()">
                                <option value="">All <?php echo htmlspecialchars($active_category_name); ?></option>
                                <?php foreach ($subcategories_for_filter as $subcat): ?>
                                    <option value="<?php echo htmlspecialchars($subcat); ?>" <?php echo (isset($_GET['subcategory']) && $_GET['subcategory'] == $subcat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subcat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>-->

                        <?php if (!empty($subcategories_for_filter)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold fs-4 d-block pb-2 mb-4 border-bottom border-3 border-black">Category</label>
        
        <ul class="list-unstyled mb-0">
            <!-- "All" Link -->
            <li class="mb-2">
                <a href="?category=<?php echo urlencode($active_category_name); ?>"
                   class="text-decoration-none d-block py-1 <?php echo empty($_GET['subcategory']) ? 'fw-bold text-black' : 'text-body'; ?>">
                   All <!--<?php echo htmlspecialchars($active_category_name); ?>-->
                </a>
            </li>

            <!-- Subcategory Links -->
            <?php foreach ($subcategories_for_filter as $subcat): ?>
                <li class="mb-2">
                    <a href="?category=<?php echo urlencode($active_category_name); ?>&subcategory=<?php echo urlencode($subcat); ?>"
                       class="text-decoration-none d-block py-1 <?php echo (isset($_GET['subcategory']) && $_GET['subcategory'] == $subcat) ? 'fw-bold text-black' : 'text-body'; ?>">
                       <?php echo htmlspecialchars($subcat); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


                        <div class="mb-4">
                            <label class="form-label fw-bold fs-4 d-block pb-2 mb-4 border-bottom border-3 border-black">Availability</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in-stock" <?php echo isset($_GET['in_stock']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="in-stock">Show only in stock</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="on_sale" value="1" id="on-sale" <?php echo isset($_GET['on_sale']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="on-sale">On Sale</label>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label for="brand-select" class="form-label fw-bold fs-4 d-block pb-2 mb-4 border-bottom border-3 border-black">Brand</label>
                            <select class="form-select bg-body-secondary" id="brand-select" name="brand">
                                <option value="">All Brands</option>
                                <?php foreach ($brands_for_filter as $brand): ?>
                                    <option class="bg-body-white" value="<?php echo htmlspecialchars($brand); ?>" <?php echo (isset($_GET['brand']) && $_GET['brand'] == $brand) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($brand); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>


                        <!--
                        <div class="mb-4">
                            <label class="form-label fw-bold fs-4 d-block pb-2 mb-4 border-bottom border-3 border-warning">Price Range</label>
                            <div class="row g-2">
                                <div class="col">
                                    <input type="number" class="form-control form-control-sm" name="min_price" placeholder="Min" value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>">
                                </div>
                                <div class="col">
                                    <input type="number" class="form-control form-control-sm" name="max_price" placeholder="Max" value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                                -->

                        <button type="submit" class="btn btn-primary bg-dark border-0 w-100 mb-2">Apply Filters</button>
                        <a href="shop.php?category=<?php echo urlencode($active_category_name); ?>" class="btn btn-outline-secondary w-100">Clear Filters</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-lg-<?php echo ($category_selected ? '9' : '12'); ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="fw-bold mb-0 d-none d-sm-block hide">
                    <?php echo $category_selected ? htmlspecialchars($active_category_name) : 'All Products'; ?>
                </h2>
                
                
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Sort By: 
                        <?php 
                            $sort_label = 'Name (A-Z)';
                            switch ($_GET['sort'] ?? '') {
                                case 'price_asc': $sort_label = 'Price (Low to High)'; break;
                                case 'price_desc': $sort_label = 'Price (High to Low)'; break;
                                case 'name_asc': default: $sort_label = 'Name (A-Z)'; break;
                            }
                            echo $sort_label;
                        ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-center">
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name_asc', 'page' => 1])); ?>">Name (A-Z)</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_asc', 'page' => 1])); ?>">Price (Low to High)</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_desc', 'page' => 1])); ?>">Price (High to Low)</a></li>
                    </ul>
                </div>
            </div>

            <!-- =================  Right Column ====================== -->
            
            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-3 row-cols-xxl-4 g-4" id="product-container">
                <?php
                // --- Initial product load SQL logic ---
                $limit = 12;
                $sql = "SELECT p.id, p.name, p.description, p.price, p.sale_price, p.image_url, p.image_filename_thumb, p.on_sale, p.stock_quantity, p.tag, c.category_name, s.subcategory_name FROM products p JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON p.subcategory_id = s.id";
                $params = [];
                $types = "";
                $where_clause = " WHERE 1=1";
                
                // Add initial category filter
                if ($category_selected) {
                    $where_clause .= " AND c.category_name = ?";
                    $params[] = $active_category_name;
                    $types .= "s";
                }
                // Add filters from GET requests for initial load
                if (isset($_GET['subcategory']) && $_GET['subcategory'] != '') {
                    $where_clause .= " AND s.subcategory_name = ?";
                    $params[] = $_GET['subcategory'];
                    $types .= "s";
                }
                if (isset($_GET['in_stock'])) {
                    $where_clause .= " AND p.stock_quantity > 0";
                }
                // New On Sale Filter for Initial Load
                if (isset($_GET['on_sale'])) {
                    $where_clause .= " AND p.on_sale = 1";
                }
                if (isset($_GET['brand']) && $_GET['brand'] != '') {
                    $where_clause .= " AND p.brand = ?";
                    $params[] = $_GET['brand'];
                    $types .= "s";
                }
                if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
                    $where_clause .= " AND p.price >= ?";
                    $params[] = $_GET['min_price'];
                    $types .= "d";
                }
                if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
                    $where_clause .= " AND p.price <= ?";
                    $params[] = $_GET['max_price'];
                    $types .= "d";
                }

                $sort_order = "p.name ASC";
                if (isset($_GET['sort'])) {
                    switch ($_GET['sort']) {
                        case 'name_asc': $sort_order = "p.name ASC"; break;
                        case 'price_asc': $sort_order = "p.price ASC"; break;
                        case 'price_desc': $sort_order = "p.price DESC"; break;
                    }
                }
                $sql .= $where_clause . " ORDER BY " . $sort_order . " LIMIT ?";
                $params[] = $limit;
                $types .= "i";
                
                $stmt = $conn->prepare($sql);
                
                // Dynamic bind_param
                if (!empty($types)) {
                    $bind_args = [$types];
                    foreach ($params as $key => $value) {
                        $bind_args[] = &$params[$key];
                    }
                    call_user_func_array([$stmt, 'bind_param'], $bind_args);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Existing product card code
                        ?>
                        <div class="col">
                            <a href="product.php?id=<?php echo $row["id"]; ?>" class="product-card-link border border-0">
                                <div class="card h-100 border border-0">
                                    <div class="card-img-container">
                                        <img src="images/<?php echo htmlspecialchars($row["image_filename_thumb"]); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row["name"]); ?>">
                                        
                                        <?php if ($row['stock_quantity'] <= 0) { ?>
                                            <div class="product-badge out-of-stock-badge">Out of Stock</div>
                                        <?php } else if ($row['on_sale'] == 1 && $row['sale_price'] !== null) { ?>
                                            <div class="product-badge on-sale-badge">On Sale</div>
                                        <?php } ?>
                                        
                                        <?php if ($row['tag']) { ?>
                                            <div class="product-badge tag-badge"><?php echo htmlspecialchars($row['tag']); ?></div>
                                        <?php } ?>
                                        
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title mb-2 fs-6"><?php echo htmlspecialchars($row["name"]); ?></h5>
                                        <div class="mt-auto pt-3 border-top">
                                            <?php if ($row['on_sale'] == 1 && $row['sale_price'] !== null) { ?>
                                                <p class="mb-0 fs-5"><span class="text-muted text-decoration-line-through">R<?php echo htmlspecialchars(number_format($row["price"], 2)); ?></span> <span class="text-primary mb-0">R<?php echo htmlspecialchars(number_format($row["sale_price"], 2)); ?></span></p>
                                                
                                            <?php } else { ?>
                                                <h4 class="text-primary mb-1 fs-5">R<?php echo htmlspecialchars(number_format($row["price"], 2)); ?></h4>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php
                    }
                } else {
                    echo "<p class='text-center w-100'>No products found matching your filters.</p>";
                }
                $stmt->close();
                // --- End of initial product load ---
                ?>
            </div>

            <div class="d-grid gap-2 col-md-6 mx-auto my-5">
                <button class="btn btn-primary" id="load-more-btn" data-page="2" data-total="<?php echo $total_products; ?>" style="display: <?php echo ($limit < $total_products) ? 'block' : 'none'; ?>;">
                    Load More Products
                </button>
            </div>
            
        </div>
    </div>
</div>
            </div>

            <div class="col-xxl-11 p-3 mx-auto" style="margin-top: 50px;">
        <div class="py-5 mb-4 shadow-sm rounded-4 <?php echo $hero_bg_class; ?>" <?php echo $hero_style_attribute; ?>>
            <div class="container">
                <div class="row align-items-center">
                    
                    <div class="col-lg-7 text-center text-lg-start">
                        <h1 class="display-3 fw-bolder <?php echo $text_color_class; ?>"><?php echo htmlspecialchars($category_hero['category_name']); ?></h1>
                        <?php if (!empty($category_hero['description'])): ?>
                            <p class="lead <?php echo $text_color_class; ?>-50"><?php echo htmlspecialchars($category_hero['description']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($category_hero['hero_images'])): ?>
                        <div class="col-lg-5 mt-4 mt-lg-0">
                            <img src="images/assets/hero/<?php echo htmlspecialchars($category_hero['hero_images']); ?>" class="img-fluid rounded shadow-lg" alt="<?php echo htmlspecialchars($category_hero['category_name']); ?> Image">
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Existing JavaScript logic for Load More functionality
    document.addEventListener('DOMContentLoaded', function() {
        const loadMoreBtn = document.getElementById('load-more-btn');
        const productContainer = document.getElementById('product-container');
        const totalProducts = parseInt(loadMoreBtn.getAttribute('data-total')); // Get total products count

        if (loadMoreBtn && productContainer.querySelectorAll('.col').length < totalProducts) {
            loadMoreBtn.style.display = 'block'; // Ensure button is shown if there are more products

            loadMoreBtn.addEventListener('click', function() {
                this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
                this.disabled = true;

                const page = this.getAttribute('data-page');
                const nextPage = parseInt(page) + 1;
                
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('load_more', '1');
                currentUrl.searchParams.set('page', page);

                fetch(currentUrl.toString())
                    .then(response => response.text())
                    .then(html => {
                        if (html.trim().length > 0) {
                            productContainer.insertAdjacentHTML('beforeend', html);
                            this.setAttribute('data-page', nextPage);
                            this.innerHTML = 'Load More Products';
                            this.disabled = false;

                            const loadedProducts = productContainer.querySelectorAll('.col').length;
                            if (loadedProducts >= totalProducts) {
                                this.style.display = 'none';
                            } else {
                                this.style.display = 'block';
                            }

                        } else {
                            this.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        this.innerHTML = 'Error loading products.';
                    });
            });
        } else if (loadMoreBtn) {
            loadMoreBtn.style.display = 'none'; // Hide button if all products are loaded initially
        }

        // Close offcanvas when a category link is clicked
        document.querySelectorAll('.offcanvas-body .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                let offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('offcanvasNavbar'));
                if (offcanvas) {
                    offcanvas.hide();
                }
            });
        });
    });
</script>
</body>
</html>