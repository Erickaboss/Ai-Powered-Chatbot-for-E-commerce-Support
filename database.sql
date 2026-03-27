-- ============================================================
-- ShopAI Rwanda — Full E-Commerce Dataset
-- Currency: Rwandan Franc (RWF)
-- ============================================================

CREATE DATABASE IF NOT EXISTS ecommerce_chatbot;
USE ecommerce_chatbot;

-- Drop tables in correct order (foreign keys first)
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS chatbot_logs;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer','admin') DEFAULT 'customer',
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(12,2) NOT NULL,
    image VARCHAR(255) DEFAULT 'placeholder.jpg',
    category_id INT,
    stock INT DEFAULT 0,
    brand VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (cart_id) REFERENCES cart(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    status ENUM('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    address TEXT,
    payment_method VARCHAR(50) DEFAULT 'cod',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE chatbot_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    is_guest TINYINT(1) DEFAULT 1,
    message TEXT NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id)
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE chatbot_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    rating TINYINT NOT NULL COMMENT '1=thumbs up, 0=thumbs down',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log (log_id),
    INDEX idx_rating (rating)
);

CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    session_id VARCHAR(64) DEFAULT NULL,
    customer_name VARCHAR(100),
    customer_email VARCHAR(150),
    message TEXT NOT NULL,
    admin_reply TEXT DEFAULT NULL,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('open','replied','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
);

CREATE TABLE stock_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    name VARCHAR(100),
    notified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_notify (product_id, email)
);

