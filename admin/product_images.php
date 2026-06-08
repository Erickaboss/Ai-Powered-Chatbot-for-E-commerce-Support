<?php
// ── Google Custom Search credentials (set in config/secrets.php) ──
// Handle AJAX requests BEFORE any HTML output
if (isset($_GET['action']) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']))) {
    require_once __DIR__ . '/../config/db.php';
    $gKey    = defined('GOOGLE_CSE_KEY')  ? GOOGLE_CSE_KEY  : '';
    $gCx     = defined('GOOGLE_CSE_CX')   ? GOOGLE_CSE_CX   : '';
    $gemKey  = defined('GEMINI_API_KEY')  ? GEMINI_API_KEY  : '';

    // ── Helper: search via free image APIs ──
    function searchImagesViaFreeAPIs(string $query): array {
        $results = [];

        // Method 1: Unsplash Source (free, no API key, returns real product images)
        $encodedQuery = urlencode($query);
        $unsplashImages = [
            [
                'url'   => "https://source.unsplash.com/300x300/?" . $encodedQuery,
                'thumb' => "https://source.unsplash.com/60x60/?" . $encodedQuery,
                'title' => $query . " (Unsplash)",
            ],
            [
                'url'   => "https://source.unsplash.com/300x300/?" . urlencode($query . " product"),
                'thumb' => "https://source.unsplash.com/60x60/?" . urlencode($query . " product"),
                'title' => $query . " product (Unsplash)",
            ],
        ];
        $results = array_merge($results, $unsplashImages);

        // Method 2: Picsum placeholder with product name overlay
        $results[] = [
            'url'   => "https://via.placeholder.com/300x300/0f3460/ffffff?text=" . urlencode(substr($query, 0, 20)),
            'thumb' => "https://via.placeholder.com/60x60/0f3460/ffffff?text=IMG",
            'title' => $query . " (placeholder)",
        ];

        return $results;
    }

    // ── Helper: search via Gemini (asks for product image search terms, then uses Unsplash) ──
    function searchImagesViaGemini(string $query, string $gemKey): array {
        // Use Gemini to get better search keywords, then fetch from Unsplash
        $prompt = "For the product \"$query\", give me 3 short image search keywords (2-3 words each) " .
                  "that would find good product photos. Return ONLY a JSON array of strings. " .
                  "Example: [\"samsung galaxy phone\", \"android smartphone\", \"mobile device\"]";

        $models = ['gemini-2.0-flash', 'gemini-1.5-flash'];
        foreach ($models as $model) {
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$gemKey}";
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['contents' => [['parts' => [['text' => $prompt]]]]]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code !== 200) continue;
            $data = json_decode($resp, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (preg_match('/\[.*?\]/s', $text, $m)) {
                $keywords = json_decode($m[0], true);
                if (is_array($keywords) && !empty($keywords)) {
                    $results = [];
                    foreach (array_slice($keywords, 0, 3) as $kw) {
                        $enc = urlencode($kw);
                        $results[] = [
                            'url'   => "https://source.unsplash.com/300x300/?" . $enc,
                            'thumb' => "https://source.unsplash.com/60x60/?" . $enc,
                            'title' => $kw,
                        ];
                    }
                    return $results;
                }
            }
        }
        return [];
    }

    // ── AJAX: search images for a product name ──
    if (isset($_GET['action']) && $_GET['action'] === 'search') {
        header('Content-Type: application/json');
        $query = trim($_GET['q'] ?? '');
        if (empty($query)) {
            echo json_encode(['error' => 'Missing search query']);
            exit;
        }

        // Try Google Custom Search first
        if (!empty($gKey) && !empty($gCx)) {
            // Try with image search
            $url = "https://www.googleapis.com/customsearch/v1?" . http_build_query([
                'key'        => $gKey,
                'cx'         => $gCx,
                'q'          => $query . ' product',
                'searchType' => 'image',
                'num'        => 6,
                'safe'       => 'active',
            ]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false]);
            $resp     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($resp, true);

            if ($httpCode === 200 && !empty($data['items'])) {
                $results = array_map(fn($i) => [
                    'url'   => $i['link'],
                    'thumb' => $i['image']['thumbnailLink'] ?? $i['link'],
                    'title' => $i['title'] ?? '',
                ], $data['items']);
                echo json_encode(['results' => $results, 'source' => 'google_cse']);
                exit;
            }
            // Log actual error for debugging
            $apiError = $data['error']['message'] ?? "HTTP $httpCode — no items returned";
            error_log("CSE search failed for '$query': $apiError");
        }

        // Fallback 1: Gemini-enhanced Unsplash search
        if (!empty($gemKey)) {
            $results = searchImagesViaGemini($query, $gemKey);
            if (!empty($results)) {
                echo json_encode(['results' => $results, 'source' => 'gemini+unsplash']);
                exit;
            }
        }

        // Fallback 2: Direct Unsplash free images (no API key needed)
        $results = searchImagesViaFreeAPIs($query);
        echo json_encode(['results' => $results, 'source' => 'unsplash']);
        exit;
    }

    // ── AJAX: save chosen image URL to DB ──
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_url') {
        header('Content-Type: application/json');
        $id  = (int)($_POST['id'] ?? 0);
        $url = trim($_POST['url'] ?? '');
        if ($id && filter_var($url, FILTER_VALIDATE_URL)) {
            $safe = $conn->real_escape_string($url);
            $stmt = $conn->prepare("UPDATE products SET image=? WHERE id=?");
            $stmt->bind_param("si", $safe, $id);
            $stmt->execute();
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['error' => 'Invalid data']);
        }
        exit;
    }
}

