<?php
session_start();

// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle logout action
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Handle login form submission
if (isset($_POST['username']) && isset($_POST['password'])) {
    // Database Connection for login
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "hardware_store_db";
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Retrieve the hashed password from the database
    $stmt = $conn->prepare("SELECT password FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $_POST['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    // Verify the password
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['is_admin'] = true;
        header("Location: admin.php");
        exit();
    } else {
        $login_error = "Invalid username or password.";
    }
}

// If not logged in, show login form and exit
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container d-flex justify-content-center align-items-center" style="height: 100vh;">
            <div class="card p-4 shadow-sm" style="width: 400px;">
                <h2 class="card-title text-center mb-4">Admin Login</h2>
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $login_error; ?></div>
                <?php endif; ?>
                <form method="post" action="admin.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Database Connection for the rest of the admin panel
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hardware_store_db";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$edit_product_data = null;
$product_id_to_edit = null;
$search_query = '';
$product_images = [];

// Check if the images directory exists, if not, create it
$image_upload_dir = 'images/';
if (!is_dir($image_upload_dir)) {
    mkdir($image_upload_dir, 0755, true);
}

// Function to create and save a thumbnail
function createThumbnail($source_path, $dest_path, $thumb_width, $thumb_height) {
    list($width, $height, $image_type) = getimagesize($source_path);
    $new_width = $width;
    $new_height = $height;

    if ($width > $thumb_width || $height > $thumb_height) {
        $ratio = min($thumb_width / $width, $thumb_height / $height);
        $new_width = $width * $ratio;
        $new_height = $height * $ratio;
    }

    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }

    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $dest_path);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $dest_path);
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $dest_path);
            break;
    }

    imagedestroy($new_image);
    imagedestroy($source_image);
    return true;
}

// Function to handle image uploads and return the new filename
function handle_image_upload($file_input_name) {
    global $image_upload_dir;
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] != 0) {
        return null;
    }

    $file_name = $_FILES[$file_input_name]['name'];
    $file_tmp = $_FILES[$file_input_name]['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($file_ext, $allowed_extensions)) {
        return false;
    }

    $unique_filename = uniqid('img_', true) . '.' . $file_ext;
    $upload_path = $image_upload_dir . $unique_filename;
    
    if (move_uploaded_file($file_tmp, $upload_path)) {
        return $unique_filename;
    }
    return false;
}

// ----------------------------------------------------
// ---- LOGIC FOR DELETING IMAGES FROM GALLERY ----
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_gallery_image' && isset($_GET['image_id'])) {
    $image_id = $_GET['image_id'];
    $product_id_to_edit = $_GET['product_id'];

    // Get image URL to delete from server
    $stmt_select = $conn->prepare("SELECT image_url FROM product_images WHERE id = ?");
    $stmt_select->bind_param("i", $image_id);
    $stmt_select->execute();
    $result_img = $stmt_select->get_result();
    if ($row_img = $result_img->fetch_assoc()) {
        if (file_exists($row_img['image_url'])) {
            @unlink($row_img['image_url']);
        }
    }
    $stmt_select->close();

    $stmt_delete = $conn->prepare("DELETE FROM product_images WHERE id = ?");
    $stmt_delete->bind_param("i", $image_id);
    if ($stmt_delete->execute()) {
        $message = "<div class='alert alert-success mt-3'>✅ Image deleted successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger mt-3'>❌ Error deleting image: " . $stmt_delete->error . "</div>";
    }
    $stmt_delete->close();
    header("Location: admin.php?action=edit&id=$product_id_to_edit");
    exit();
}

