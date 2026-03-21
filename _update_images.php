<?php
require_once 'config/db.php';

// picsum.photos — always works, no API key, no deprecation
// We use category-specific Unsplash collection IDs via picsum for variety
// Format: https://picsum.photos/seed/{seed}/400/400

// Better: use specific real product images from open sources per category
$categoryImages = [
    1  => [ // Smartphones & Tablets
        'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1565849904461-04a58ad377e0?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=400&h=400&fit=crop',
        'https://images.unsplash.com/photo-1580910051074-3eb694886505?w=400&h=400&fit=crop',
    ],
    2  => [ // Laptops & Computers
        'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=400&h=400&fit=crop',
        