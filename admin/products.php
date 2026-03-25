<?php require_once 'includes/admin_header.php'; ?>
<?php
$msg = '';
$upload_dir = __DIR__ . '/../assets/images/products/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name  = $conn->real_escape_string(trim($_POST['name']));
        $desc  = $conn->real_escape_string(trim($_POST['description']));
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $cat   = (int)$_POST['category_id'];
        $image = $conn->real_escape_string(trim($_POST['existing_image'] ?: 'placeholder.jpg'));

        // Handle image upload
        if (!empty($_FILES['image_file']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed) && $_FILES['image_file']['size'] < 5000000) {
                $filename = 'prod_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename)) {
                    $image = $filename;
                } else {
                    $msg = '<div class="alert alert-danger">Upload failed. Check folder permissions.</div>';
                }
            } else {
                $msg = '<div class="alert alert-danger">Invalid file. Use JPG/PNG/GIF/WEBP under 5MB.</div>';
            }
        }

        if (empty($msg)) {
            if ($action === 'add') {
                $conn->query("INSERT INTO products (name,description,price,image,category_id,stock) VALUES ('$name','$desc',$price,'$image',$cat,$stock)");
                $msg = '<div class="alert alert-success">Product added successfully.</div>';
            } else {
                $id = (int)$_POST['id'];
                $conn->query("UPDATE products SET name='$name',description='$desc',price=$price,image='$image',category_id=$cat,stock=$stock WHERE id=$id");
                $msg = '<div class="alert alert-success">Product updated successfully.</div>';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM products WHERE id=$id");
        $msg = '<div class="alert alert-warning">Product deleted.</div>';
    }

    // Quick image upload
    if ($action === 'upload_image') {
        $id = (int)$_POST['id'];
        if (!empty($_FILES['quick_image']['name'])) {
            $ext     = strtolower(pathinfo($_FILES['quick_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed) && $_FILES['quick_image']['size'] < 5000000) {
                $filename = 'p' . $id . '.' . $ext;
                if (move_uploaded_file($_FILES['quick_image']['tmp_name'], $upload_dir . $filename)) {
                    $conn->query("UPDATE products SET image='$filename' WHERE id=$id");
                    $msg = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Image uploaded for product #' . $id . '.</div>';
                } else {
                    $msg = '<div class="alert alert-danger">Upload failed. Check folder permissions on assets/images/products/</div>';
                }
            } else {
                $msg = '<div class="alert alert-danger">Invalid file. Use JPG/PNG/GIF/WEBP under 5MB.</div>';
            }
        }
    }
}

$products   = $conn->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.id DESC");
$categories = $conn->query("SELECT * FROM categories");
$cats_arr   = $categories->fetch_all(MYSQLI_ASSOC);

$edit = null;
if (!empty($_GET['edit'])) {
    $eid  = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM products WHERE id=$eid")->fetch_assoc();
}
?>
<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Manage Products</h4>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="bi bi-plus"></i> Add Product
        </button>
    </div>
    <?= $msg ?>
    <div class="card p-3">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr><th>ID</th><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php while ($p = $products->fetch_assoc()): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?php
                    $img = $p['image'];
                    if (strpos($img, 'http') === 0) {
                        $imgSrc = htmlspecialchars($img);
                    } elseif (strpos($img, 'products/') === 0) {
                        $imgSrc = SITE_URL . '/assets/images/' . htmlspecialchars($img);
                    } else {
                        $imgSrc = SITE_URL . '/assets/images/products/' . htmlspecialchars($img);
                    }
                ?><img src="<?= $imgSrc ?>"
                         style="width:55px;height:55px;object-fit:cover;border-radius:8px"
                         onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'"></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
                <td>RWF <?= number_format($p['price'], 2) ?></td>
                <td><?= $p['stock'] <= 5 ? '<span class="text-danger fw-bold">' . $p['stock'] . '</span>' : $p['stock'] ?></td>
                <td>
                    <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <button class="btn btn-sm btn-outline-success" title="Upload Image"
                            onclick="openImgUpload(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">
                        <i class="bi bi-image"></i>
                    </button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this product?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><?= $edit ? 'Edit' : 'Add' ?> Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?= $edit ? 'edit' : 'add' ?>">
                    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($edit['image'] ?? 'placeholder.jpg') ?>">
                    <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
                    <div class="mb-2">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col mb-2">
                            <label class="form-label">Price (RWF)</label>
                            <input type="number" name="price" class="form-control" step="0.01" value="<?= $edit['price'] ?? '' ?>" required>
                        </div>
                        <div class="col mb-2">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-control" value="<?= $edit['stock'] ?? 0 ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <?php foreach ($cats_arr as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($edit['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Product Image</label>
                        <?php if (!empty($edit['image'])): ?>
                        <div class="mb-2">
                            <?php
                            $eimg = $edit['image'];
                            if (strpos($eimg, 'http') === 0) {
                                $eimgSrc = htmlspecialchars($eimg);
                            } elseif (strpos($eimg, 'products/') === 0) {
                                $eimgSrc = SITE_URL . '/assets/images/' . htmlspecialchars($eimg);
                            } else {
                                $eimgSrc = SITE_URL . '/assets/images/products/' . htmlspecialchars($eimg);
                            }
                            ?>
                            <img id="current-img" src="<?= $eimgSrc ?>"
                                 style="height:80px;border-radius:8px;object-fit:cover"
                                 onerror="this.style.display='none'">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="image_file" class="form-control" accept="image/*"
                               onchange="previewImg(this)">
                        <img id="img-preview" src="" style="display:none;height:80px;margin-top:8px;border-radius:8px;object-fit:cover">
                        <small class="text-muted">JPG, PNG, WEBP — max 5MB. Leave empty to keep current.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-dark"><i class="bi bi-save"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewImg(input) {
    const preview = document.getElementById('img-preview');
    const current = document.getElementById('current-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (current) current.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openImgUpload(id, name) {
    document.getElementById('quick-upload-id').value   = id;
    document.getElementById('quick-upload-name').textContent = name;
    document.getElementById('quick-preview').style.display = 'none';
    document.getElementById('quick-file').value = '';
    new bootstrap.Modal(document.getElementById('quickImgModal')).show();
}
</script>

<?php if ($edit): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('productModal')).show();
    });
</script>
<?php endif; ?>

<!-- Quick Image Upload Modal -->
<div class="modal fade" id="quickImgModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_image">
                <input type="hidden" name="id" id="quick-upload-id">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-image me-2"></i>Upload Image</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="quick-upload-name"></p>
                    <input type="file" name="quick_image" id="quick-file" class="form-control mb-2"
                           accept="image/*" required
                           onchange="const r=new FileReader();r.onload=e=>{const i=document.getElementById('quick-preview');i.src=e.target.result;i.style.display='block'};r.readAsDataURL(this.files[0])">
                    <img id="quick-preview" src="" style="display:none;width:100%;border-radius:8px;object-fit:cover;max-height:160px">
                    <small class="text-muted">JPG, PNG, WEBP — max 5MB</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-success btn-sm"><i class="bi bi-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