require_once 'includes/admin_header.php';

$gKey = defined('GOOGLE_CSE_KEY') ? GOOGLE_CSE_KEY : '';
$gCx  = defined('GOOGLE_CSE_CX')  ? GOOGLE_CSE_CX  : '';

// ── Load products that still need images ──
$filter = $_GET['filter'] ?? 'missing';
if ($filter === 'all') {
    $products = $conn->query("SELECT p.id, p.name, p.image, c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id ORDER BY p.id ASC");
} else {
    $products = $conn->query("SELECT p.id, p.name, p.image, c.name as cat FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.image NOT LIKE 'http%' ORDER BY p.id ASC");
}
$rows = $products->fetch_all(MYSQLI_ASSOC);
$total = count($rows);
?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-images me-2"></i>Auto Image Fetcher</h4>
            <small class="text-muted">Search Google Images by product name and apply with one click</small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="?filter=missing" class="btn btn-sm <?= $filter==='missing'?'btn-dark':'btn-outline-secondary' ?>">Missing Only (<?= $total ?>)</a>
            <a href="?filter=all"     class="btn btn-sm <?= $filter==='all'?'btn-dark':'btn-outline-secondary' ?>">All Products</a>
            <a href="products.php"    class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Back to Products</a>
        </div>
    </div>

    <?php if (empty($gKey) || empty($gCx)): ?>
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle me-2"></i>Google API not configured.</strong><br>
        Add these two lines to <code>config/secrets.php</code>:<br><br>
        <code>define('GOOGLE_CSE_KEY', 'YOUR_GOOGLE_API_KEY');</code><br>
        <code>define('GOOGLE_CSE_CX',  'YOUR_SEARCH_ENGINE_ID');</code><br><br>
        <a href="https://developers.google.com/custom-search/v1/overview" target="_blank">Get API Key →</a> &nbsp;|&nbsp;
        <a href="https://programmablesearchengine.google.com/" target="_blank">Create Search Engine →</a>
    </div>
    <?php endif; ?>

    <div id="global-msg"></div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>All products already have images!</div>
    <?php else: ?>

    <div class="alert alert-info py-2">
        <i class="bi bi-info-circle me-2"></i>
        Click <strong>Search</strong> on any product to find images from Google, then click an image to apply it.
        <?php if (!empty($gKey)): ?>
        &nbsp;|&nbsp; <button class="btn btn-sm btn-dark" onclick="autoFillAll()"><i class="bi bi-magic me-1"></i>Auto-fill All (top result)</button>
        <?php endif; ?>
    </div>

    <div class="row g-3" id="product-grid">
    <?php foreach ($rows as $p): ?>
    <?php
        $img = $p['image'];
        if (strpos($img, 'http') === 0)          $thumb = htmlspecialchars($img);
        elseif (strpos($img, 'products/') === 0) $thumb = SITE_URL . '/assets/images/' . htmlspecialchars($img);
        else                                      $thumb = SITE_URL . '/assets/images/products/' . htmlspecialchars($img);
    ?>
    <div class="col-12 col-md-6 col-lg-4" id="card-<?= $p['id'] ?>">
        <div class="card h-100 shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex gap-3 align-items-start mb-2">
                    <img id="thumb-<?= $p['id'] ?>" src="<?= $thumb ?>"
                         style="width:64px;height:64px;object-fit:cover;border-radius:8px;flex-shrink:0;border:2px solid #eee"
                         onerror="this.src='<?= SITE_URL ?>/assets/images/placeholder.jpg'">
                    <div style="min-width:0">
                        <div class="fw-600 text-truncate" style="font-size:.88rem"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($p['cat'] ?? '') ?> &bull; ID: <?= $p['id'] ?></div>
                        <div id="status-<?= $p['id'] ?>" style="font-size:.72rem;margin-top:2px">
                            <?php if (strpos($p['image'],'http')===0): ?>
                            <span class="text-success"><i class="bi bi-check-circle"></i> Has image</span>
                            <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle"></i> No image</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Search bar -->
                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="q-<?= $p['id'] ?>" class="form-control"
                           value="<?= htmlspecialchars($p['name']) ?>"
                           placeholder="Search query...">
                    <button class="btn btn-dark" onclick="searchImages(<?= $p['id'] ?>)">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <!-- Results grid -->
                <div id="results-<?= $p['id'] ?>" class="d-flex flex-wrap gap-1"></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
