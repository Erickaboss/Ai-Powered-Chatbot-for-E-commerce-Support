<?php require_once 'includes/admin_header.php'; ?>
<?php
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = $conn->real_escape_string(trim($_POST['name']));
        $desc = $conn->real_escape_string(trim($_POST['description']));
        $conn->query("INSERT INTO categories (name, description) VALUES ('$name','$desc')");
        $msg = '<div class="alert alert-success">Category added.</div>';
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM categories WHERE id=$id");
        $msg = '<div class="alert alert-warning">Category deleted.</div>';
    }
}
$cats = $conn->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id=p.category_id GROUP BY c.id");
?>
<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Manage Categories</h4>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#catModal">
            <i class="bi bi-plus"></i> Add Category
        </button>
    </div>
    <?= $msg ?>
    <div class="card p-3">
        <table class="table table-hover">
            <thead class="table-dark"><tr><th>ID</th><th>Name</th><th>Description</th><th>Products</th><th>Action</th></tr></thead>
            <tbody>
            <?php while ($c = $cats->fetch_assoc()): ?>
            <tr>
                <td><?= $c['id'] ?></td>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['description']) ?></td>
                <td><span class="badge bg-primary"><?= $c['product_count'] ?></span></td>
                <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete category?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header"><h5 class="modal-title">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-dark">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