CREATE TABLE wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wish (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin (password: admin123)
INSERT INTO users (name, email, password, role, phone) VALUES
('Admin ShopAI', 'admin@shopai.rw', '$2y$10$doyuuiaaYVttOWoBnwvzZeeWqFviCv3RTbF.YhD6i3y5eNOGhqlEy', 'admin', '+250788000001');

-- ============================================================
-- CATEGORIES (10 categories)
-- ============================================================
INSERT INTO categories (name, description) VALUES
('Smartphones & Tablets',   'Latest phones, tablets and accessories'),
('Laptops & Computers',     'Laptops, desktops, monitors and peripherals'),
('TV & Audio',              'Smart TVs, speakers, headphones and home cinema'),
('Home Appliances',         'Refrigerators, washing machines, microwaves and more'),
('Fashion — Men',           'Men clothing, shoes and accessories'),
('Fashion — Women',         'Women clothing, shoes, bags and accessories'),
('Groceries & Food',        'Fresh food, beverages, snacks and household items'),
('Health & Beauty',         'Skincare, haircare, vitamins and personal care'),
('Sports & Fitness',        'Gym equipment, sportswear and outdoor gear'),
('Baby & Kids',             'Baby care, toys, kids clothing and school supplies');

-- ============================================================
-- PRODUCTS — Category 1: Smartphones & Tablets (15 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Samsung Galaxy A54 5G',       '6.4" Super AMOLED, 128GB, 5000mAh battery, 50MP camera, Android 13',                          450000, 'samsung_a54.jpg',    1, 25, 'Samsung'),
('Samsung Galaxy S23',          '6.1" Dynamic AMOLED, 256GB, Snapdragon 8 Gen 2, 50MP triple camera',                          980000, 'samsung_s23.jpg',    1, 12, 'Samsung'),
('iPhone 14',                   '6.1" Super Retina XDR, 128GB, A15 Bionic chip, 12MP dual camera, iOS 16',                    1350000, 'iphone14.jpg',       1, 10, 'Apple'),
('iPhone 15 Pro',               '6.1" ProMotion OLED, 256GB, A17 Pro chip, 48MP triple camera, titanium design',              1850000, 'iphone15pro.jpg',    1, 8,  'Apple'),
('Tecno Spark 20 Pro',          '6.78" AMOLED, 256GB, 5000mAh, 50MP AI camera — best budget phone in Rwanda',                  180000, 'tecno_spark20.jpg',  1, 40, 'Tecno'),
('Infinix Hot 40 Pro',          '6.78" IPS LCD, 256GB, 5000mAh, 108MP camera, 33W fast charge',                                160000, 'infinix_hot40.jpg',  1, 35, 'Infinix'),
('Xiaomi Redmi Note 13',        '6.67" AMOLED, 128GB, 5000mAh, 108MP camera, Snapdragon 685',                                  220000, 'redmi_note13.jpg',   1, 30, 'Xiaomi'),
('Samsung Galaxy Tab A8',       '10.5" TFT display, 64GB, 7040mAh, Android 11, ideal for students',                            320000, 'galaxy_tab_a8.jpg',  1, 18, 'Samsung'),
('iPad 10th Generation',        '10.9" Liquid Retina, 64GB, A14 Bionic, 12MP camera, USB-C',                                   750000, 'ipad_10th.jpg',      1, 10, 'Apple'),
('Tecno Camon 20 Pro',          '6.67" AMOLED, 256GB, 64MP RGBW camera, 33W fast charge, 5G ready',                            280000, 'tecno_camon20.jpg',  1, 22, 'Tecno'),
('Huawei Nova 11i',             '6.8" IPS, 128GB, 6000mAh, 48MP camera, EMUI 13',                                              210000, 'huawei_nova11i.jpg', 1, 20, 'Huawei'),
('Oppo A78',                    '6.43" AMOLED, 128GB, 5000mAh, 50MP camera, 67W SUPERVOOC charge',                             230000, 'oppo_a78.jpg',       1, 25, 'Oppo'),
('Vivo Y36',                    '6.64" IPS, 128GB, 5000mAh, 50MP camera, 44W FlashCharge',                                     195000, 'vivo_y36.jpg',       1, 28, 'Vivo'),
('Samsung Galaxy A14',          '6.6" PLS LCD, 64GB, 5000mAh, 50MP triple camera — entry level',                               130000, 'samsung_a14.jpg',    1, 50, 'Samsung'),
('Itel P40',                    '6.6" IPS, 64GB, 6000mAh, 13MP camera — ultra affordable',                                      75000, 'itel_p40.jpg',       1, 60, 'Itel');

-- ============================================================
-- PRODUCTS — Category 2: Laptops & Computers (12 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('HP Laptop 15s',               'Intel Core i5-12th Gen, 8GB RAM, 512GB SSD, 15.6" FHD, Windows 11',                           750000, 'hp_15s.jpg',         2, 15, 'HP'),
('Dell Inspiron 15',            'Intel Core i7-12th Gen, 16GB RAM, 512GB SSD, 15.6" FHD, Backlit keyboard',                    950000, 'dell_inspiron15.jpg',2, 10, 'Dell'),
('Lenovo IdeaPad 3',            'AMD Ryzen 5, 8GB RAM, 256GB SSD, 15.6" FHD, Windows 11 — great for students',                 620000, 'lenovo_ideapad3.jpg',2, 18, 'Lenovo'),
('MacBook Air M2',              'Apple M2 chip, 8GB RAM, 256GB SSD, 13.6" Liquid Retina, 18hr battery',                       1650000, 'macbook_air_m2.jpg', 2, 6,  'Apple'),
('Asus VivoBook 15',            'Intel Core i5, 8GB RAM, 512GB SSD, 15.6" FHD, OLED display',                                  680000, 'asus_vivobook15.jpg',2, 12, 'Asus'),
('HP EliteBook 840 G9',         'Intel Core i7, 16GB RAM, 512GB SSD, 14" FHD, Business laptop, fingerprint reader',           1200000, 'hp_elitebook840.jpg',2, 8,  'HP'),
('Acer Aspire 5',               'Intel Core i5, 8GB RAM, 512GB SSD, 15.6" FHD IPS, slim design',                               640000, 'acer_aspire5.jpg',   2, 14, 'Acer'),
('Lenovo ThinkPad E15',         'Intel Core i7, 16GB RAM, 512GB SSD, 15.6" FHD, MIL-SPEC durability',                         1100000, 'thinkpad_e15.jpg',   2, 7,  'Lenovo'),
('HP Desktop PC i5',            'Intel Core i5, 8GB RAM, 1TB HDD + 256GB SSD, Windows 11, with keyboard & mouse',              580000, 'hp_desktop_i5.jpg',  2, 10, 'HP'),
('Dell 24" Monitor FHD',        '24" Full HD IPS monitor, 75Hz, HDMI+VGA, slim bezel, eye-care technology',                    180000, 'dell_monitor24.jpg', 2, 20, 'Dell'),
('Logitech MX Keys Keyboard',   'Wireless backlit keyboard, multi-device, USB-C rechargeable, quiet keys',                      65000, 'logitech_mxkeys.jpg',2, 30, 'Logitech'),
('Logitech MX Master 3 Mouse',  'Wireless ergonomic mouse, 4000 DPI, MagSpeed scroll, multi-device',                            55000, 'logitech_mx3.jpg',   2, 35, 'Logitech');

-- ============================================================
-- PRODUCTS — Category 3: TV & Audio (10 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Samsung 43" Smart TV 4K',     '43" Crystal UHD 4K, HDR, Tizen OS, Netflix/YouTube built-in, 3 HDMI',                        550000, 'samsung_tv43.jpg',   3, 12, 'Samsung'),
('LG 55" OLED Smart TV',        '55" OLED 4K, 120Hz, webOS, Dolby Vision, AI ThinQ, perfect blacks',                         1450000, 'lg_oled55.jpg',      3, 5,  'LG'),
('Hisense 32" Smart TV',        '32" HD Smart TV, VIDAA OS, Netflix/YouTube, 2 HDMI, budget-friendly',                         220000, 'hisense_32.jpg',     3, 20, 'Hisense'),
('Sony 50" Bravia 4K',          '50" 4K HDR, X1 processor, Android TV, Google Assistant, Dolby Atmos',                        780000, 'sony_bravia50.jpg',  3, 8,  'Sony'),
('JBL Charge 5 Speaker',        'Portable Bluetooth speaker, 20hr battery, IP67 waterproof, PartyBoost',                       120000, 'jbl_charge5.jpg',    3, 25, 'JBL'),
('Sony WH-1000XM5 Headphones',  'Wireless noise-cancelling headphones, 30hr battery, Hi-Res Audio, foldable',                  280000, 'sony_wh1000xm5.jpg', 3, 15, 'Sony'),
('Samsung Soundbar HW-B450',    '2.1ch soundbar, 300W, Dolby Audio, Bluetooth, wireless subwoofer',                            180000, 'samsung_soundbar.jpg',3, 10, 'Samsung'),
('JBL Flip 6 Speaker',          'Portable Bluetooth speaker, 12hr battery, IP67, bold sound, USB-C',                            85000, 'jbl_flip6.jpg',      3, 30, 'JBL'),
('Bose QuietComfort 45',        'Wireless noise-cancelling headphones, 24hr battery, premium comfort',                          320000, 'bose_qc45.jpg',      3, 8,  'Bose'),
('Xiaomi Mi TV Stick 4K',       'Streaming stick, 4K HDR, Android TV, Google Assistant, Chromecast built-in',                   45000, 'mi_tv_stick.jpg',    3, 40, 'Xiaomi');

-- ============================================================
-- PRODUCTS — Category 4: Home Appliances (12 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Samsung 253L Refrigerator',   'Double door, frost-free, digital inverter, energy saving, silver finish',                      650000, 'samsung_fridge.jpg', 4, 8,  'Samsung'),
('LG 7kg Washing Machine',      'Front load, 1400 RPM, steam wash, AI DD technology, energy class A+++',                        580000, 'lg_washer7kg.jpg',   4, 6,  'LG'),
('Hisense 150L Refrigerator',   'Single door, direct cool, low energy consumption — ideal for small homes',                     280000, 'hisense_fridge.jpg', 4, 15, 'Hisense'),
('Ramtons Microwave 20L',       '20L solo microwave, 700W, 5 power levels, defrost function, white',                             65000, 'ramtons_mw.jpg',     4, 20, 'Ramtons'),
('Bruhm Gas Cooker 4-Burner',   '4-burner gas cooker with oven, stainless steel, auto ignition, safety valve',                  180000, 'bruhm_cooker.jpg',   4, 12, 'Bruhm'),
('Philips Air Fryer 4.1L',      '4.1L capacity, 1400W, rapid air technology, up to 90% less fat, digital',                     120000, 'philips_airfryer.jpg',4, 18, 'Philips'),
('Ramtons Electric Kettle 1.7L','1.7L stainless steel kettle, 2200W, auto shut-off, boil-dry protection',                       18000, 'ramtons_kettle.jpg', 4, 50, 'Ramtons'),
('Panasonic Rice Cooker 1.8L',  '1.8L, 10 cups, keep-warm function, non-stick inner pot, steam tray',                           28000, 'panasonic_rice.jpg', 4, 35, 'Panasonic'),
('Dyson V12 Vacuum Cleaner',    'Cordless vacuum, 150AW suction, HEPA filtration, 60min battery, lightweight',                  480000, 'dyson_v12.jpg',      4, 5,  'Dyson'),
('Midea 1.5HP Split AC',        '1.5HP inverter air conditioner, 12000 BTU, WiFi control, energy saving, R32 gas',              420000, 'midea_ac.jpg',       4, 7,  'Midea'),
('Blueflame Electric Heater',   '2000W fan heater, 3 heat settings, overheat protection, portable',                              35000, 'blueflame_heater.jpg',4, 25, 'Blueflame'),
('Kenwood Stand Mixer 5L',      '5L bowl, 1000W, 6 speeds, dough hook + whisk + beater, stainless steel',                      145000, 'kenwood_mixer.jpg',  4, 10, 'Kenwood');

-- ============================================================
-- PRODUCTS — Category 5: Fashion — Men (12 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Men Slim Fit Chino Pants',    'Stretch cotton chino, slim fit, available in khaki/navy/black, sizes 28–38',                    18000, 'men_chino.jpg',      5, 60, 'Local Brand'),
('Men Formal Dress Shirt',      '100% cotton, long sleeve, slim fit, white/blue/grey, sizes S–XXL',                             12000, 'men_dress_shirt.jpg',5, 80, 'Local Brand'),
('Men Polo Shirt',              'Premium pique cotton polo, embroidered logo, sizes S–XXL, multiple colors',                      8500, 'men_polo.jpg',       5, 100,'Local Brand'),
('Men Leather Oxford Shoes',    'Genuine leather, lace-up, rubber sole, formal/casual, sizes 39–45',                             65000, 'men_oxford.jpg',     5, 30, 'Clarks'),
('Men Running Sneakers',        'Lightweight mesh upper, cushioned sole, breathable, sizes 39–45',                               35000, 'men_sneakers.jpg',   5, 45, 'Nike'),
('Men Leather Belt',            'Genuine leather belt, automatic buckle, 3.5cm width, black/brown',                               9500, 'men_belt.jpg',       5, 70, 'Local Brand'),
('Men Casual Hoodie',           'Fleece hoodie, kangaroo pocket, drawstring, sizes S–XXL, grey/black/navy',                     15000, 'men_hoodie.jpg',     5, 55, 'Local Brand'),
('Men Denim Jeans Slim',        'Stretch denim, slim fit, 5-pocket, dark blue/black, sizes 28–38',                              16000, 'men_jeans.jpg',      5, 65, 'Levi\'s'),
('Men Suit 2-Piece',            'Polyester-wool blend, slim fit, 2-button, navy/charcoal/black, sizes 36–52',                   85000, 'men_suit.jpg',       5, 20, 'Local Brand'),
('Men Wrist Watch Casio',       'Casio MTP-V001, analog, stainless steel, water resistant 50m, classic design',                  22000, 'casio_mtp.jpg',      5, 40, 'Casio'),
('Men Sunglasses Polarized',    'UV400 polarized lenses, metal frame, unisex, comes with case',                                   8000, 'men_sunglasses.jpg', 5, 50, 'Local Brand'),
('Men Backpack 30L',            'Water-resistant polyester, laptop compartment 15.6", USB charging port, black',                 25000, 'men_backpack.jpg',   5, 35, 'Local Brand');

-- ============================================================
-- PRODUCTS — Category 6: Fashion — Women (12 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Women Floral Maxi Dress',     'Chiffon floral print, V-neck, sleeveless, flowy, sizes XS–XL, multiple prints',               14000, 'women_maxi_dress.jpg',6, 50, 'Local Brand'),
('Women Office Blazer',         'Structured blazer, single button, slim fit, black/navy/grey, sizes XS–XL',                    28000, 'women_blazer.jpg',   6, 30, 'Local Brand'),
('Women High Waist Jeans',      'Stretch denim, high waist, skinny fit, blue/black, sizes 26–34',                              16000, 'women_jeans.jpg',    6, 55, 'Zara'),
('Women Leather Handbag',       'PU leather tote bag, spacious interior, zip closure, shoulder strap, black/brown',             22000, 'women_handbag.jpg',  6, 40, 'Local Brand'),
('Women Heeled Sandals',        'Block heel 7cm, ankle strap, faux leather, sizes 36–41, nude/black/red',                      18000, 'women_heels.jpg',    6, 35, 'Local Brand'),
('Women Sports Leggings',       'High waist compression leggings, moisture-wicking, 4-way stretch, sizes XS–XL',                9500, 'women_leggings.jpg', 6, 70, 'Local Brand'),
('Women Silk Blouse',           '100% polyester satin blouse, V-neck, long sleeve, elegant, sizes XS–XL',                     11000, 'women_blouse.jpg',   6, 45, 'Local Brand'),
('Women Sneakers White',        'Canvas sneakers, rubber sole, lace-up, classic white, sizes 36–41',                           18000, 'women_sneakers.jpg', 6, 40, 'Converse'),
('Women Crossbody Bag',         'Small crossbody bag, adjustable strap, multiple pockets, trendy design',                      12000, 'women_crossbody.jpg',6, 50, 'Local Brand'),
('Women Wrist Watch Elegant',   'Rose gold case, leather strap, quartz movement, water resistant, gift box included',           28000, 'women_watch.jpg',    6, 25, 'Fossil'),
('Women Perfume 100ml',         'Floral-fruity fragrance, long-lasting 8hrs, elegant bottle, gift-ready packaging',             35000, 'women_perfume.jpg',  6, 30, 'Local Brand'),
('Women Ankara Dress',          'African print Ankara fabric, fitted bodice, flared skirt, custom sizes available',             20000, 'women_ankara.jpg',   6, 40, 'Local Brand');

-- ============================================================
-- PRODUCTS — Category 7: Groceries & Food (15 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Inyange Milk 1L',             'Fresh whole milk, pasteurized, 1 liter pack, locally produced in Rwanda',                       1200, 'inyange_milk.jpg',   7, 200,'Inyange'),
('Inyange Yogurt 500ml',        'Strawberry/vanilla/plain yogurt, 500ml, no preservatives, chilled',                             1500, 'inyange_yogurt.jpg', 7, 150,'Inyange'),
('Akabanga Chili Oil 50ml',     'Rwanda\'s famous hot chili oil, 50ml bottle, authentic Rwandan flavor',                         2500, 'akabanga.jpg',       7, 300,'Akabanga'),
('Isange Rice 5kg',             'Premium long grain white rice, 5kg bag, locally grown in Rwanda',                               6500, 'isange_rice.jpg',    7, 100,'Isange'),
('Cooking Oil Kimbo 2L',        'Refined vegetable cooking oil, 2 liters, cholesterol-free',                                     4500, 'kimbo_oil.jpg',      7, 120,'Kimbo'),
('Nido Milk Powder 400g',       'Full cream milk powder, 400g tin, fortified with vitamins A & D',                               5500, 'nido_400g.jpg',      7, 80, 'Nestlé'),
('Lipton Yellow Label Tea 100', 'Black tea bags, 100 pack, classic blend, rich flavor',                                          3500, 'lipton_tea.jpg',     7, 150,'Lipton'),
('Nescafé Classic 200g',        'Instant coffee, 200g jar, rich aroma, smooth taste',                                            6000, 'nescafe_200g.jpg',   7, 90, 'Nestlé'),
('Indomie Noodles 70g x10',     'Instant noodles, chicken flavor, 10-pack bundle, quick meal',                                   3000, 'indomie_10pk.jpg',   7, 200,'Indomie'),
('Colgate Toothpaste 150ml',    'Cavity protection toothpaste, fluoride, fresh mint, 150ml',                                     2200, 'colgate_150ml.jpg',  7, 180,'Colgate'),
('Ariel Detergent 1kg',         'Washing powder, 1kg, removes tough stains, fresh scent',                                        3800, 'ariel_1kg.jpg',      7, 130,'Ariel'),
('Dettol Soap 3-Pack',          'Antibacterial bar soap, 3 x 100g, original scent, kills 99.9% germs',                          2800, 'dettol_soap.jpg',    7, 200,'Dettol'),
('Heinz Tomato Ketchup 570g',   'Classic tomato ketchup, 570g squeeze bottle, no artificial colors',                             4200, 'heinz_ketchup.jpg',  7, 100,'Heinz'),
('Pringles Original 165g',      'Crispy potato chips, original flavor, 165g can, perfect snack',                                 3500, 'pringles.jpg',       7, 120,'Pringles'),
('Coca-Cola 1.5L',              'Carbonated soft drink, 1.5L bottle, chilled and refreshing',                                    1800, 'cocacola_15l.jpg',   7, 250,'Coca-Cola');

-- ============================================================
-- PRODUCTS — Category 8: Health & Beauty (10 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Nivea Body Lotion 400ml',     'Moisturizing body lotion, shea butter, 400ml, 48hr moisture, all skin types',                   5500, 'nivea_lotion.jpg',   8, 80, 'Nivea'),
('Neutrogena Face Wash 200ml',  'Oil-free acne wash, salicylic acid, 200ml, gentle daily cleanser',                               8500, 'neutrogena_fw.jpg',  8, 60, 'Neutrogena'),
('Garnier Vitamin C Serum 30ml','Brightening serum, 30ml, vitamin C + niacinamide, reduces dark spots, daily use',               12000, 'garnier_serum.jpg',  8, 50, 'Garnier'),
('Dove Shampoo 400ml',          'Moisturizing shampoo, 400ml, intensive repair, for dry/damaged hair',                            5000, 'dove_shampoo.jpg',   8, 90, 'Dove'),
('Oral-B Electric Toothbrush',  'Rechargeable electric toothbrush, 2 modes, 2-min timer, removes 100% more plaque',              35000, 'oralb_electric.jpg', 8, 25, 'Oral-B'),
('Centrum Multivitamin 30tabs', 'Complete multivitamin, 30 tablets, vitamins A-Z, immune support, energy boost',                  8000, 'centrum_30.jpg',     8, 70, 'Centrum'),
('Vaseline Petroleum Jelly 250g','Pure petroleum jelly, 250g, heals dry skin, lips, elbows, multipurpose',                        3500, 'vaseline_250g.jpg',  8, 120,'Vaseline'),
('Gillette Fusion Razor 5-blade','5-blade precision razor, lubricating strip, FlexBall technology, 2 cartridges',                 9500, 'gillette_fusion.jpg',8, 55, 'Gillette'),
('Maybelline Lipstick',         'Superstay matte ink lipstick, 16hr wear, 20 shades available, transfer-proof',                   7500, 'maybelline_lip.jpg', 8, 65, 'Maybelline'),
('Sanitizer Dettol 500ml',      'Alcohol-based hand sanitizer, 500ml pump, kills 99.9% bacteria and viruses',                    4500, 'dettol_sanitizer.jpg',8,100, 'Dettol');

-- ============================================================
-- PRODUCTS — Category 9: Sports & Fitness (10 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Nike Running Shoes Air Max',  'Air Max cushioning, breathable mesh, sizes 39–46, black/white/red',                             85000, 'nike_airmax.jpg',    9, 30, 'Nike'),
('Adidas Training T-Shirt',     'Climalite moisture-wicking, slim fit, sizes S–XXL, multiple colors',                            18000, 'adidas_tshirt.jpg',  9, 60, 'Adidas'),
('Yoga Mat 6mm',                'Non-slip TPE yoga mat, 183x61cm, 6mm thick, carrying strap included',                           15000, 'yoga_mat.jpg',       9, 45, 'Local Brand'),
('Adjustable Dumbbell Set 20kg','Adjustable dumbbell pair, 2x10kg, cast iron, rubber grip, home gym essential',                  65000, 'dumbbell_20kg.jpg',  9, 20, 'Local Brand'),
('Jump Rope Speed',             'Speed jump rope, ball bearings, adjustable length, foam handles, counter',                       5500, 'jump_rope.jpg',      9, 80, 'Local Brand'),
('Resistance Bands Set 5pcs',   '5 resistance levels, latex bands, door anchor + handles + ankle straps included',               12000, 'resistance_bands.jpg',9,55, 'Local Brand'),
('Football Adidas Size 5',      'FIFA quality pro football, size 5, thermally bonded, all-weather use',                          22000, 'adidas_football.jpg',9, 35, 'Adidas'),
('Cycling Helmet',              'Adult cycling helmet, 57-61cm, 21 vents, adjustable fit, CE certified',                         18000, 'cycling_helmet.jpg', 9, 25, 'Local Brand'),
('Protein Powder Whey 1kg',     'Whey protein isolate, 1kg, chocolate/vanilla, 25g protein per serving',                         45000, 'whey_protein.jpg',   9, 30, 'Optimum Nutrition'),
('Water Bottle 1L Insulated',   'Stainless steel insulated bottle, 1L, keeps cold 24hr/hot 12hr, leak-proof',                     8500, 'water_bottle.jpg',   9, 70, 'Local Brand');

-- ============================================================
-- PRODUCTS — Category 10: Baby & Kids (10 products)
-- ============================================================
INSERT INTO products (name, description, price, image, category_id, stock, brand) VALUES
('Pampers Diapers Size 3 x52',  'Soft & dry diapers, size 3 (6-10kg), 52 count, 12hr protection, wetness indicator',            12000, 'pampers_s3.jpg',    10, 80, 'Pampers'),
('Johnson Baby Lotion 500ml',   'Gentle baby lotion, 500ml, clinically proven mild, 24hr moisture, no parabens',                  6500, 'johnson_lotion.jpg',10, 90, 'Johnson\'s'),
('Baby Feeding Bottle Set',     'BPA-free feeding bottles, 3-pack (150ml+250ml+300ml), anti-colic, silicone nipple',              8500, 'baby_bottles.jpg',  10, 60, 'Philips Avent'),
('Kids School Backpack',        'Lightweight school bag, 20L, padded straps, multiple pockets, ages 6–12',                        9500, 'kids_backpack.jpg', 10, 55, 'Local Brand'),
('Lego Classic Bricks 484pcs',  'Classic building bricks, 484 pieces, 33 colors, ages 4+, creativity toy',                       28000, 'lego_classic.jpg',  10, 25, 'Lego'),
('Baby Stroller Lightweight',   'Foldable stroller, 0–36 months, 5-point harness, sun canopy, storage basket',                   85000, 'baby_stroller.jpg', 10, 10, 'Graco'),
('Kids Bicycle 16"',            '16" wheel bicycle, training wheels included, ages 4–7, multiple colors',                         45000, 'kids_bicycle.jpg',  10, 15, 'Local Brand'),
('Educational Puzzle 100pcs',   'Wooden jigsaw puzzle, 100 pieces, animals theme, ages 3+, develops cognition',                   6500, 'edu_puzzle.jpg',    10, 50, 'Local Brand'),
('Baby Monitor WiFi',           'WiFi baby monitor, 1080p camera, night vision, 2-way audio, temperature sensor',                 65000, 'baby_monitor.jpg',  10, 12, 'Motorola'),
('Kids Clothing Set 3-piece',   'Cotton t-shirt + shorts + cap set, ages 1–8, bright colors, machine washable',                   7500, 'kids_clothing.jpg', 10, 70, 'Local Brand');

-- ============================================================
-- SAMPLE USERS (password for all: password123)
-- ============================================================
INSERT INTO users (name, email, password, role, phone, address) VALUES
('Eric Mugisha',    'eric@gmail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '+250788111001', 'KG 15 Ave, Kigali'),
('Amina Uwase',     'amina@gmail.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '+250788111002', 'KN 3 Rd, Nyarugenge'),
('Jean Habimana',   'jean@gmail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '+250788111003', 'KK 7 Ave, Kicukiro'),
('Grace Mukamana',  'grace@gmail.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', '+250788111004', 'NB 12 St, Musanze'),
('Patrick Nzeyimana','patrick@gmail.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','customer', '+250788111005', 'GS 5 Ave, Huye');

-- ============================================================
-- SAMPLE ORDERS
-- ============================================================
INSERT INTO orders (user_id, total_price, status, address, payment_method) VALUES
(2, 450000,  'delivered',  'KG 15 Ave, Kigali',       'momo'),
(2, 18000,   'shipped',    'KG 15 Ave, Kigali',       'cod'),
(3, 750000,  'processing', 'KN 3 Rd, Nyarugenge',     'momo'),
(4, 6500,    'pending',    'KK 7 Ave, Kicukiro',      'cod'),
(5, 85000,   'delivered',  'NB 12 St, Musanze',       'card');

INSERT INTO order_items (order_id, product_id, quantity, price) VALUES
(1, 1,  1, 450000),
(2, 34, 2, 9000),
(3, 17, 1, 750000),
(4, 43, 1, 6500),
(5, 29, 1, 85000);

-- ============================================================
-- SAMPLE REVIEWS
-- ============================================================
INSERT INTO reviews (product_id, user_id, rating, comment) VALUES
(1,  2, 5, 'Excellent phone! Battery lasts all day. Very happy with this purchase.'),
(1,  3, 4, 'Good phone for the price. Camera is great. Delivery was fast.'),
(17, 4, 5, 'Best laptop I have ever used. Fast and reliable. Worth every franc.'),
(43, 5, 4, 'Good quality rice. Well packaged. Will order again.'),
(29, 2, 5, 'Nike shoes are amazing! Very comfortable for running. True to size.');

-- ============================================================
-- SUMMARY
-- ============================================================
-- Total Categories : 10
-- Total Products   : 118 products
-- Price Range      : 1,200 RWF — 1,850,000 RWF
-- Total Users      : 6 (1 admin + 5 customers)
-- Sample Orders    : 5
-- Sample Reviews   : 5
-- Currency         : Rwandan Franc (RWF)
-- ============================================================