// ----------------------------------------------------
// ---- LOGIC FOR EDITING AND DELETING PRODUCTS ----
// ----------------------------------------------------
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $product_id_to_edit = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id_to_edit);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_product_data = $result->fetch_assoc();
        $stmt->close();
        
        // Fetch gallery images for this product
        $stmt_gallery = $conn->prepare("SELECT id, image_url FROM product_images WHERE product_id = ?");
        $stmt_gallery->bind_param("i", $product_id_to_edit);
        $stmt_gallery->execute();
        $gallery_result = $stmt_gallery->get_result();
        while($row = $gallery_result->fetch_assoc()) {
            $product_images[] = $row;
        }
        $stmt_gallery->close();

    } elseif ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $product_id_to_delete = $_GET['id'];
        
        // First, get all image file paths to delete from server
        $stmt_select_images = $conn->prepare("SELECT image_url, image_filename_thumb FROM products WHERE id = ?");
        $stmt_select_images->bind_param("i", $product_id_to_delete);
        $stmt_select_images->execute();
        $result_images = $stmt_select_images->get_result();
        $product_to_delete = $result_images->fetch_assoc();
        $stmt_select_images->close();
        
        $stmt_gallery_images = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
        $stmt_gallery_images->bind_param("i", $product_id_to_delete);
        $stmt_gallery_images->execute();
        $gallery_images_result = $stmt_gallery_images->get_result();

        if ($product_to_delete) {
            @unlink($product_to_delete['image_url']);
            @unlink($image_upload_dir . $product_to_delete['image_filename_thumb']);
        }
        while ($row = $gallery_images_result->fetch_assoc()) {
            @unlink($row['image_url']);
        }
        $stmt_gallery_images->close();
        
        $stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt_delete->bind_param("i", $product_id_to_delete);
        if ($stmt_delete->execute()) {
            $message = "<div class='alert alert-success mt-3'>✅ Product and its images were deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger mt-3'>❌ Error deleting product: " . $stmt_delete->error . "</div>";
        }
        $stmt_delete->close();
    }
}

// ----------------------------------------------------
// ---- LOGIC FOR SUBMITTING FORMS (ADD, EDIT, GALLERY) ----
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Handle main product form submission (Add/Edit)
    if (isset($_POST['submit_product'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        // Set a default description if the field is empty
        if (empty($description)) {
            $description = "No additional info";
        }
        
        // Correctly handle price and sale_price to avoid type issues with bind_param
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        $sale_price = isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? filter_var($_POST['sale_price'], FILTER_VALIDATE_FLOAT) : null;
        
        $stock_quantity = filter_var($_POST['stock_quantity'], FILTER_VALIDATE_INT);
        $tag = $_POST['tag'];
        // If the tag is an empty string, set it to NULL for the database
        $tag = ($tag === '') ? null : $tag;
        $category_id = intval($_POST['category_id']);
        $subcategory_id = isset($_POST['subcategory_id']) && $_POST['subcategory_id'] !== '' ? intval($_POST['subcategory_id']) : null;

        $featured = isset($_POST['featured']) ? 1 : 0;
        $on_sale = isset($_POST['on_sale']) ? 1 : 0;
        $brand = trim($_POST['brand']);
        $sku = trim($_POST['sku']);
        
        // Handle new 'specs' field (JSON data)
        $specs_data = $_POST['specs'];
        $specs_json = null;
        if (!empty($specs_data)) {
            $decoded_specs = json_decode($specs_data, true);
            if ($decoded_specs !== null) {
                $specs_json = json_encode($decoded_specs);
            } else {
                $message = "<div class='alert alert-danger mt-3'>❌ Invalid JSON for Technical Specifications.</div>";
            }
        }
        
        $image_filename = null;
        $image_filename_thumb = null;
        $image_url = null;
        $image_uploaded = false;
        
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $image_filename = handle_image_upload('product_image');
            $image_url = 'images/' . $image_filename;
            if ($image_filename) {
                $thumb_filename = 'thumb_' . $image_filename;
                if (createThumbnail($image_upload_dir . $image_filename, $image_upload_dir . $thumb_filename, 250, 250)) {
                    $image_filename_thumb = $thumb_filename;
                    $image_uploaded = true;
                }
            }
        }
        
        if ($message === '') {
            if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
                // UPDATE operation
                $product_id = $_POST['product_id'];
                
                $sql = "UPDATE products SET name=?, description=?, price=?, sale_price=?, category_id=?, subcategory_id=?, featured=?, on_sale=?, stock_quantity=?, tag=?, specs=?, brand=?, sku=?";
                $params = "ssddiiiiissss";
                $values = [&$name, &$description, &$price, &$sale_price, &$category_id, &$subcategory_id, &$featured, &$on_sale, &$stock_quantity, &$tag, &$specs_json, &$brand, &$sku];
                
                if ($image_uploaded) {
                    $stmt_select_images = $conn->prepare("SELECT image_url, image_filename_thumb FROM products WHERE id = ?");
                    $stmt_select_images->bind_param("i", $product_id);
                    $stmt_select_images->execute();
                    $result_images = $stmt_select_images->get_result();
                    $old_images = $result_images->fetch_assoc();
                    $stmt_select_images->close();
                
                    if ($old_images) {
                        @unlink($old_images['image_url']);
                        @unlink($image_upload_dir . $old_images['image_filename_thumb']);
                    }
                    
                    $sql .= ", image_url=?, image_filename_thumb=?";
                    $params .= "ss";
                    $values[] = &$image_url;
                    $values[] = &$image_filename_thumb;
                }
                
                $sql .= " WHERE id=?";
                $params .= "i";
                $values[] = &$product_id;
                
                $stmt = $conn->prepare($sql);
                // Corrected bind_param call
                $stmt->bind_param($params, ...$values);
                
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success mt-3'>✅ Product updated successfully!</div>";
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ Error updating product: " . $stmt->error . "</div>";
                }
                $stmt->close();
                
            } else {
                // INSERT operation
                if ($image_uploaded) {
                    $sql = "INSERT INTO products (name, description, price, sale_price, image_url, image_filename_thumb, category_id, subcategory_id, featured, on_sale, stock_quantity, tag, specs, brand, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    
                    // Corrected bind_param call with 15 type characters
                    $stmt->bind_param("ssddssiiiiissss", $name, $description, $price, $sale_price, $image_url, $image_filename_thumb, $category_id, $subcategory_id, $featured, $on_sale, $stock_quantity, $tag, $specs_json, $brand, $sku);

                    if ($stmt->execute()) {
                        $message = "<div class='alert alert-success mt-3'>✅ New product added successfully!</div>";
                    } else {
                        $message = "<div class='alert alert-danger mt-3'>❌ Error adding product: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ Main product image is required.</div>";
                }
            }
        }
    }
    
    // Handle gallery image upload
    if (isset($_POST['upload_gallery_images']) && isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        if (isset($_FILES['gallery_images'])) {
            $files = $_FILES['gallery_images'];
            $file_count = count($files['name']);
            $upload_success = 0;
            $upload_errors = [];
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === 0) {
                    $unique_filename = uniqid('gallery_', true) . '_' . basename($files['name'][$i]);
                    $upload_path = $image_upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $upload_path)) {
                        $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                        $image_url = $image_upload_dir . $unique_filename;
                        $stmt->bind_param("is", $product_id, $image_url);
                        $stmt->execute();
                        $stmt->close();
                        $upload_success++;
                    } else {
                        $upload_errors[] = $files['name'][$i];
                    }
                }
            }
            if ($upload_success > 0) {
                $message = "<div class='alert alert-success mt-3'>✅ Successfully uploaded $upload_success gallery image(s)!</div>";
            }
            if (!empty($upload_errors)) {
                $message .= "<div class='alert alert-warning mt-3'>⚠️ The following images failed to upload: " . implode(", ", $upload_errors) . "</div>";
            }
            header("Location: admin.php?action=edit&id=$product_id");
            exit();
        }
    }
}

