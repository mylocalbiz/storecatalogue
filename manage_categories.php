<?php
session_start();

// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Simple password for authentication
$password = "password123"; // **CHANGE THIS TO A SECURE PASSWORD**

// If not logged in, show login form and exit
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin.php");
    exit();
}

// Database Connection
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password is empty
$dbname = "hardware_store_db"; // **CHANGE THIS to your database name**
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$edit_category_data = null;
$category_id_to_edit = null;

// ----------------------------------------------------
// ---- LOGIC FOR ADDING AND EDITING CATEGORIES ----
// ----------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_category'])) {
    $category_name = trim($_POST['category_name']);
    $description = trim($_POST['description']);
    
    if (empty($category_name)) {
        $message = "<div class='alert alert-danger mt-3'>❌ Category name cannot be empty.</div>";
    } else {
        if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
            // This is an EDIT operation
            $category_id = $_POST['category_id'];
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ? WHERE id = ?");
            $stmt->bind_param("ssi", $category_name, $description, $category_id);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success mt-3'>✅ Category updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger mt-3'>❌ Error updating category: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            // This is an ADD operation
            $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $category_name, $description);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success mt-3'>✅ New category added successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger mt-3'>❌ Error adding category: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

// ----------------------------------------------------
// ---- LOGIC FOR DELETING CATEGORIES ----
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $category_id_to_delete = $_GET['id'];
    
    // Check if any products are associated with this category ID
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $stmt_check->bind_param("i", $category_id_to_delete);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($result_check['count'] > 0) {
        $message = "<div class='alert alert-danger mt-3'>❌ Cannot delete this category. " . $result_check['count'] . " product(s) are associated with it.</div>";
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt_delete->bind_param("i", $category_id_to_delete);
        if ($stmt_delete->execute()) {
            $message = "<div class='alert alert-success mt-3'>✅ Category deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger mt-3'>❌ Error deleting category: " . $stmt_delete->error . "</div>";
        }
        $stmt_delete->close();
    }
}

// ----------------------------------------------------
// ---- LOGIC FOR EDITING (PRE-POPULATING FORM) ----
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $category_id_to_edit = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_category_data = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all categories for display
$categories_result = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background-color: #343a40; color: white; height: 100vh; padding-top: 20px; }
        .sidebar a { color: #adb5bd; padding: 10px 15px; text-decoration: none; display: block; }
        .sidebar a:hover { color: white; background-color: #495057; }
        .content { padding: 30px; }
        .card-header-add { background-color: #0d6efd; color: white; }
        .card-header-manage { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column p-3">
            <h4 class="text-white mb-4">Admin Panel</h4>
            <a href="admin.php">📊 Dashboard</a>
            <a href="admin.php#add-product">➕ Add Product</a>
            <a href="admin.php#manage-products">📝 Manage Products</a>
            <a href="manage_categories.php" class="text-white">📂 Manage Categories</a>
            <a href="admin.php?logout=true" class="mt-auto btn btn-danger">Log Out</a>
        </div>
        
        <div class="content flex-grow-1">
            <h1 class="mb-4">Manage Categories</h1>
            <?php echo $message; ?>
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="card shadow-sm">
                        <div class="card-header card-header-add">
                            <h3 class="mb-0"><?php echo $edit_category_data ? 'Edit Category' : 'Add New Category'; ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="manage_categories.php">
                                <input type="hidden" name="submit_category" value="1">
                                <?php if ($edit_category_data): ?>
                                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($edit_category_data['id']); ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="category_name" class="form-label">Category Name</label>
                                    <input type="text" class="form-control" id="category_name" name="category_name" required value="<?php echo htmlspecialchars($edit_category_data['category_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($edit_category_data['description'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><?php echo $edit_category_data ? 'Update Category' : 'Add Category'; ?></button>
                                <?php if ($edit_category_data): ?>
                                    <a href="manage_categories.php" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card shadow-sm">
                        <div class="card-header card-header-manage">
                            <h3 class="mb-0">Existing Categories</h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Category Name</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                            <?php while($row = $categories_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['id'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($row['category_name'] ?? ''); ?></td>
                                                    <td>
                                                        <a href="manage_categories.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white">Edit</a>
                                                        <a href="manage_categories.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category? This will fail if products are associated with it.');">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No categories found.</td>
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
</body>
</html>