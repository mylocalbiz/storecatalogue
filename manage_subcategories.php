<?php
session_start();

// Redirect to admin login if not logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin.php");
    exit();
}

// Database Connection
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
$edit_subcategory_data = null;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add or update a subcategory
    if (isset($_POST['subcategory_name']) && isset($_POST['category_id'])) {
        $subcategory_name = trim($_POST['subcategory_name']);
        $category_id = intval($_POST['category_id']);
        
        if (empty($subcategory_name)) {
            $message = "<div class='alert alert-danger mt-3'>❌ Subcategory name cannot be empty.</div>";
        } else {
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // UPDATE operation
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE subcategories SET subcategory_name = ?, category_id = ? WHERE id = ?");
                $stmt->bind_param("sii", $subcategory_name, $category_id, $id);
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success mt-3'>✅ Subcategory updated successfully!</div>";
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ Error updating subcategory: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                // INSERT operation
                $stmt = $conn->prepare("INSERT INTO subcategories (subcategory_name, category_id) VALUES (?, ?)");
                $stmt->bind_param("si", $subcategory_name, $category_id);
                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success mt-3'>✅ New subcategory added successfully!</div>";
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ Error adding subcategory: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
    }
}

// Handle GET requests (edit and delete)
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'edit' && isset($_GET['id'])) {
        $id_to_edit = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM subcategories WHERE id = ?");
        $stmt->bind_param("i", $id_to_edit);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_subcategory_data = $result->fetch_assoc();
        $stmt->close();
    } elseif ($_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id_to_delete = intval($_GET['id']);
        $stmt = $conn->prepare("DELETE FROM subcategories WHERE id = ?");
        $stmt->bind_param("i", $id_to_delete);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success mt-3'>✅ Subcategory deleted successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger mt-3'>❌ Error deleting subcategory: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Fetch all subcategories and their parent categories for display
$subcategories_result = $conn->query("SELECT s.id, s.subcategory_name, c.category_name FROM subcategories s JOIN categories c ON s.category_id = c.id ORDER BY s.subcategory_name");

// Fetch all categories for the dropdown list
$categories_result = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name");
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subcategories - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { background-color: #343a40; color: white; height: 100vh; padding-top: 20px; }
        .sidebar a { color: #adb5bd; padding: 10px 15px; text-decoration: none; display: block; }
        .sidebar a:hover { color: white; background-color: #495057; }
        .content { padding: 30px; }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column p-3">
            <h4 class="text-white mb-4">Admin Panel</h4>
            <a href="admin.php">📊 Dashboard</a>
            <a href="manage_categories.php">📂 Manage Categories</a>
            <a href="manage_subcategories.php">📂 Manage Subcategories</a>
            <a href="admin.php?logout=true" class="mt-auto btn btn-danger">Log Out</a>
        </div>
        <div class="content flex-grow-1">
            <h1 class="mb-4">Manage Subcategories</h1>
            <?php echo $message; ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h3 class="mb-0"><?php echo $edit_subcategory_data ? 'Edit Subcategory' : 'Add New Subcategory'; ?></h3>
                        </div>
                        <div class="card-body p-4">
                            <form method="post" action="manage_subcategories.php">
                                <?php if ($edit_subcategory_data): ?>
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_subcategory_data['id']); ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="subcategory_name" class="form-label">Subcategory Name</label>
                                    <input type="text" class="form-control" id="subcategory_name" name="subcategory_name" required value="<?php echo htmlspecialchars($edit_subcategory_data['subcategory_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Parent Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select a Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['id']); ?>" <?php echo (isset($edit_subcategory_data['category_id']) && $edit_subcategory_data['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><?php echo $edit_subcategory_data ? 'Update Subcategory' : 'Add Subcategory'; ?></button>
                                <?php if ($edit_subcategory_data): ?>
                                    <a href="manage_subcategories.php" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h3 class="mb-0">Existing Subcategories</h3>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Parent Category</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($subcategories_result && $subcategories_result->num_rows > 0): ?>
                                            <?php while($row = $subcategories_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['subcategory_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                                    <td>
                                                        <a href="manage_subcategories.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info text-white">Edit</a>
                                                        <a href="manage_subcategories.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this subcategory? This will not delete products linked to it, but will remove their subcategory association.');">Delete</a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No subcategories found.</td>
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
</body>
</html>