// ----------------------------------------------------
// ---- LOGIC FOR SEARCHING PRODUCTS AND FETCHING DATA FOR DROPDOWNS ----
// ----------------------------------------------------

// Fetch existing categories for the form dropdown
$categories_result = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch existing subcategories for the form dropdown and group them by category
$subcategories_result = $conn->query("SELECT id, subcategory_name, category_id FROM subcategories ORDER BY subcategory_name");
$subcategories = [];
$subcategories_by_category = [];
if ($subcategories_result) {
    while ($row = $subcategories_result->fetch_assoc()) {
        $subcategories[] = $row;
        if (!isset($subcategories_by_category[$row['category_id']])) {
            $subcategories_by_category[$row['category_id']] = [];
        }
        $subcategories_by_category[$row['category_id']][] = $row;
    }
}

// Prepare the JSON data for the JavaScript
$subcategories_json = json_encode($subcategories_by_category);

// ----------------------------------------------------
// ---- LOGIC FOR SEARCHING PRODUCTS ----
// ----------------------------------------------------
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
    $sql_products = "SELECT p.*, c.category_name, s.subcategory_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON p.subcategory_id = s.id WHERE p.name LIKE ? ORDER BY p.id DESC";
    $search_param = "%" . $search_query . "%";
    $stmt = $conn->prepare($sql_products);
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $products_result = $stmt->get_result();
    $stmt->close();
} else {
    $sql_products = "SELECT p.*, c.category_name, s.subcategory_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN subcategories s ON p.subcategory_id = s.id ORDER BY p.id DESC";
    $products_result = $conn->query($sql_products);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background-color: #343a40; color: white; height: 100vh; padding-top: 20px; }
        .sidebar a { color: #adb5bd; padding: 10px 15px; text-decoration: none; display: block; }
        .sidebar a:hover { color: white; background-color: #495057; }
        .content { padding: 30px; }
        .card-header-add { background-color: #0d6efd; color: white; }
        .card-header-manage { background-color: #6c757d; color: white; }
        .gallery-thumb { width: 100px; height: 100px; object-fit: cover; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column p-3">
            <h4 class="text-white mb-4">Admin Panel</h4>
            <a href="admin.php">📊 Dashboard</a>
            <a href="admin.php#add-product">➕ Add Product</a>
            <a href="admin.php#manage-products">📝 Manage Products</a>
            <a href="manage_categories.php">📂 Manage Categories</a>
            <a href="manage_subcategories.php">📂 Manage Subcategories</a>
            <a href="admin.php?logout=true" class="mt-auto btn btn-danger">Log Out</a>
        </div>
        
        <div class="content flex-grow-1">
            <h1 class="mb-4">Admin Dashboard</h1>
            <?php echo $message; ?>
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="card shadow-sm" id="add-product">
                        <div class="card-header card-header-add">
                            <h3 class="mb-0"><?php echo $edit_product_data ? 'Edit Product' : 'Add New Product'; ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="admin.php" enctype="multipart/form-data">
                                <input type="hidden" name="submit_product" value="1">
                                <?php if ($edit_product_data): ?>
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($edit_product_data['id']); ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($edit_product_data['name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_product_data['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="specs" class="form-label">Technical Specifications (JSON format)</label>
                                    <textarea class="form-control" id="specs" name="specs" rows="5" placeholder='{"Weight": "1.5 kg", "Dimensions": "20x10x5 cm"}'><?php echo htmlspecialchars(json_encode(json_decode($edit_product_data['specs'] ?? '{}'), JSON_PRETTY_PRINT)); ?></textarea>
                                    <div class="form-text">Enter specifications as a JSON object. For example: `{"Weight": "1.5 kg", "Color": "Black"}`</div>
                                </div>
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($edit_product_data['brand'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU</label>
                                    <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($edit_product_data['sku'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" required value="<?php echo htmlspecialchars($edit_product_data['price'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="sale_price" class="form-label">Sale Price (optional)</label>
                                    <input type="number" step="0.01" class="form-control" id="sale_price" name="sale_price" value="<?php echo htmlspecialchars($edit_product_data['sale_price'] ?? ''); ?>">
                                    <div class="form-text">Leave blank if the product is not on sale.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                    <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required value="<?php echo htmlspecialchars($edit_product_data['stock_quantity'] ?? '0'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="product_image" class="form-label">Main Product Image</label>
                                    <input class="form-control" type="file" id="product_image" name="product_image">
                                    <?php if ($edit_product_data && $edit_product_data['image_url']): ?>
                                        <div class="mt-2">
                                            <p>Current Image:</p>
                                            <img src="<?php echo htmlspecialchars($edit_product_data['image_url']); ?>" alt="Current Product Image" style="max-width: 150px; height: auto;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category_id" required>
                                        <option value="">Select a category</option>
                                        <?php foreach ($categories as $cat) { ?>
                                            <option value="<?php echo htmlspecialchars($cat['id']); ?>" <?php echo (isset($edit_product_data['category_id']) && $edit_product_data['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="form-text">To add a new category, go to <a href="manage_categories.php">Manage Categories</a>.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="subcategory" class="form-label">Subcategory (optional)</label>
                                    <select class="form-select" id="subcategory" name="subcategory_id">
                                        <option value="">Select a subcategory</option>
                                    </select>
                                    <div class="form-text">Subcategories will appear after a category is selected.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="tag" class="form-label">Product Tag (optional)</label>
                                    <select class="form-select" id="tag" name="tag">
                                        <option value="">None</option>
                                        <?php
                                        $tags = ["New Arrival", "Best Seller", "Low Stock"];
                                        foreach ($tags as $tag_option) {
                                            $selected = (isset($edit_product_data['tag']) && $edit_product_data['tag'] == $tag_option) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($tag_option) . "' $selected>" . htmlspecialchars($tag_option) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="featured" name="featured" <?php echo (isset($edit_product_data['featured']) && $edit_product_data['featured'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">Featured Product</label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="on_sale" name="on_sale" <?php echo (isset($edit_product_data['on_sale']) && $edit_product_data['on_sale'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="on_sale">On Sale</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><?php echo $edit_product_data ? 'Update Product' : 'Add Product'; ?></button>
                                <?php if ($edit_product_data): ?>
                                    <a href="admin.php#manage-products" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <?php if ($edit_product_data): ?>
                        <div class="card shadow-sm mt-4">
                            <div class="card-header card-header-add">
                                <h3 class="mb-0">Product Image Gallery</h3>
                            </div>
                            <div class="card-body p-4">
                                <form action="admin.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($edit_product_data['id']); ?>">
                                    <input type="hidden" name="upload_gallery_images" value="1">
                                    <div class="mb-3">
                                        <label for="gallery_images" class="form-label">Add more images to the gallery</label>
                                        <input class="form-control" type="file" id="gallery_images" name="gallery_images[]" multiple>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Upload Images</button>
                                </form>
                                <hr class="my-4">
                                <h5 class="mb-3">Existing Gallery Images</h5>
                                <div class="row g-2">
                                    <?php if (!empty($product_images)): ?>
                                        <?php foreach ($product_images as $image): ?>
                                            <div class="col-sm-4 position-relative">
                                                <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="Gallery Image" class="img-fluid gallery-thumb">
                                                <a href="admin.php?action=delete_gallery_image&image_id=<?php echo $image['id']; ?>&product_id=<?php echo $edit_product_data['id']; ?>" 
                                                   class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                                   onclick="return confirm('Are you sure you want to delete this image?');"
                                                   style="z-index: 10;">
                                                   X
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p>No additional gallery images for this product.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-7">
                    <div class="card shadow-sm" id="manage-products">
                        <div class="card-header card-header-manage">
                            <h3 class="mb-0">Manage Products</h3>
                        </div>
                        <div class="card-body p-4">
                            <form method="get" action="admin.php#manage-products" class="mb-3">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search by product name..." name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">🔍 Search</button>
                                    <a href="admin.php#manage-products" class="btn btn-outline-secondary">Clear</a>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Product Name</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Category</th>
                                            <th>Subcategory</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($products_result && $products_result->num_rows > 0): ?>
                                            <?php while($row = $products_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="Product Image" style="max-width: 50px; height: auto;"></td>
                                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td>$<?php echo number_format($row['price'], 2); ?></td>
                                                    <td>
                                                        <?php
                                                        echo htmlspecialchars($row['stock_quantity']);
                                                        if ($row['stock_quantity'] <= 0) {
                                                            echo ' <span class="badge bg-danger">Out</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['subcategory_name'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <a href="admin.php?action=edit&id=<?php echo $row['id']; ?>#add-product" class="btn btn-sm btn-info text-white">Edit</a>
                                                        <a href="admin.php?action=delete&id=<?php echo $row['id']; ?>#manage-products" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No products found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Get the category and subcategory dropdown elements
    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');
    const allSubcategories = <?php echo $subcategories_json; ?>;
    const currentSubcategoryId = <?php echo isset($edit_product_data['subcategory_id']) ? json_encode($edit_product_data['subcategory_id']) : 'null'; ?>;
    
    // Function to populate the subcategory dropdown
    function populateSubcategories(categoryId) {
        // Clear existing options
        subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';
        subcategorySelect.disabled = true;

        if (categoryId && allSubcategories[categoryId]) {
            const subcategories = allSubcategories[categoryId];
            subcategories.forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.id;
                option.textContent = subcategory.subcategory_name;
                // Pre-select the subcategory if editing a product
                if (currentSubcategoryId && subcategory.id == currentSubcategoryId) {
                    option.selected = true;
                }
                subcategorySelect.appendChild(option);
            });
            subcategorySelect.disabled = false;
        }
    }

    // Add event listener to the category dropdown
    categorySelect.addEventListener('change', (e) => {
        populateSubcategories(e.target.value);
    });

    // Run on page load to pre-populate the subcategory dropdown if a category is already selected (e.g., on edit mode)
    if (categorySelect.value) {
        populateSubcategories(categorySelect.value);
    }
    </script>
</body>
</html>