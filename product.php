<?php
// Include database connection
include 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hardware Store - Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffffff;
        }
        .navbar {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .main-product-img {
            max-width: 100%;
            height: auto;
            border-radius: 1rem;
        }
        .thumbnail-img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: border 0.2s;
            border: 2px solid transparent;
        }
        .thumbnail-img:hover, .thumbnail-img.active {
            border-color: #007bff;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">🛠️ Hardware Store</a>
        <a href="shop.php" class="btn btn-outline-light d-flex align-items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shop" viewBox="0 0 16 16">
                <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35L14.73 3H13.5a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 .5.5h2a.5.5 0 0 0 .5-.5V.5a.5.5 0 0 0-.5-.5H3.73a1 1 0 0 0-.76.35L.27 3H1.5a.5.5 0 0 0 .5.5v2a.5.5 0 0 0 .5.5H5.5a.5.5 0 0 0 .5-.5v-2a.5.5 0 0 0-.5-.5zM.82 3l1.82-1.82a2 2 0 0 1 1.54-.68h8.54a2 2 0 0 1 1.54.68L15.18 3H.82zm1.5 8h-.5a.5.5 0 0 1-.5-.5V7a.5.5 0 0 1 .5-.5h.5V11zm2-1h-.5a.5.5 0 0 1-.5-.5V7a.5.5 0 0 1 .5-.5h.5v3zm-2 2h-.5a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h.5v3zm2-2h-.5a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h.5v3zm2-2h-1a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h1v3zm1 0h-1a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h1v3zm1 0h-1a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h1v3zM15.5 11h-.5a.5.5 0 0 1-.5-.5V7a.5.5 0 0 1 .5-.5h.5V11zm-2 0h-.5a.5.5 0 0 1-.5-.5V7a.5.5 0 0 1 .5-.5h.5V11zm-2 0h-1a.5.5 0 0 1-.5-.5V7a.5.5 0 0 1 .5-.5h1v3zM.82 13h14.36a1 1 0 0 1-.98 1H1.8a1 1 0 0 1-.98-1zM10.5 7h-5v3h5V7zM15.5 13h-.5a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h.5v3zM13.5 13h-.5a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h.5v3zM12.5 13h-1a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h1v3zM8.5 13h-1a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h1v3zM6.5 13h-1a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 1 .5-.5h1v3z"/>
            </svg>
            <span>Back to Shop</span>
        </a>
    </div>
</nav>

<div class="container my-5">
    <?php
    // Check if a product ID is provided in the URL
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $product_id = $_GET['id'];
        
        // Prepare and execute a safe SQL query
        $stmt = $conn->prepare("SELECT p.name, p.description, p.price, p.sale_price, p.image_url, p.on_sale, p.stock_quantity, p.tag, c.category_name, p.specs, p.brand, p.sku FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Get additional images for the gallery
            $images_sql = "SELECT image_url FROM product_images WHERE product_id = ?";
            $images_stmt = $conn->prepare($images_sql);
            $images_stmt->bind_param("i", $product_id);
            $images_stmt->execute();
            $images_result = $images_stmt->get_result();
            $product_images = [];
            while ($img_row = $images_result->fetch_assoc()) {
                $product_images[] = $img_row['image_url'];
            }
            $images_stmt->close();
            
            ?>
            <div class="row g-5">
                <div class="col-md-6">
                    <img id="main-product-image" src="<?php echo htmlspecialchars($row["image_url"]); ?>" class="main-product-img w-100" alt="<?php echo htmlspecialchars($row["name"]); ?>">
                    <?php if (!empty($product_images) || $row['image_url']): ?>
                        <div class="row mt-3 g-2">
                            <?php if ($row['image_url']): ?>
                                <div class="col-3">
                                    <img src="<?php echo htmlspecialchars($row['image_url']); ?>" class="thumbnail-img active" alt="Product thumbnail">
                                </div>
                            <?php endif; ?>
                            <?php foreach ($product_images as $img_url): ?>
                                <div class="col-3">
                                    <img src="<?php echo htmlspecialchars($img_url); ?>" class="thumbnail-img" alt="Product thumbnail">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h2 class="display-7 fw-bold text-dark"><?php echo htmlspecialchars($row["name"]); ?></h2>
                    <span class="badge bg-secondary mb-3"><?php echo htmlspecialchars($row["category_name"]); ?></span>
                    <?php if ($row['on_sale'] == 1 && $row['sale_price'] !== null) { ?>
                        <span class="badge bg-danger mb-3 ms-2">On Sale!</span>
                    <?php } ?>
                    <?php if ($row['tag']) { ?>
                        <span class="badge bg-primary mb-3 ms-2"><?php echo htmlspecialchars($row['tag']); ?></span>
                    <?php } ?>
                    <p class="lead text-dark"><?php echo htmlspecialchars($row["description"]); ?></p>
                    <hr class="my-4">
                    
                    <h2 class="fw-bold my-4">
                        <?php if ($row['on_sale'] == 1 && $row['sale_price'] !== null) { ?>
                            <span class="text-muted text-decoration-line-through me-2">R<?php echo htmlspecialchars(number_format($row["price"], 2)); ?></span>
                            <span class="text-primary">R<?php echo htmlspecialchars(number_format($row["sale_price"], 2)); ?></span>
                        <?php } else { ?>
                            <span class="text-primary">R<?php echo htmlspecialchars(number_format($row["price"], 2)); ?></span>
                        <?php } ?>
                    </h2>
                    
                    <div class="mb-4">
                        <?php if ($row['stock_quantity'] > 0) { ?>
                            <p class="text-success fw-bold mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                                </svg>
                                In Stock (<?php echo htmlspecialchars($row['stock_quantity']); ?>)
                            </p>
                            <!--<button class="btn btn-primary btn-lg fw-bold mt-3">Add to Cart</button>-->
                        <?php } else { ?>
                            <p class="text-danger fw-bold mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                                    <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
                                </svg>
                                Out of Stock
                            </p>
                            <button class="btn btn-primary btn-lg fw-bold mt-3" disabled>Add to Cart</button>
                        <?php } ?>
                    </div>
                    <?php 
                    // Display technical specifications, brand and sku if they exist
                    if ($row['specs'] || !empty($row['brand']) || !empty($row['sku'])) {
                        $specs = json_decode($row['specs'], true);
                        if (is_array($specs) && !empty($specs) || !empty($row['brand']) || !empty($row['sku'])): ?>
                            <hr class="my-4">
                            <h5 class="fw-bold mb-3">Product Details:</h5>
                            <table class="table table-bordered">
                                <tbody>
                                    <?php
                                    // Add Brand and SKU to specs table
                                    if (!empty($row['brand'])) { ?>
                                        <tr>
                                            <th scope="row">Brand</th>
                                            <td><?php echo htmlspecialchars($row['brand']); ?></td>
                                        </tr>
                                    <?php }
                                    if (!empty($row['sku'])) { ?>
                                        <tr>
                                            <th scope="row">SKU</th>
                                            <td><?php echo htmlspecialchars($row['sku']); ?></td>
                                        </tr>
                                    <?php }
                                    foreach ($specs as $key => $value): ?>
                                        <tr>
                                            <th scope="row"><?php echo htmlspecialchars($key); ?></th>
                                            <td><?php echo htmlspecialchars($value); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; 
                    }
                    ?>
                </div>
            </div>
            <div class="mt-5">
                <a href="shop.php" class="btn btn-outline-secondary">← Back to all products</a>
            </div>
            <?php
        } else {
            echo "<p class='text-center'>Product not found.</p>";
        }
        $stmt->close();
    } else {
        echo "<p class='text-center'>Invalid product ID.</p>";
    }
    $conn->close();
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mainImage = document.getElementById('main-product-image');
        const thumbnails = document.querySelectorAll('.thumbnail-img');

        if (mainImage && thumbnails.length > 0) {
            thumbnails.forEach(thumbnail => {
                thumbnail.addEventListener('click', function() {
                    mainImage.src = this.src;
                    thumbnails.forEach(thumb => thumb.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }
    });
</script>
</body>
</html>