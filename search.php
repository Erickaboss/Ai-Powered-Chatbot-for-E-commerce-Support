<?php
/**
 * Site Search Page - Google Custom Search Engine
 * Powered by Google CSE for comprehensive site-wide search
 */
require_once 'includes/header.php';

// Get search query from URL
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Search Header -->
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3" style="color: var(--primary);">
                    <i class="bi bi-search me-2"></i>Search Our Site
                </h1>
                <p class="lead text-muted">
                    Find products, information, and more using Google Custom Search
                </p>
            </div>
            
            <!-- Quick Links to Product Search -->
            <div class="card shadow-sm mb-4 border-0" style="border-radius: 12px; background: linear-gradient(135deg, rgba(15,52,96,0.05), rgba(233,69,96,0.05));">
                <div class="card-body p-4">
                    <div class="row align-items-center g-3">
                        <div class="col-md-8">
                            <h5 class="mb-2" style="color: var(--primary);">
                                <i class="bi bi-grid me-2"></i>Looking for Products?
                            </h5>
                            <p class="mb-0 text-muted">
                                Try our AI-powered product search with advanced filters
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="<?= SITE_URL ?>/products.php" class="btn btn-primary">
                                <i class="bi bi-shop me-2"></i>Browse All Products
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Google Custom Search Box -->
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-body p-4">
                    
                    <!-- Search Form -->
                    <form action="" method="GET" class="mb-4">
                        <div class="input-group input-group-lg">
                            <input type="text" 
                                   name="q" 
                                   class="form-control border-0" 
                                   placeholder="Search anything..." 
                                   value="<?= htmlspecialchars($searchQuery) ?>"
                                   aria-label="Search query"
                                   style="background: #f8f9fa; border-radius: 8px 0 0 8px;">
                            <button type="submit" class="btn btn-primary px-4" style="border-radius: 0 8px 8px 0;">
                                <i class="bi bi-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                    
                    <!-- Google CSE Results -->
                    <?php if ($searchQuery): ?>
                    <div class="google-cse-results">
                        <!-- Google CSE will inject results here -->
                        <script async src="https://cse.google.com/cse.js?cx=60ebce9ef20834c3f"></script>
                        <div class="gcse-searchresults-only" data-queryParameterName="q"></div>
                    </div>
                    <?php else: ?>
                    <!-- Search Tips (shown when no query) -->
                    <div class="search-tips text-center py-5">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="tip-card p-4">
                                    <i class="bi bi-phone display-4 mb-3" style="color: var(--accent);"></i>
                                    <h5>Product Search</h5>
                                    <p class="text-muted mb-0">Find specific products by name, brand, or category</p>
                                    <small class="text-muted">Try: "Samsung Galaxy A54"</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="tip-card p-4">
                                    <i class="bi bi-cart display-4 mb-3" style="color: var(--primary);"></i>
                                    <h5>Order Information</h5>
                                    <p class="text-muted mb-0">Track orders, check status, view history</p>
                                    <small class="text-muted">Try: "track order #123"</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="tip-card p-4">
                                    <i class="bi bi-question-circle display-4 mb-3" style="color: var(--gold);"></i>
                                    <h5>Help & Support</h5>
                                    <p class="text-muted mb-0">Delivery info, payment methods, returns</p>
                                    <small class="text-muted">Try: "delivery time Rwanda"</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-5">
                            <p class="text-muted">
                                <strong>Popular searches:</strong>
                                <a href="?q=phones" class="badge bg-secondary me-2">Phones</a>
                                <a href="?q=laptops" class="badge bg-secondary me-2">Laptops</a>
                                <a href="?q=delivery" class="badge bg-secondary me-2">Delivery</a>
                                <a href="?q=payment" class="badge bg-secondary me-2">Payment</a>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            
            <!-- Back to Home -->
            <div class="text-center mt-4">
                <a href="<?= SITE_URL ?>/index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Home
                </a>
            </div>
            
        </div>
    </div>
</div>

<!-- Custom Styling for Google CSE -->
<style>
/* Override Google CSE default styles to match your theme */
.gsc-control-cse {
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
}

.gsc-input-box {
    border-radius: 8px !important;
    border: 1px solid #ddd !important;
}

.gsc-search-button-v2 {
    border-radius: 8px !important;
    background: var(--primary) !important;
    border: none !important;
}

.gsc-search-button-v2:hover {
    background: var(--accent) !important;
}

.gsc-result-info {
    color: var(--primary) !important;
    font-weight: 600 !important;
}

.gs-title {
    color: var(--primary) !important;
    text-decoration: none !important;
}

.gs-title:hover {
    color: var(--accent) !important;
    text-decoration: underline !important;
}

.gs-snippet {
    color: #666 !important;
    font-size: 0.9rem !important;
}

.gsc-url-top, .gsc-url-bottom {
    color: var(--gold) !important;
    font-size: 0.85rem !important;
}

/* Search tips styling */
.tip-card {
    background: white;
    border-radius: 12px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.tip-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.badge {
    padding: 8px 16px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.badge:hover {
    background: var(--accent) !important;
    transform: scale(1.05);
}
</style>

<script>
// Auto-focus on search input if query exists
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="q"]');
    if (searchInput && searchInput.value.trim()) {
        // Highlight the search box when there's a query
        searchInput.parentElement.classList.add('shadow');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