const API = '<?= SITE_URL ?>/admin/product_images.php';

async function searchImages(id) {
    const q   = document.getElementById('q-' + id).value.trim();
    const box = document.getElementById('results-' + id);
    if (!q) return;
    box.innerHTML = '<span class="text-muted" style="font-size:.75rem"><i class="bi bi-hourglass-split me-1"></i>Searching...</span>';
    try {
        const res  = await fetch(API + '?action=search&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (data.error) { box.innerHTML = '<span class="text-danger" style="font-size:.75rem">' + data.error + '</span>'; return; }
        if (!data.results.length) { box.innerHTML = '<span class="text-muted" style="font-size:.75rem">No results found.</span>'; return; }
        box.innerHTML = '';
        data.results.forEach(r => {
            const img = document.createElement('img');
            img.src   = r.thumb;
            img.title = r.title;
            img.style.cssText = 'width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid transparent;transition:.15s';
            img.onmouseover = () => img.style.borderColor = '#0f3460';
            img.onmouseout  = () => img.style.borderColor = 'transparent';
            img.onclick     = () => applyImage(id, r.url, img, box);
            img.onerror     = () => img.style.display = 'none';
            box.appendChild(img);
        });
    } catch(e) {
        box.innerHTML = '<span class="text-danger" style="font-size:.75rem">Search failed: ' + e.message + '</span>';
    }
}

async function applyImage(id, url, imgEl, box) {
    // Highlight selected
    box.querySelectorAll('img').forEach(i => i.style.borderColor = 'transparent');
    imgEl.style.borderColor = '#2ecc71';

    const fd = new FormData();
    fd.append('action', 'save_url');
    fd.append('id', id);
    fd.append('url', url);
    const res  = await fetch(API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
        document.getElementById('thumb-' + id).src = url;
        document.getElementById('status-' + id).innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Image saved!</span>';
        showMsg('✅ Image saved for product #' + id, 'success');
    } else {
        showMsg('❌ Failed to save image.', 'danger');
    }
}

async function autoFillAll() {
    const cards = document.querySelectorAll('[id^="card-"]');
    let done = 0;
    showMsg('⏳ Auto-filling ' + cards.length + ' products... please wait.', 'info');
    for (const card of cards) {
        const id = card.id.replace('card-', '');
        const q  = document.getElementById('q-' + id)?.value?.trim();
        if (!q) continue;
        try {
            const res  = await fetch(API + '?action=search&q=' + encodeURIComponent(q));
            const data = await res.json();
            if (data.results && data.results.length > 0) {
                const top = data.results[0];
                const fd  = new FormData();
                fd.append('action', 'save_url'); fd.append('id', id); fd.append('url', top.url);
                await fetch(API, { method: 'POST', body: fd });
                document.getElementById('thumb-' + id).src = top.url;
                document.getElementById('status-' + id).innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Auto-filled</span>';
                done++;
            }
        } catch(e) {}
        // Small delay to avoid hitting API rate limits
        await new Promise(r => setTimeout(r, 300));
    }
    showMsg('✅ Auto-filled ' + done + ' products!', 'success');
}

function showMsg(text, type) {
    const el = document.getElementById('global-msg');
    el.innerHTML = '<div class="alert alert-' + type + ' py-2">' + text + '</div>';
    setTimeout(() => el.innerHTML = '', 5000);
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
