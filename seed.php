<?php
set_time_limit(600);
ini_set("memory_limit","256M");
require_once "config/db.php";

echo "<!DOCTYPE html><html><head><meta charset=UTF-8>
<style>body{font-family:monospace;background:#0d1117;color:#58a6ff;padding:20px;font-size:13px}
.ok{color:#3fb950}.err{color:#f85149}.info{color:#d29922}h2{color:#58a6ff}</style>
</head><body><h2>AI-Powered Chatbot For E-commerce Support — Product Seeder</h2><pre>";
flush();

// Clear existing data
$conn->query("SET FOREIGN_KEY_CHECKS=0");
foreach(["reviews","chatbot_logs","order_items","orders","cart_items","cart","products","categories","users"] as $t)
    $conn->query("TRUNCATE TABLE $t");
$conn->query("SET FOREIGN_KEY_CHECKS=1");
echo "<span class='ok'>✓ Tables cleared</span>\n"; flush();

// Admin
$pass = password_hash("admin123", PASSWORD_DEFAULT);
$conn->query("INSERT INTO users (name,email,password,role,phone,address) VALUES
('Admin','ericniringiyimana123@gmail.com','$pass','admin','+250782977559','Kigali, Rwanda')");
echo "<span class='ok'>✓ Admin: ericniringiyimana123@gmail.com / admin123</span>\n"; flush();

// Sample customers
$conn->query("INSERT INTO users (name,email,password,role,phone,address) VALUES
('Eric Mugisha','eric@gmail.com','$pass','customer','+250788111001','KG 15 Ave, Kigali'),
('Amina Uwase','amina@gmail.com','$pass','customer','+250788111002','KN 3 Rd, Nyarugenge'),
('Jean Habimana','jean@gmail.com','$pass','customer','+250788111003','KK 7 Ave, Kicukiro'),
('Grace Mukamana','grace@gmail.com','$pass','customer','+250788111004','NB 12 St, Musanze'),
('Patrick Nzeyimana','patrick@gmail.com','$pass','customer','+250788111005','GS 5 Ave, Huye')");
echo "<span class='ok'>✓ 5 sample customers added</span>\n"; flush();

// Categories
$cats = [
  1=>"Smartphones & Tablets",2=>"Laptops & Computers",3=>"TV & Audio",
  4=>"Home Appliances",5=>"Fashion Men",6=>"Fashion Women",
  7=>"Groceries & Food",8=>"Health & Beauty",9=>"Sports & Fitness",
  10=>"Baby & Kids",11=>"Furniture & Decor",12=>"Car Accessories",
  13=>"Books & Stationery",14=>"Jewelry & Watches",15=>"Gaming & Electronics"
];
foreach($cats as $id=>$name){
    $n=$conn->real_escape_string($name);
    $conn->query("INSERT INTO categories (id,name) VALUES ($id,'$n')");
}
echo "<span class='ok'>✓ 15 categories inserted</span>\n"; flush();

// ── PRODUCT DATA ──────────────────────────────────────────────
$products = [];

// ── CAT 1: Smartphones & Tablets (55 products) ──
$p1 = [
["Samsung Galaxy A54 5G","6.4in AMOLED 120Hz, 128GB, 5000mAh, 50MP camera, Android 13, IP67",450000,25,"Samsung",10],
["Samsung Galaxy A34 5G","6.6in AMOLED, 128GB, 5000mAh, 48MP camera, Dimensity 1080, 25W charge",320000,30,"Samsung",11],
["Samsung Galaxy A14","6.6in PLS LCD, 64GB, 5000mAh, 50MP triple camera, entry-level",130000,50,"Samsung",12],
["Samsung Galaxy A24","6.5in AMOLED 90Hz, 128GB, 5000mAh, 50MP camera, 25W fast charge",210000,35,"Samsung",13],
["Samsung Galaxy S23","6.1in Dynamic AMOLED 120Hz, 256GB, Snapdragon 8 Gen 2, 50MP camera",980000,12,"Samsung",14],
["Samsung Galaxy S23 Ultra","6.8in QHD+ AMOLED, 512GB, 200MP camera, S Pen, 5000mAh",1650000,6,"Samsung",15],
["Samsung Galaxy Z Fold5","7.6in foldable AMOLED, 256GB, Snapdragon 8 Gen 2, dual screen",2200000,4,"Samsung",16],
["Samsung Galaxy Z Flip5","6.7in foldable AMOLED, 256GB, Snapdragon 8 Gen 2, compact flip",1450000,5,"Samsung",17],
["iPhone 14 128GB","6.1in Super Retina XDR, A15 Bionic, 12MP dual camera, iOS 16, 5G",1350000,10,"Apple",18],
["iPhone 14 Pro 256GB","6.1in ProMotion OLED, A16 Bionic, 48MP triple camera, Dynamic Island",1750000,7,"Apple",19],
["iPhone 15 128GB","6.1in Super Retina XDR, A16 Bionic, 48MP camera, USB-C, iOS 17",1480000,9,"Apple",20],
["iPhone 15 Pro 256GB","6.1in ProMotion OLED, A17 Pro, 48MP triple camera, titanium design",1850000,7,"Apple",21],
["iPhone 15 Pro Max 256GB","6.7in ProMotion OLED, A17 Pro, 48MP periscope camera, titanium",2100000,5,"Apple",22],
["Tecno Spark 20 Pro","6.78in AMOLED 120Hz, 256GB, 5000mAh, 50MP AI camera, 33W charge",180000,40,"Tecno",23],
["Tecno Spark 20C","6.56in IPS, 128GB, 5000mAh, 50MP camera, 18W fast charge, budget",120000,55,"Tecno",24],
["Tecno Camon 20 Pro","6.67in AMOLED, 256GB, 64MP RGBW camera, 5000mAh, 33W fast charge",280000,22,"Tecno",25],
["Tecno Phantom X2 Pro","6.8in AMOLED 120Hz, 256GB, 50MP retractable portrait lens, 5160mAh",420000,15,"Tecno",26],
["Infinix Hot 40 Pro","6.78in IPS LCD, 256GB, 5000mAh, 108MP camera, 33W fast charge",160000,35,"Infinix",27],
["Infinix Hot 40i","6.56in IPS, 128GB, 5000mAh, 50MP camera, 18W charge, budget",100000,60,"Infinix",28],
["Infinix Zero 30 5G","6.78in AMOLED 144Hz, 256GB, 50MP front camera, 5000mAh, 68W charge",250000,20,"Infinix",29],
["Xiaomi Redmi Note 13","6.67in AMOLED 120Hz, 128GB, 5000mAh, 108MP camera, Snapdragon 685",220000,30,"Xiaomi",30],
["Xiaomi Redmi Note 13 Pro","6.67in AMOLED 120Hz, 256GB, 200MP camera, 5100mAh, 67W fast charge",350000,18,"Xiaomi",31],
["Xiaomi 13T Pro","6.67in AMOLED 144Hz, 256GB, Dimensity 9200+, 50MP Leica camera",780000,10,"Xiaomi",32],
["Oppo A78","6.43in AMOLED, 128GB, 5000mAh, 50MP camera, 67W SUPERVOOC charge",230000,25,"Oppo",33],
["Oppo Reno10 Pro","6.74in AMOLED 120Hz, 256GB, 50MP triple camera, 80W fast charge",580000,12,"Oppo",34],
["Vivo Y36","6.64in IPS, 128GB, 5000mAh, 50MP camera, 44W FlashCharge, slim",195000,28,"Vivo",35],
["Vivo V29","6.78in AMOLED 120Hz, 256GB, 50MP OIS camera, 4600mAh, 80W charge",520000,14,"Vivo",36],
["Huawei Nova 11i","6.8in IPS, 128GB, 6000mAh, 48MP camera, EMUI 13, 40W fast charge",210000,20,"Huawei",37],
["Nokia G42 5G","6.56in IPS, 128GB, 5000mAh, 50MP camera, 20W charge, 3yr OS updates",185000,22,"Nokia",38],
["Motorola Moto G84","6.55in pOLED 120Hz, 256GB, 50MP OIS camera, 5000mAh, 33W TurboPower",280000,18,"Motorola",39],
["Itel P40","6.6in IPS, 64GB, 6000mAh ultra battery, 13MP camera, ultra affordable",75000,60,"Itel",40],
["Samsung Galaxy Tab A8","10.5in TFT, 64GB, 7040mAh, Android 11, 8MP camera, for students",320000,18,"Samsung",41],
["Samsung Galaxy Tab S9 FE","10.9in TFT 90Hz, 128GB, Exynos 1380, 8000mAh, S Pen, IP68",580000,10,"Samsung",42],
["iPad 10th Generation","10.9in Liquid Retina, 64GB, A14 Bionic, 12MP camera, USB-C",750000,10,"Apple",43],
["iPad Air M1","10.9in Liquid Retina, 64GB, M1 chip, 12MP camera, USB-C, Touch ID",1050000,7,"Apple",44],
["Xiaomi Pad 6","11in IPS 144Hz, 128GB, Snapdragon 870, 8840mAh, 33W fast charge",420000,12,"Xiaomi",45],
["Lenovo Tab M10 Plus","10.6in 2K IPS, 64GB, Helio G80, 7700mAh, 4G LTE, kids mode",220000,20,"Lenovo",46],
["Samsung Galaxy Buds2 Pro","True wireless ANC earbuds, IPX7, 8hr battery + 29hr case",180000,30,"Samsung",47],
["Apple AirPods Pro 2nd Gen","True wireless ANC earbuds, H2 chip, 6hr battery, MagSafe case",280000,12,"Apple",48],
["Jabra Elite 4 Active","True wireless ANC earbuds, IP57, 7hr battery, multipoint",120000,20,"Jabra",49],
["Samsung Galaxy A05s","6.7in PLS LCD, 64GB, 5000mAh, 50MP triple camera, Snapdragon 680",115000,45,"Samsung",50],
["Tecno Pop 7 Pro","6.6in IPS, 64GB, 5000mAh, 13MP camera, face unlock, entry level",65000,70,"Tecno",51],
["Infinix Smart 8","6.6in IPS, 64GB, 5000mAh, 13MP camera, Android 13 Go, affordable",70000,65,"Infinix",52],
["Xiaomi Redmi 13C","6.74in IPS, 128GB, 5000mAh, 50MP camera, Helio G85, 18W charge",140000,40,"Xiaomi",53],
["Realme C55","6.72in IPS 90Hz, 128GB, 5000mAh, 64MP camera, 33W fast charge",175000,32,"Realme",54],
["Google Pixel 7a","6.1in OLED 90Hz, 128GB, Google Tensor G2, 64MP camera, 5G, IP67",680000,8,"Google",55],
["OnePlus Nord CE3 Lite","6.72in IPS 120Hz, 128GB, 5000mAh, 108MP camera, 67W SUPERVOOC",310000,20,"OnePlus",56],
["Realme GT5","6.74in AMOLED 144Hz, 256GB, Snapdragon 8 Gen 2, 50MP camera, 240W charge",850000,8,"Realme",57],
["Itel A70","6.6in IPS, 256GB, 5000mAh, 13MP camera, 10W charge, budget Rwanda",55000,80,"Itel",58],
["Samsung Galaxy M34 5G","6.5in Super AMOLED 120Hz, 128GB, 6000mAh, 50MP triple camera",260000,25,"Samsung",59],
["Tecno Spark Go 2024","6.56in IPS, 64GB, 5000mAh, 13MP camera, face unlock, very affordable",50000,90,"Tecno",60],
["Xiaomi Poco X5 Pro","6.67in AMOLED 120Hz, 256GB, Snapdragon 778G, 108MP camera, 67W",380000,18,"Xiaomi",61],
["Oppo A17","6.56in IPS, 64GB, 5000mAh, 50MP camera, 33W fast charge, slim design",145000,38,"Oppo",62],
["Vivo Y16","6.51in IPS, 64GB, 5000mAh, 13MP camera, 15W charge, budget phone",110000,42,"Vivo",63],
["Nokia C32","6.52in IPS, 64GB, 5000mAh, 50MP camera, 18W charge, 3yr OS guarantee",95000,48,"Nokia",64],
["Motorola Moto E13","6.5in IPS, 64GB, 5000mAh, 13MP camera, Android 13 Go, affordable",80000,55,"Motorola",65]
];
foreach($p1 as $p) $products[]=[$p[0],$p[1],$p[2],1,$p[3],$p[4],$p[5]];

// ── CAT 2: Laptops & Computers (40 products) ──
$p2 = [
["HP Laptop 15s","Intel Core i5-12th Gen, 8GB RAM, 512GB SSD, 15.6in FHD, Windows 11",750000,15,"HP",101],
["Dell Inspiron 15","Intel Core i7-12th Gen, 16GB RAM, 512GB SSD, 15.6in FHD, backlit keyboard",950000,10,"Dell",102],
["Lenovo IdeaPad 3","AMD Ryzen 5, 8GB RAM, 256GB SSD, 15.6in FHD, Windows 11, for students",620000,18,"Lenovo",103],
["MacBook Air M2","Apple M2 chip, 8GB RAM, 256GB SSD, 13.6in Liquid Retina, 18hr battery",1650000,6,"Apple",104],
["Asus VivoBook 15","Intel Core i5, 8GB RAM, 512GB SSD, 15.6in FHD OLED display",680000,12,"Asus",105],
["HP EliteBook 840 G9","Intel Core i7, 16GB RAM, 512GB SSD, 14in FHD, business laptop",1200000,8,"HP",106],
["Acer Aspire 5","Intel Core i5, 8GB RAM, 512GB SSD, 15.6in FHD IPS, slim design",640000,14,"Acer",107],
["Lenovo ThinkPad E15","Intel Core i7, 16GB RAM, 512GB SSD, 15.6in FHD, MIL-SPEC durability",1100000,7,"Lenovo",108],
["HP Desktop PC i5","Intel Core i5, 8GB RAM, 1TB HDD + 256GB SSD, Windows 11, keyboard+mouse",580000,10,"HP",109],
["Dell 24in Monitor FHD","24in Full HD IPS, 75Hz, HDMI+VGA, slim bezel, eye-care technology",180000,20,"Dell",110],
["Logitech MX Keys Keyboard","Wireless backlit keyboard, multi-device, USB-C rechargeable, quiet keys",65000,30,"Logitech",111],
["Logitech MX Master 3 Mouse","Wireless ergonomic mouse, 4000 DPI, MagSpeed scroll, multi-device",55000,35,"Logitech",112],
["MacBook Pro M3 14in","Apple M3 chip, 8GB RAM, 512GB SSD, 14.2in Liquid Retina XDR, 22hr battery",2200000,4,"Apple",113],
["Dell XPS 15","Intel Core i7-13th Gen, 16GB RAM, 512GB SSD, 15.6in OLED 3.5K, premium",1850000,5,"Dell",114],
["Asus ROG Strix G15","AMD Ryzen 9, 16GB RAM, 512GB SSD, RTX 3060, 15.6in 144Hz, gaming",1400000,6,"Asus",115],
["HP Pavilion Gaming 15","Intel Core i5, 8GB RAM, 512GB SSD, GTX 1650, 15.6in 144Hz, gaming",980000,9,"HP",116],
["Lenovo Legion 5","AMD Ryzen 7, 16GB RAM, 512GB SSD, RTX 3060, 15.6in 165Hz, gaming",1350000,7,"Lenovo",117],
["Acer Nitro 5","Intel Core i5, 8GB RAM, 512GB SSD, RTX 3050, 15.6in 144Hz, gaming",880000,10,"Acer",118],
["Microsoft Surface Pro 9","Intel Core i5, 8GB RAM, 256GB SSD, 13in PixelSense, 2-in-1 tablet",1500000,5,"Microsoft",119],
["HP 27in 4K Monitor","27in 4K UHD IPS, 60Hz, USB-C, HDMI, DisplayPort, eye-care",320000,12,"HP",120],
["Samsung 27in Curved Monitor","27in FHD VA curved, 75Hz, AMD FreeSync, HDMI, slim bezel",220000,15,"Samsung",121],
["Logitech C920 Webcam","1080p HD webcam, autofocus, stereo mic, USB, for video calls",55000,25,"Logitech",122],
["HP LaserJet Pro M15w","Wireless laser printer, 19ppm, USB+WiFi, compact, Windows/Mac",180000,10,"HP",123],
["Canon PIXMA G3420","Wireless inkjet printer, print/scan/copy, refillable ink tank, WiFi",145000,12,"Canon",124],
["WD 1TB External HDD","USB 3.0 portable hard drive, 1TB, slim design, plug-and-play",45000,40,"WD",125],
["Samsung 1TB SSD T7","Portable SSD, 1TB, USB 3.2, 1050MB/s read, compact, shock-resistant",95000,20,"Samsung",126],
["Kingston 16GB USB Flash","USB 3.2 flash drive, 16GB, 200MB/s read, compact, durable",8000,80,"Kingston",127],
["Corsair 16GB DDR4 RAM","16GB DDR4 3200MHz, desktop RAM, CL16, compatible with Intel/AMD",45000,25,"Corsair",128],
["Intel Core i5-12400 CPU","12th Gen, 6 cores, 12 threads, 4.4GHz boost, LGA1700, 65W TDP",180000,10,"Intel",129],
["Nvidia GTX 1650 GPU","4GB GDDR6, 1665MHz boost, HDMI+DP, low power, gaming graphics card",280000,8,"Nvidia",130],
["Asus 24in FHD Monitor","24in IPS FHD, 75Hz, HDMI+VGA, ultra-slim, flicker-free, eye care",120000,18,"Asus",131],
["Lenovo IdeaPad 1","Intel Celeron, 4GB RAM, 128GB SSD, 14in HD, Windows 11 S, budget",320000,20,"Lenovo",132],
["Acer Chromebook 314","Intel Celeron, 4GB RAM, 64GB eMMC, 14in FHD, ChromeOS, 12hr battery",280000,15,"Acer",133],
["HP Stream 14","Intel Celeron, 4GB RAM, 64GB eMMC, 14in HD, Windows 11 S, ultra budget",250000,18,"HP",134],
["Dell Vostro 15","Intel Core i5, 8GB RAM, 256GB SSD, 15.6in FHD, business laptop",720000,10,"Dell",135],
["Asus ZenBook 14","Intel Core i5, 8GB RAM, 512GB SSD, 14in FHD OLED, slim 1.39kg",950000,8,"Asus",136],
["Razer DeathAdder V3","Wired gaming mouse, 30000 DPI, 90hr battery, Focus Pro sensor",85000,15,"Razer",137],
["HyperX Alloy Origins Keyboard","Mechanical gaming keyboard, RGB, HyperX Red switches, USB-C",95000,12,"HyperX",138],
["Anker USB-C Hub 7-in-1","USB-C hub, 4K HDMI, 3x USB-A, SD card, 100W PD, compact",35000,30,"Anker",139],
["TP-Link WiFi 6 Router","AX1800 dual-band WiFi 6, 4 antennas, MU-MIMO, 1.5GHz CPU",95000,15,"TP-Link",140]
];
foreach($p2 as $p) $products[]=[$p[0],$p[1],$p[2],2,$p[3],$p[4],$p[5]];

// ── CAT 3: TV & Audio (30 products) ──
$p3 = [
["Samsung 43in Smart TV 4K","43in Crystal UHD 4K, HDR, Tizen OS, Netflix/YouTube, 3 HDMI",550000,12,"Samsung",201],
["LG 55in OLED Smart TV","55in OLED 4K, 120Hz, webOS, Dolby Vision, AI ThinQ, perfect blacks",1450000,5,"LG",202],
["Hisense 32in Smart TV","32in HD Smart TV, VIDAA OS, Netflix/YouTube, 2 HDMI, budget",220000,20,"Hisense",203],
["Sony 50in Bravia 4K","50in 4K HDR, X1 processor, Android TV, Google Assistant, Dolby Atmos",780000,8,"Sony",204],
["JBL Charge 5 Speaker","Portable Bluetooth speaker, 20hr battery, IP67 waterproof, PartyBoost",120000,25,"JBL",205],
["Sony WH-1000XM5 Headphones","Wireless noise-cancelling, 30hr battery, Hi-Res Audio, foldable",280000,15,"Sony",206],
["Samsung Soundbar HW-B450","2.1ch soundbar, 300W, Dolby Audio, Bluetooth, wireless subwoofer",180000,10,"Samsung",207],
["JBL Flip 6 Speaker","Portable Bluetooth speaker, 12hr battery, IP67, bold sound, USB-C",85000,30,"JBL",208],
["Bose QuietComfort 45","Wireless noise-cancelling headphones, 24hr battery, premium comfort",320000,8,"Bose",209],
["Xiaomi Mi TV Stick 4K","Streaming stick, 4K HDR, Android TV, Google Assistant, Chromecast",45000,40,"Xiaomi",210],
["LG 43in Smart TV FHD","43in Full HD, webOS, ThinQ AI, Netflix/YouTube, 3 HDMI, 2 USB",380000,15,"LG",211],
["TCL 55in QLED 4K TV","55in QLED 4K, 60Hz, Android TV, Dolby Vision, HDR10+, 3 HDMI",650000,8,"TCL",212],
["Hisense 55in 4K Smart TV","55in 4K UHD, VIDAA OS, Dolby Vision, HDR10, 3 HDMI, 2 USB",520000,10,"Hisense",213],
["Sony HT-S400 Soundbar","2.1ch soundbar, 330W, Bluetooth, HDMI ARC, X-Balanced speaker",220000,12,"Sony",214],
["JBL Xtreme 3 Speaker","Portable Bluetooth speaker, 15hr battery, IP67, PartyBoost, USB-C",180000,15,"JBL",215],
["Anker Soundcore Q45 Headphones","Wireless ANC headphones, 50hr battery, Hi-Res Audio, foldable",65000,20,"Anker",216],
["Samsung 65in Crystal UHD","65in 4K Crystal UHD, HDR, Tizen OS, Motion Xcelerator, 3 HDMI",850000,6,"Samsung",217],
["LG OLED C3 65in","65in OLED evo 4K, 120Hz, G-Sync, Dolby Vision IQ, webOS 23",2200000,3,"LG",218],
["Philips 50in 4K Ambilight TV","50in 4K UHD, Ambilight 3-sided, Android TV, P5 processor",680000,7,"Philips",219],
["Bose SoundLink Flex","Portable Bluetooth speaker, 12hr battery, IP67, PositionIQ",150000,18,"Bose",220],
["Sony SRS-XB33 Speaker","Portable Bluetooth speaker, 24hr battery, IP67, Extra Bass, USB-C",95000,22,"Sony",221],
["Marshall Emberton II","Portable Bluetooth speaker, 30hr battery, IP67, True Stereophonic",120000,15,"Marshall",222],
["Sennheiser HD 450BT","Wireless headphones, ANC, 30hr battery, USB-C, foldable, Hi-Res",95000,18,"Sennheiser",223],
["JBL Tune 760NC","Wireless ANC headphones, 35hr battery, USB-C, foldable, multipoint",75000,25,"JBL",224],
["Xiaomi Redmi Buds 4 Pro","True wireless ANC earbuds, 43dB noise cancel, 9hr battery, IP54",55000,30,"Xiaomi",225],
["Samsung Galaxy Buds FE","True wireless ANC earbuds, 6hr battery + 21hr case, IPX2",95000,25,"Samsung",226],
["Hisense 40in FHD Smart TV","40in Full HD, VIDAA OS, Netflix/YouTube, 2 HDMI, budget Rwanda",280000,18,"Hisense",227],
["TCL 32in HD Smart TV","32in HD, Android TV, Google Assistant, 2 HDMI, budget friendly",185000,22,"TCL",228],
["Xiaomi TV A2 43in","43in FHD, Android TV 11, Google Assistant, Chromecast, 3 HDMI",320000,14,"Xiaomi",229],
["Polk Audio Signa S2 Soundbar","2.1ch soundbar, 80W, Dolby Digital, Bluetooth, HDMI ARC",145000,10,"Polk Audio",230]
];
foreach($p3 as $p) $products[]=[$p[0],$p[1],$p[2],3,$p[3],$p[4],$p[5]];

// ── CAT 4: Home Appliances (35 products) ──
$p4 = [
["Samsung 253L Refrigerator","Double door, frost-free, digital inverter, energy saving, silver",650000,8,"Samsung",301],
["LG 7kg Washing Machine","Front load, 1400 RPM, steam wash, AI DD technology, energy A+++",580000,6,"LG",302],
["Hisense 150L Refrigerator","Single door, direct cool, low energy, ideal for small homes",280000,15,"Hisense",303],
["Ramtons Microwave 20L","20L solo microwave, 700W, 5 power levels, defrost function, white",65000,20,"Ramtons",304],
["Bruhm Gas Cooker 4-Burner","4-burner gas cooker with oven, stainless steel, auto ignition",180000,12,"Bruhm",305],
["Philips Air Fryer 4.1L","4.1L, 1400W, rapid air technology, up to 90% less fat, digital",120000,18,"Philips",306],
["Ramtons Electric Kettle 1.7L","1.7L stainless steel, 2200W, auto shut-off, boil-dry protection",18000,50,"Ramtons",307],
["Panasonic Rice Cooker 1.8L","1.8L, 10 cups, keep-warm function, non-stick inner pot, steam tray",28000,35,"Panasonic",308],
["Dyson V12 Vacuum Cleaner","Cordless vacuum, 150AW suction, HEPA filtration, 60min battery",480000,5,"Dyson",309],
["Midea 1.5HP Split AC","1.5HP inverter AC, 12000 BTU, WiFi control, energy saving, R32",420000,7,"Midea",310],
["Blueflame Electric Heater","2000W fan heater, 3 heat settings, overheat protection, portable",35000,25,"Blueflame",311],
["Kenwood Stand Mixer 5L","5L bowl, 1000W, 6 speeds, dough hook + whisk + beater, stainless",145000,10,"Kenwood",312],
["Samsung 400L Refrigerator","French door, no-frost, Twin Cooling Plus, 400L, energy saving",1200000,5,"Samsung",313],
["LG 9kg Washing Machine","Top load, 700 RPM, TurboDrum, Smart Inverter, energy efficient",480000,8,"LG",314],
["Hisense 200L Refrigerator","Double door, frost-free, 200L, energy saving, silver finish",420000,10,"Hisense",315],
["Bruhm 2-Burner Gas Cooker","2-burner table top gas cooker, stainless steel, auto ignition",65000,20,"Bruhm",316],
["Philips Blender 2L","2L blender, 600W, 2 speeds + pulse, stainless steel blades, BPA-free",35000,25,"Philips",317],
["Ramtons Sandwich Maker","Non-stick sandwich maker, 750W, indicator light, cool-touch handle",12000,40,"Ramtons",318],
["Midea 2HP Split AC","2HP inverter AC, 18000 BTU, WiFi, self-cleaning, energy saving",580000,5,"Midea",319],
["Panasonic Microwave 25L","25L solo microwave, 900W, 6 power levels, defrost, child lock",85000,15,"Panasonic",320],
["Kenwood Food Processor","800W, 2.1L bowl, 6 attachments, slice/shred/blend/chop, stainless",95000,12,"Kenwood",321],
["Dyson Purifier Hot+Cool","Air purifier + heater + fan, HEPA H13, 360° filtration, WiFi",650000,4,"Dyson",322],
["LG Dishwasher 14 Place","14 place settings, 6 programs, inverter direct drive, A++ energy",850000,4,"LG",323],
["Samsung Microwave 28L","28L convection microwave, 900W, grill, ceramic enamel interior",120000,10,"Samsung",324],
["Bruhm 6-Burner Gas Cooker","6-burner gas cooker with oven, stainless steel, auto ignition",280000,8,"Bruhm",325],
["Philips Juicer 1.5L","1.5L juicer, 400W, 2 speeds, XL feed tube, easy clean, BPA-free",28000,22,"Philips",326],
["Ramtons Toaster 4-Slice","4-slice toaster, 1500W, 7 browning levels, cancel/defrost/reheat",22000,30,"Ramtons",327],
["Midea Chest Freezer 100L","100L chest freezer, manual defrost, energy saving, white finish",280000,10,"Midea",328],
["Hisense Washing Machine 7kg","Top load, 700 RPM, 7kg, 8 programs, child lock, energy efficient",320000,10,"Hisense",329],
["Panasonic Iron 2400W","2400W steam iron, non-stick soleplate, anti-drip, self-clean",18000,35,"Panasonic",330],
["Kenwood Electric Grill","2000W contact grill, non-stick plates, adjustable temperature",45000,15,"Kenwood",331],
["Philips Pressure Cooker 6L","6L electric pressure cooker, 12 programs, keep-warm, stainless",65000,12,"Philips",332],
["Ramtons Water Dispenser","Hot & cold water dispenser, 3 taps, child safety lock, stainless",85000,10,"Ramtons",333],
["Midea Portable AC 1HP","1HP portable AC, 9000 BTU, no installation needed, remote control",320000,6,"Midea",334],
["Samsung Robot Vacuum","Robot vacuum, 2500Pa suction, LiDAR mapping, WiFi, auto-empty",580000,5,"Samsung",335]
];
foreach($p4 as $p) $products[]=[$p[0],$p[1],$p[2],4,$p[3],$p[4],$p[5]];

// ── CAT 5: Fashion Men (30 products) ──
$p5 = [
["Men Slim Fit Chino Pants","Stretch cotton chino, slim fit, khaki/navy/black, sizes 28-38",18000,60,"Local Brand",401],
["Men Formal Dress Shirt","100% cotton, long sleeve, slim fit, white/blue/grey, sizes S-XXL",12000,80,"Local Brand",402],
["Men Polo Shirt","Premium pique cotton polo, embroidered logo, sizes S-XXL, multi-color",8500,100,"Local Brand",403],
["Men Leather Oxford Shoes","Genuine leather, lace-up, rubber sole, formal/casual, sizes 39-45",65000,30,"Clarks",404],
["Men Running Sneakers Nike","Lightweight mesh upper, cushioned sole, breathable, sizes 39-45",35000,45,"Nike",405],
["Men Leather Belt","Genuine leather belt, automatic buckle, 3.5cm width, black/brown",9500,70,"Local Brand",406],
["Men Casual Hoodie","Fleece hoodie, kangaroo pocket, drawstring, sizes S-XXL, grey/black",15000,55,"Local Brand",407],
["Men Denim Jeans Slim","Stretch denim, slim fit, 5-pocket, dark blue/black, sizes 28-38",16000,65,"Levi's",408],
["Men Suit 2-Piece","Polyester-wool blend, slim fit, 2-button, navy/charcoal, sizes 36-52",85000,20,"Local Brand",409],
["Men Wrist Watch Casio","Casio MTP-V001, analog, stainless steel, water resistant 50m",22000,40,"Casio",410],
["Men Sunglasses Polarized","UV400 polarized lenses, metal frame, unisex, comes with case",8000,50,"Local Brand",411],
["Men Backpack 30L","Water-resistant polyester, laptop compartment 15.6in, USB port, black",25000,35,"Local Brand",412],
["Men Adidas Track Suit","Polyester track suit, zip jacket + pants, sizes S-XXL, 3 stripes",28000,40,"Adidas",413],
["Men Leather Wallet","Genuine leather bifold wallet, 8 card slots, RFID blocking, slim",12000,60,"Local Brand",414],
["Men Formal Trousers","Polyester-wool blend, flat front, slim fit, black/grey, sizes 28-38",14000,50,"Local Brand",415],
["Men Casual T-Shirt Pack 3","100% cotton crew neck t-shirts, 3-pack, white/black/grey, S-XXL",12000,80,"Local Brand",416],
["Men Leather Loafers","Genuine leather slip-on loafers, rubber sole, sizes 39-45, brown",45000,25,"Clarks",417],
["Men Sports Shorts","Polyester sports shorts, elastic waist, 2 pockets, sizes S-XXL",6500,70,"Adidas",418],
["Men Winter Jacket","Padded winter jacket, water-resistant, hood, sizes S-XXL, black/navy",35000,30,"Local Brand",419],
["Men Tie Silk","100% polyester silk tie, 8cm width, multiple patterns, gift box",8000,45,"Local Brand",420],
["Men Dress Shoes Formal","Patent leather Oxford, lace-up, rubber sole, sizes 39-45, black",55000,20,"Local Brand",421],
["Men Cargo Pants","Cotton cargo pants, 6 pockets, relaxed fit, khaki/black, sizes 28-38",16000,45,"Local Brand",422],
["Men Swim Shorts","Quick-dry polyester swim shorts, elastic waist, sizes S-XXL, multi",8500,40,"Local Brand",423],
["Men Wool Sweater","Merino wool blend sweater, crew neck, sizes S-XXL, grey/navy/black",22000,30,"Local Brand",424],
["Men Cap Baseball","Cotton baseball cap, adjustable strap, embroidered logo, multi-color",5000,80,"Local Brand",425],
["Men Socks Pack 6","Cotton ankle socks, 6-pack, sizes 40-46, white/black/mixed colors",4500,100,"Local Brand",426],
["Men Underwear Pack 3","Cotton boxer briefs, 3-pack, sizes S-XXL, comfortable waistband",7500,90,"Local Brand",427],
["Men Formal Blazer","Polyester-wool blazer, single button, slim fit, navy/black, S-XXL",45000,25,"Local Brand",428],
["Men Sandals Leather","Genuine leather sandals, adjustable strap, rubber sole, sizes 39-45",18000,35,"Local Brand",429],
["Men Gym Bag 40L","Polyester gym bag, shoe compartment, wet pocket, shoulder strap",18000,30,"Local Brand",430]
];
foreach($p5 as $p) $products[]=[$p[0],$p[1],$p[2],5,$p[3],$p[4],$p[5]];

// ── CAT 6: Fashion Women (30 products) ──
$p6 = [
["Women Floral Maxi Dress","Chiffon floral print, V-neck, sleeveless, flowy, sizes XS-XL",14000,50,"Local Brand",501],
["Women Office Blazer","Structured blazer, single button, slim fit, black/navy/grey, XS-XL",28000,30,"Local Brand",502],
["Women High Waist Jeans","Stretch denim, high waist, skinny fit, blue/black, sizes 26-34",16000,55,"Zara",503],
["Women Leather Handbag","PU leather tote bag, spacious, zip closure, shoulder strap, black",22000,40,"Local Brand",504],
["Women Heeled Sandals","Block heel 7cm, ankle strap, faux leather, sizes 36-41, nude/black",18000,35,"Local Brand",505],
["Women Sports Leggings","High waist compression, moisture-wicking, 4-way stretch, XS-XL",9500,70,"Local Brand",506],
["Women Silk Blouse","100% polyester satin blouse, V-neck, long sleeve, elegant, XS-XL",11000,45,"Local Brand",507],
["Women Sneakers White","Canvas sneakers, rubber sole, lace-up, classic white, sizes 36-41",18000,40,"Converse",508],
["Women Crossbody Bag","Small crossbody bag, adjustable strap, multiple pockets, trendy",12000,50,"Local Brand",509],
["Women Wrist Watch Elegant","Rose gold case, leather strap, quartz movement, water resistant",28000,25,"Fossil",510],
["Women Perfume 100ml","Floral-fruity fragrance, long-lasting 8hrs, elegant bottle, gift-ready",35000,30,"Local Brand",511],
["Women Ankara Dress","African print Ankara fabric, fitted bodice, flared skirt, custom sizes",20000,40,"Local Brand",512],
["Women Cardigan Knit","Soft knit cardigan, open front, long sleeve, sizes XS-XL, multi-color",14000,45,"Local Brand",513],
["Women Flat Shoes Ballet","Faux leather ballet flats, cushioned insole, sizes 36-41, black/nude",12000,50,"Local Brand",514],
["Women Bodycon Dress","Stretch bodycon midi dress, sleeveless, sizes XS-XL, black/red/navy",16000,40,"Local Brand",515],
["Women Tote Bag Canvas","Canvas tote bag, zip closure, inner pocket, shoulder strap, natural",8500,60,"Local Brand",516],
["Women Running Shoes","Lightweight mesh, cushioned sole, breathable, sizes 36-41, pink/white",28000,35,"Nike",517],
["Women Wrap Skirt","Chiffon wrap skirt, midi length, floral print, sizes XS-XL, multi",10000,45,"Local Brand",518],
["Women Sunglasses Cat Eye","UV400 cat eye sunglasses, metal frame, gradient lens, with case",7500,55,"Local Brand",519],
["Women Hoodie Oversized","Fleece oversized hoodie, kangaroo pocket, sizes XS-XL, pastel colors",16000,50,"Local Brand",520],
["Women Formal Trousers","High waist straight leg trousers, polyester, sizes XS-XL, black/grey",14000,40,"Local Brand",521],
["Women Bikini Set","2-piece bikini, adjustable straps, sizes XS-XL, multi-color prints",12000,35,"Local Brand",522],
["Women Leather Jacket","Faux leather biker jacket, zip closure, sizes XS-XL, black/brown",35000,20,"Local Brand",523],
["Women Hair Accessories Set","Scrunchies + clips + headbands set, 20 pieces, multi-color",5000,80,"Local Brand",524],
["Women Scarf Silk","100% polyester silk scarf, 90x90cm, multi-pattern, gift-ready",8000,50,"Local Brand",525],
["Women Jumpsuit","Sleeveless wide-leg jumpsuit, V-neck, sizes XS-XL, black/white/navy",18000,35,"Local Brand",526],
["Women Sandals Flat","Faux leather flat sandals, ankle strap, sizes 36-41, tan/black/white",10000,50,"Local Brand",527],
["Women Clutch Bag Evening","Satin evening clutch, chain strap, rhinestone detail, multi-color",9500,40,"Local Brand",528],
["Women Yoga Pants","High waist yoga pants, 4-way stretch, moisture-wicking, XS-XL",11000,55,"Local Brand",529],
["Women Winter Coat","Wool blend long coat, belt, sizes XS-XL, camel/black/grey",45000,20,"Local Brand",530]
];
foreach($p6 as $p) $products[]=[$p[0],$p[1],$p[2],6,$p[3],$p[4],$p[5]];

// ── CAT 7: Groceries & Food (35 products) ──
$p7 = [
["Inyange Milk 1L","Fresh whole milk, pasteurized, 1 liter pack, locally produced Rwanda",1200,200,"Inyange",601],
["Inyange Yogurt 500ml","Strawberry/vanilla/plain yogurt, 500ml, no preservatives, chilled",1500,150,"Inyange",602],
["Akabanga Chili Oil 50ml","Rwanda's famous hot chili oil, 50ml bottle, authentic Rwandan flavor",2500,300,"Akabanga",603],
["Isange Rice 5kg","Premium long grain white rice, 5kg bag, locally grown in Rwanda",6500,100,"Isange",604],
["Cooking Oil Kimbo 2L","Refined vegetable cooking oil, 2 liters, cholesterol-free",4500,120,"Kimbo",605],
["Nido Milk Powder 400g","Full cream milk powder, 400g tin, fortified with vitamins A & D",5500,80,"Nestle",606],
["Lipton Yellow Label Tea 100","Black tea bags, 100 pack, classic blend, rich flavor",3500,150,"Lipton",607],
["Nescafe Classic 200g","Instant coffee, 200g jar, rich aroma, smooth taste",6000,90,"Nestle",608],
["Indomie Noodles 70g x10","Instant noodles, chicken flavor, 10-pack bundle, quick meal",3000,200,"Indomie",609],
["Colgate Toothpaste 150ml","Cavity protection toothpaste, fluoride, fresh mint, 150ml",2200,180,"Colgate",610],
["Ariel Detergent 1kg","Washing powder, 1kg, removes tough stains, fresh scent",3800,130,"Ariel",611],
["Dettol Soap 3-Pack","Antibacterial bar soap, 3x100g, original scent, kills 99.9% germs",2800,200,"Dettol",612],
["Heinz Tomato Ketchup 570g","Classic tomato ketchup, 570g squeeze bottle, no artificial colors",4200,100,"Heinz",613],
["Pringles Original 165g","Crispy potato chips, original flavor, 165g can, perfect snack",3500,120,"Pringles",614],
["Coca-Cola 1.5L","Carbonated soft drink, 1.5L bottle, chilled and refreshing",1800,250,"Coca-Cola",615],
["Inyange Butter 250g","Pure cow butter, 250g, unsalted, locally produced in Rwanda",3500,100,"Inyange",616],
["Isange Maize Flour 2kg","Fine maize flour, 2kg, for ugali/porridge, locally milled Rwanda",2500,150,"Isange",617],
["Kimbo Cooking Fat 500g","Vegetable cooking fat, 500g, for frying and baking, white",2800,120,"Kimbo",618],
["Nestle Milo 400g","Chocolate malt drink powder, 400g tin, energy drink for kids",5500,90,"Nestle",619],
["Lipton Green Tea 25 bags","Green tea bags, 25 pack, antioxidant-rich, light refreshing taste",2500,120,"Lipton",620],
["Nescafe 3-in-1 x10","Instant coffee sachets, 3-in-1, 10 pack, coffee+milk+sugar",3000,150,"Nestle",621],
["Sprite 1.5L","Lemon-lime carbonated soft drink, 1.5L bottle, refreshing",1800,200,"Coca-Cola",622],
["Fanta Orange 1.5L","Orange carbonated soft drink, 1.5L bottle, fruity and sweet",1800,200,"Coca-Cola",623],
["Omo Detergent 1kg","Washing powder, 1kg, active clean, removes tough stains, fresh",3500,130,"Omo",624],
["Sunlight Dish Soap 750ml","Dish washing liquid, 750ml, lemon scent, cuts grease fast",2500,150,"Sunlight",625],
["Dettol Liquid Soap 250ml","Antibacterial liquid hand soap, 250ml pump, original scent",3000,120,"Dettol",626],
["Kellogg's Corn Flakes 500g","Breakfast cereal, 500g, fortified with vitamins, crispy flakes",5500,80,"Kellogg's",627],
["Quaker Oats 1kg","Rolled oats, 1kg, high fiber, heart healthy, quick cook",4500,90,"Quaker",628],
["Cadbury Bournvita 500g","Chocolate malt drink, 500g, calcium + vitamins, energy boost",5000,85,"Cadbury",629],
["Royco Beef Cubes x24","Beef seasoning cubes, 24 pack, rich flavor, for soups and stews",2000,200,"Royco",630],
["Maggi Tomato Paste 400g","Concentrated tomato paste, 400g tin, rich color and flavor",2500,150,"Maggi",631],
["Soya Chunks 500g","Textured soya protein, 500g, high protein, meat substitute",3500,100,"Local Brand",632],
["Groundnut Paste 500g","Pure groundnut paste, 500g, no additives, locally made Rwanda",3000,120,"Local Brand",633],
["Honey Pure 500g","Pure natural honey, 500g jar, locally harvested Rwanda, no additives",8500,60,"Local Brand",634],
["Isange Wheat Flour 2kg","All-purpose wheat flour, 2kg, for bread/cakes/chapati, fine grind",3000,130,"Isange",635]
];
foreach($p7 as $p) $products[]=[$p[0],$p[1],$p[2],7,$p[3],$p[4],$p[5]];

// ── CAT 8: Health & Beauty (30 products) ──
$p8 = [
["Nivea Body Lotion 400ml","Moisturizing body lotion, shea butter, 400ml, 48hr moisture",5500,80,"Nivea",701],
["Neutrogena Face Wash 200ml","Oil-free acne wash, salicylic acid, 200ml, gentle daily cleanser",8500,60,"Neutrogena",702],
["Garnier Vitamin C Serum 30ml","Brightening serum, 30ml, vitamin C + niacinamide, reduces dark spots",12000,50,"Garnier",703],
["Dove Shampoo 400ml","Moisturizing shampoo, 400ml, intensive repair, for dry/damaged hair",5000,90,"Dove",704],
["Oral-B Electric Toothbrush","Rechargeable, 2 modes, 2-min timer, removes 100% more plaque",35000,25,"Oral-B",705],
["Centrum Multivitamin 30tabs","Complete multivitamin, 30 tablets, vitamins A-Z, immune support",8000,70,"Centrum",706],
["Vaseline Petroleum Jelly 250g","Pure petroleum jelly, 250g, heals dry skin, lips, elbows",3500,120,"Vaseline",707],
["Gillette Fusion Razor","5-blade precision razor, lubricating strip, FlexBall, 2 cartridges",9500,55,"Gillette",708],
["Maybelline Lipstick","Superstay matte ink lipstick, 16hr wear, 20 shades, transfer-proof",7500,65,"Maybelline",709],
["Dettol Sanitizer 500ml","Alcohol-based hand sanitizer, 500ml pump, kills 99.9% bacteria",4500,100,"Dettol",710],
["Nivea Men Face Wash 100ml","Oil control face wash, 100ml, charcoal, deep cleansing, for men",4500,70,"Nivea",711],
["L'Oreal Shampoo 400ml","Elvive extraordinary oil shampoo, 400ml, for dry/damaged hair",6500,60,"L'Oreal",712],
["Garnier Micellar Water 400ml","Micellar cleansing water, 400ml, removes makeup, no rinse needed",7500,55,"Garnier",713],
["Dove Body Wash 500ml","Moisturizing body wash, 500ml, shea butter, gentle on skin",5500,75,"Dove",714],
["Colgate Mouthwash 500ml","Antibacterial mouthwash, 500ml, kills 99.9% germs, fresh breath",4500,80,"Colgate",715],
["Pantene Conditioner 400ml","Repair and protect conditioner, 400ml, for damaged hair, keratin",5500,65,"Pantene",716],
["Neutrogena Sunscreen SPF50","Sunscreen lotion, SPF50, 88ml, broad spectrum UVA/UVB protection",8500,50,"Neutrogena",717],
["Maybelline Foundation","Fit Me matte + poreless foundation, 30ml, 40 shades, SPF22",9500,55,"Maybelline",718],
["Revlon Mascara","Volume + length mascara, waterproof, 8ml, black, buildable formula",7000,60,"Revlon",719],
["Gillette Venus Razor Women","3-blade women's razor, moisture ribbon, 2 cartridges, ergonomic",8500,50,"Gillette",720],
["Dettol Antiseptic Liquid 500ml","Antiseptic liquid, 500ml, for wounds/skin/surface disinfection",5500,90,"Dettol",721],
["Nivea Lip Balm SPF15","Moisturizing lip balm, SPF15, 4.8g, essential care, 24hr moisture",2500,100,"Nivea",722],
["Garnier BB Cream 50ml","All-in-one BB cream, SPF25, 50ml, moisturize+cover+protect",8000,55,"Garnier",723],
["Dove Deodorant 150ml","Antiperspirant deodorant spray, 150ml, 48hr protection, gentle",4500,80,"Dove",724],
["Oral-B Toothbrush 3-Pack","Soft bristle toothbrush, 3-pack, indicator bristles, ergonomic",5500,70,"Oral-B",725],
["Centrum Women Multivitamin","Women's multivitamin, 60 tablets, iron + folic acid + vitamins",12000,45,"Centrum",726],
["Bioderma Sensibio H2O 250ml","Micellar water, 250ml, for sensitive skin, removes makeup gently",15000,35,"Bioderma",727],
["The Ordinary Niacinamide 30ml","10% niacinamide + 1% zinc serum, 30ml, reduces pores + blemishes",12000,40,"The Ordinary",728],
["Cetaphil Moisturizing Cream 250g","Gentle moisturizing cream, 250g, for sensitive/dry skin, fragrance-free",14000,40,"Cetaphil",729],
["Himalaya Face Wash 150ml","Purifying neem face wash, 150ml, removes oil and impurities, gentle",4500,75,"Himalaya",730]
];
foreach($p8 as $p) $products[]=[$p[0],$p[1],$p[2],8,$p[3],$p[4],$p[5]];

// ── CAT 9: Sports & Fitness (30 products) ──
$p9 = [
["Nike Air Max Running Shoes","Air Max cushioning, breathable mesh, sizes 39-46, black/white/red",85000,30,"Nike",801],
["Adidas Training T-Shirt","Climalite moisture-wicking, slim fit, sizes S-XXL, multi-color",18000,60,"Adidas",802],
["Yoga Mat 6mm","Non-slip TPE yoga mat, 183x61cm, 6mm thick, carrying strap",15000,45,"Local Brand",803],
["Adjustable Dumbbell Set 20kg","Adjustable dumbbell pair, 2x10kg, cast iron, rubber grip",65000,20,"Local Brand",804],
["Jump Rope Speed","Speed jump rope, ball bearings, adjustable length, foam handles",5500,80,"Local Brand",805],
["Resistance Bands Set 5pcs","5 resistance levels, latex bands, door anchor + handles included",12000,55,"Local Brand",806],
["Football Adidas Size 5","FIFA quality pro football, size 5, thermally bonded, all-weather",22000,35,"Adidas",807],
["Cycling Helmet Adult","57-61cm, 21 vents, adjustable fit, CE certified, multiple colors",18000,25,"Local Brand",808],
["Whey Protein 1kg","Whey protein isolate, 1kg, chocolate/vanilla, 25g protein/serving",45000,30,"Optimum Nutrition",809],
["Water Bottle 1L Insulated","Stainless steel, 1L, keeps cold 24hr/hot 12hr, leak-proof",8500,70,"Local Brand",810],
["Nike Dri-FIT Shorts","Polyester training shorts, elastic waist, sizes S-XXL, multi-color",14000,50,"Nike",811],
["Adidas Ultraboost 22","Running shoes, Boost midsole, Primeknit upper, sizes 39-46",120000,15,"Adidas",812],
["Pull-Up Bar Doorway","Doorway pull-up bar, no screws, 70-100cm adjustable, 150kg max",18000,30,"Local Brand",813],
["Gym Gloves Weightlifting","Padded gym gloves, wrist support, sizes S-XL, anti-slip grip",8500,45,"Local Brand",814],
["Treadmill Manual 3-Level","Manual treadmill, 3 incline levels, LCD display, foldable, home gym",280000,8,"Local Brand",815],
["Exercise Bike Stationary","Stationary bike, 8 resistance levels, LCD display, adjustable seat",220000,6,"Local Brand",816],
["Basketball Spalding Size 7","Official size 7 basketball, rubber, indoor/outdoor, orange",18000,30,"Spalding",817],
["Tennis Racket Wilson","Wilson Clash 100, graphite frame, 300g, grip size 4 3/8, with bag",65000,15,"Wilson",818],
["Swimming Goggles Anti-Fog","Anti-fog swimming goggles, UV protection, adjustable strap, adult",8500,40,"Speedo",819],
["Foam Roller 45cm","High-density foam roller, 45cm, for muscle recovery and massage",12000,35,"Local Brand",820],
["Kettlebell 16kg","Cast iron kettlebell, 16kg, powder-coated, flat base, home gym",35000,20,"Local Brand",821],
["Skipping Rope Weighted","Weighted skipping rope, 500g, adjustable length, foam handles",8500,45,"Local Brand",822],
["Badminton Set 4-Player","4 rackets + 3 shuttlecocks + net, carry bag, for outdoor/indoor",22000,25,"Local Brand",823],
["Volleyball Mikasa Size 5","Official size 5 volleyball, soft touch, indoor/outdoor, blue/yellow",18000,28,"Mikasa",824],
["Gym Bag Sports 40L","Polyester gym bag, shoe compartment, wet pocket, shoulder strap",18000,35,"Local Brand",825],
["Ankle Weights 2kg Pair","2kg ankle weights pair, adjustable velcro, for walking/exercise",8500,40,"Local Brand",826],
["Push-Up Handles Pair","Ergonomic push-up handles, non-slip, 360° rotation, steel frame",8000,45,"Local Brand",827],
["Ab Roller Wheel","Ab roller wheel, non-slip handles, knee pad included, core workout",8500,40,"Local Brand",828],
["Cycling Gloves Half-Finger","Half-finger cycling gloves, padded palm, sizes S-XL, breathable",6500,35,"Local Brand",829],
["Sports Compression Socks","Compression socks, sizes S-XL, for running/cycling, anti-blister",4500,60,"Local Brand",830]
];
foreach($p9 as $p) $products[]=[$p[0],$p[1],$p[2],9,$p[3],$p[4],$p[5]];

// ── CAT 10: Baby & Kids (25 products) ──
$p10 = [
["Pampers Diapers Size 3 x52","Soft & dry diapers, size 3 (6-10kg), 52 count, 12hr protection",12000,80,"Pampers",901],
["Johnson Baby Lotion 500ml","Gentle baby lotion, 500ml, clinically proven mild, 24hr moisture",6500,90,"Johnson's",902],
["Baby Feeding Bottle Set","BPA-free bottles, 3-pack (150ml+250ml+300ml), anti-colic nipple",8500,60,"Philips Avent",903],
["Kids School Backpack","Lightweight school bag, 20L, padded straps, multiple pockets, 6-12yr",9500,55,"Local Brand",904],
["Lego Classic Bricks 484pcs","Classic building bricks, 484 pieces, 33 colors, ages 4+",28000,25,"Lego",905],
["Baby Stroller Lightweight","Foldable stroller, 0-36 months, 5-point harness, sun canopy",85000,10,"Graco",906],
["Kids Bicycle 16in","16in wheel bicycle, training wheels, ages 4-7, multiple colors",45000,15,"Local Brand",907],
["Educational Puzzle 100pcs","Wooden jigsaw puzzle, 100 pieces, animals theme, ages 3+",6500,50,"Local Brand",908],
["Baby Monitor WiFi","WiFi baby monitor, 1080p camera, night vision, 2-way audio",65000,12,"Motorola",909],
["Kids Clothing Set 3-piece","Cotton t-shirt + shorts + cap set, ages 1-8, bright colors",7500,70,"Local Brand",910],
["Pampers Diapers Size 4 x44","Soft & dry diapers, size 4 (9-14kg), 44 count, 12hr protection",12000,75,"Pampers",911],
["Johnson Baby Shampoo 500ml","Gentle baby shampoo, 500ml, no tears formula, mild and gentle",5500,85,"Johnson's",912],
["Baby Carrier Ergonomic","Ergonomic baby carrier, 0-36 months, lumbar support, breathable",45000,15,"Ergobaby",913],
["Kids Tablet 7in","7in kids tablet, Android, parental controls, shockproof case, 32GB",85000,12,"Local Brand",914],
["Baby Crib Wooden","Wooden baby crib, adjustable mattress height, safety rails, white",180000,6,"Local Brand",915],
["Kids Scooter 3-Wheel","3-wheel scooter, adjustable height, ages 3-8, LED wheels, foldable",28000,20,"Local Brand",916],
["Baby Food Maker","Steam + blend baby food maker, 400ml, BPA-free, easy clean",45000,12,"Philips Avent",917],
["Kids Drawing Set 60pcs","60-piece drawing set, crayons + colored pencils + markers, ages 3+",8500,45,"Local Brand",918],
["Baby Bath Tub","Ergonomic baby bath tub, non-slip, 0-24 months, with temperature gauge",12000,30,"Local Brand",919],
["Kids Sneakers Velcro","Canvas sneakers, velcro closure, sizes 20-35, colorful, easy on/off",8500,50,"Local Brand",920],
["Baby Blanket Soft","Super soft fleece blanket, 100x75cm, machine washable, multi-color",5500,60,"Local Brand",921],
["Kids Lunch Box Set","Stainless steel lunch box + water bottle set, leak-proof, ages 3+",8500,45,"Local Brand",922],
["Baby Teether Set 5pcs","BPA-free silicone teethers, 5-pack, different shapes, easy to hold",4500,55,"Local Brand",923],
["Kids Helmet Bicycle","Kids bicycle helmet, sizes 48-54cm, ages 3-8, CE certified, colorful",8500,30,"Local Brand",924],
["Baby Wipes 80pcs x3","Gentle baby wipes, 80 count x3 packs, fragrance-free, alcohol-free",4500,100,"Pampers",925]
];
foreach($p10 as $p) $products[]=[$p[0],$p[1],$p[2],10,$p[3],$p[4],$p[5]];

// ── CAT 11: Furniture & Decor (25 products) ──
$p11 = [
["3-Seater Sofa Fabric","Modern fabric sofa, 3-seater, foam cushions, wooden legs, grey/beige",280000,8,"Local Brand",1001],
["Office Chair Ergonomic","Ergonomic office chair, lumbar support, adjustable height, mesh back",95000,15,"Local Brand",1002],
["Wooden Dining Table 6-Seater","Solid wood dining table, 6-seater, 160x90cm, natural finish",320000,5,"Local Brand",1003],
["Queen Size Bed Frame","Wooden queen bed frame, 160x200cm, headboard, slats included",280000,6,"Local Brand",1004],
["Wardrobe 3-Door","3-door wardrobe, mirror, hanging rail + shelves, white/brown",380000,5,"Local Brand",1005],
["Bookshelf 5-Tier","5-tier wooden bookshelf, 180x80x30cm, natural/white, easy assemble",65000,15,"Local Brand",1006],
["Coffee Table Wooden","Solid wood coffee table, 120x60x45cm, natural finish, living room",55000,12,"Local Brand",1007],
["TV Stand 55in","TV stand for up to 55in TV, 2 shelves, cable management, black/white",45000,15,"Local Brand",1008],
["Curtains Blackout 2-Panel","Blackout curtains, 2 panels, 140x250cm, thermal insulated, multi-color",22000,30,"Local Brand",1009],
["Wall Clock Modern","Silent wall clock, 30cm diameter, wooden frame, minimalist design",8500,40,"Local Brand",1010],
["Floor Lamp LED","LED floor lamp, 3 color temperatures, dimmable, 150cm, modern design",35000,20,"Local Brand",1011],
["Throw Pillow Set 4pcs","Decorative throw pillows, 4-pack, 45x45cm, various patterns, soft",12000,35,"Local Brand",1012],
["Area Rug 200x300cm","Non-slip area rug, 200x300cm, geometric pattern, easy clean",65000,10,"Local Brand",1013],
["Desk Study Table","Study desk, 120x60cm, 2 drawers, cable hole, white/brown",55000,12,"Local Brand",1014],
["Bedside Table 2-Drawer","Bedside table, 2 drawers, 45x40x55cm, white/natural wood finish",28000,20,"Local Brand",1015],
["Shower Curtain Waterproof","Waterproof shower curtain, 180x200cm, 12 hooks included, multi-design",8500,40,"Local Brand",1016],
["Kitchen Cabinet 2-Door","2-door kitchen cabinet, 80x40x85cm, stainless steel handles, white",85000,8,"Local Brand",1017],
["Plastic Storage Drawer 5-Tier","5-tier plastic storage drawers, 40x30x100cm, transparent, stackable",22000,25,"Local Brand",1018],
["Bathroom Mirror 60x80cm","Frameless bathroom mirror, 60x80cm, wall-mounted, beveled edge",18000,20,"Local Brand",1019],
["Outdoor Garden Chair","Plastic garden chair, stackable, UV-resistant, 120kg capacity, white",8500,30,"Local Brand",1020],
["Mattress 6in Queen","6-inch foam mattress, queen size 160x200cm, medium firm, washable cover",180000,8,"Local Brand",1021],
["Laundry Basket Wicker","Wicker laundry basket, 60L, lid, handles, natural/white, bathroom",12000,25,"Local Brand",1022],
["Picture Frame Set 5pcs","Photo frames set, 5 pieces (4x6 to 8x10in), wall/desk, black/white",8500,35,"Local Brand",1023],
["Candle Set Scented 6pcs","Scented soy candles, 6-pack, 150g each, lavender/vanilla/rose",12000,30,"Local Brand",1024],
["Doormat Non-Slip","Non-slip doormat, 60x40cm, rubber backing, easy clean, multi-design",5500,50,"Local Brand",1025]
];
foreach($p11 as $p) $products[]=[$p[0],$p[1],$p[2],11,$p[3],$p[4],$p[5]];

// ── CAT 12: Car Accessories (20 products) ──
$p12 = [
["Car Phone Holder Dashboard","Universal dashboard phone holder, 360° rotation, for 4-7in phones",8500,40,"Local Brand",1101],
["Car Dash Camera 1080p","Full HD 1080p dash cam, night vision, loop recording, G-sensor",45000,20,"Vantrue",1102],
["Car Seat Cover Set","Universal seat covers, 9-piece set, waterproof, black/grey/beige",28000,25,"Local Brand",1103],
["Car Air Freshener","Long-lasting car air freshener, 60-day, multiple scents, vent clip",3500,80,"Febreze",1104],
["Car Jump Starter 12000mAh","Portable jump starter, 12000mAh, 1000A peak, USB charging, LED",55000,15,"NOCO",1105],
["Car Vacuum Cleaner 12V","12V portable car vacuum, 120W, wet/dry, 5m cord, HEPA filter",18000,25,"Local Brand",1106],
["Tire Pressure Gauge Digital","Digital tire pressure gauge, 0-100 PSI, LCD display, backlit",8500,35,"Local Brand",1107],
["Car USB Charger Dual","Dual USB car charger, 36W total, fast charge 3.0, compact design",5500,50,"Anker",1108],
["Car Floor Mats Rubber","Universal rubber floor mats, 4-piece set, waterproof, easy clean",12000,30,"Local Brand",1109],
["Car Steering Wheel Cover","Leather steering wheel cover, 37-38cm, anti-slip, black/brown/red",8500,40,"Local Brand",1110],
["Car Reverse Camera","Backup camera, 170° wide angle, night vision, waterproof, universal",22000,20,"Local Brand",1111],
["Car Bluetooth FM Transmitter","Bluetooth 5.0 FM transmitter, USB charging, hands-free, LCD display",12000,30,"Local Brand",1112],
["Car Wax Polish 500ml","Car wax polish, 500ml, UV protection, deep shine, easy application",8500,35,"Turtle Wax",1113],
["Car Sunshade Windshield","Foldable windshield sunshade, 130x60cm, UV protection, reflective",5500,45,"Local Brand",1114],
["Car First Aid Kit","25-piece car first aid kit, reflective triangle, fire extinguisher",18000,20,"Local Brand",1115],
["Car Seat Cushion Memory Foam","Memory foam seat cushion, non-slip, lumbar support, universal fit",18000,25,"Local Brand",1116],
["Car Organizer Trunk","Trunk organizer, 3 compartments, foldable, non-slip bottom, black",12000,30,"Local Brand",1117],
["Car Tyre Inflator 12V","12V portable tyre inflator, digital display, auto shut-off, 150PSI",22000,20,"Local Brand",1118],
["Car Wash Shampoo 1L","Car wash shampoo, 1L, pH neutral, high foam, streak-free shine",5500,40,"Meguiar's",1119],
["Car GPS Navigator 7in","7in GPS navigator, offline maps Africa, voice guidance, speed camera",65000,12,"Garmin",1120]
];
foreach($p12 as $p) $products[]=[$p[0],$p[1],$p[2],12,$p[3],$p[4],$p[5]];

// ── CAT 13: Books & Stationery (20 products) ──
$p13 = [
["Bic Ballpoint Pens 50-Pack","Blue/black/red ballpoint pens, 50-pack, smooth writing, 1.0mm",5500,100,"Bic",1201],
["A4 Printing Paper 500 Sheets","80gsm A4 printing paper, 500 sheets, bright white, for all printers",5000,80,"Double A",1202],
["Spiral Notebook A4 200 Pages","A4 spiral notebook, 200 pages, ruled, hard cover, multiple colors",3500,90,"Local Brand",1203],
["Scientific Calculator Casio","Casio FX-991EX, 552 functions, natural display, solar+battery",18000,40,"Casio",1204],
["Geometry Set 9-Piece","9-piece geometry set, compass + ruler + protractor + set squares",3500,80,"Local Brand",1205],
["Highlighter Set 6 Colors","Chisel tip highlighters, 6 colors, water-based, non-toxic, bright",4500,70,"Stabilo",1206],
["Stapler Heavy Duty","Heavy duty stapler, 25 sheets capacity, with 1000 staples, metal",8500,35,"Rapid",1207],
["Sticky Notes 3x3 12-Pack","Sticky notes, 3x3 inch, 12 pads x 100 sheets, multi-color",5500,60,"Post-it",1208],
["File Folder A4 10-Pack","A4 file folders, 10-pack, cardboard, assorted colors, for documents",3500,70,"Local Brand",1209],
["Whiteboard Marker Set 8","Dry-erase whiteboard markers, 8 colors, chisel tip, low odor",4500,55,"Expo",1210],
["Pencil Case Large","Large pencil case, 3 compartments, zipper, for students, multi-color",5500,60,"Local Brand",1211],
["Ruler 30cm Metal","Stainless steel ruler, 30cm, metric + imperial, non-slip backing",2500,80,"Local Brand",1212],
["Correction Fluid 20ml","White correction fluid, 20ml, fast-dry, smooth application, 3-pack",3000,70,"Tipp-Ex",1213],
["Index Cards 200pcs","Blank index cards, 200 pieces, 4x6 inch, white, for notes/flashcards",3500,60,"Local Brand",1214],
["Clipboard A4 Hardboard","A4 hardboard clipboard, low-profile clip, for office/school/field",4500,45,"Local Brand",1215],
["Scissors Stainless 21cm","Stainless steel scissors, 21cm, sharp, ergonomic handle, multi-use",3500,55,"Fiskars",1216],
["Glue Stick 40g 6-Pack","Glue sticks, 40g each, 6-pack, washable, non-toxic, for school",4500,65,"UHU",1217],
["Lever Arch File A4","A4 lever arch file, 75mm spine, 500 sheet capacity, assorted colors",4500,50,"Local Brand",1218],
["Pencil HB 12-Pack","HB pencils, 12-pack, pre-sharpened, hexagonal barrel, for school",3000,80,"Staedtler",1219],
["Eraser Set 10-Pack","White vinyl erasers, 10-pack, smudge-free, for pencil and ink",2500,90,"Faber-Castell",1220]
];
foreach($p13 as $p) $products[]=[$p[0],$p[1],$p[2],13,$p[3],$p[4],$p[5]];

// ── CAT 14: Jewelry & Watches (20 products) ──
$p14 = [
["Casio G-Shock GA-2100","Digital-analog watch, shock resistant, 200m water resistant, black",85000,20,"Casio",1301],
["Fossil Gen 6 Smartwatch","Smartwatch, 44mm, AMOLED, Wear OS, heart rate, GPS, leather strap",280000,10,"Fossil",1302],
["Seiko 5 Automatic Watch","Automatic watch, 38mm, stainless steel, 100m water resistant, classic",180000,12,"Seiko",1303],
["Casio Edifice EFR-539","Chronograph watch, stainless steel, 100m water resistant, elegant",95000,15,"Casio",1304],
["Women Rose Gold Watch","Rose gold case, mesh strap, quartz, 36mm, water resistant, elegant",45000,25,"Local Brand",1305],
["Men Stainless Steel Bracelet","Stainless steel chain bracelet, 21cm, silver/gold, magnetic clasp",8500,50,"Local Brand",1306],
["Women Pearl Necklace","Freshwater pearl necklace, 45cm, sterling silver clasp, gift box",22000,20,"Local Brand",1307],
["Men Leather Bracelet Set","Leather bracelet set, 3-piece, adjustable, brown/black, casual",6500,45,"Local Brand",1308],
["Women Gold Hoop Earrings","18K gold-plated hoop earrings, 30mm diameter, hypoallergenic",8500,40,"Local Brand",1309],
["Men Signet Ring Stainless","Stainless steel signet ring, sizes 18-22mm, silver/gold, polished",6500,35,"Local Brand",1310],
["Women Crystal Pendant Necklace","Crystal pendant necklace, 45cm chain, silver/gold, gift box",12000,30,"Local Brand",1311],
["Couple Matching Watches Set","Matching watches set, his & hers, quartz, leather strap, gift box",55000,15,"Local Brand",1312],
["Women Charm Bracelet","Sterling silver charm bracelet, 5 charms, adjustable, gift box",18000,25,"Local Brand",1313],
["Men Titanium Ring","Titanium ring, comfort fit, sizes 18-24mm, brushed finish, durable",8500,30,"Local Brand",1314],
["Women Anklet Gold","18K gold-plated anklet, 25cm adjustable, delicate chain, elegant",5500,40,"Local Brand",1315],
["Men Watch Box 6-Slot","6-slot watch box, glass lid, velvet interior, lock, display case",18000,20,"Local Brand",1316],
["Women Jewelry Set 5-Piece","Necklace + earrings + bracelet + ring + anklet set, silver/gold",22000,25,"Local Brand",1317],
["Garmin Forerunner 55","GPS running watch, heart rate, 20hr battery, sleep tracking, 5ATM",280000,8,"Garmin",1318],
["Apple Watch SE 2nd Gen","40mm, OLED, heart rate, crash detection, swim-proof, GPS",480000,7,"Apple",1319],
["Samsung Galaxy Watch 6","40mm, AMOLED, BIA sensor, sleep coaching, 5ATM, Wear OS",380000,8,"Samsung",1320]
];
foreach($p14 as $p) $products[]=[$p[0],$p[1],$p[2],14,$p[3],$p[4],$p[5]];

// ── CAT 15: Gaming & Electronics (25 products) ──
$p15 = [
["PlayStation 5 Console","PS5 disc edition, 825GB SSD, 4K gaming, DualSense controller, HDMI 2.1",950000,5,"Sony",1401],
["Xbox Series X","1TB SSD, 4K 120fps, Quick Resume, Game Pass ready, HDMI 2.1",880000,5,"Microsoft",1402],
["Nintendo Switch OLED","7in OLED screen, 64GB, Joy-Con controllers, dock, handheld/TV mode",480000,8,"Nintendo",1403],
["PS5 DualSense Controller","Wireless controller, haptic feedback, adaptive triggers, USB-C",85000,15,"Sony",1404],
["Xbox Wireless Controller","Wireless controller, textured grip, USB-C, 40hr battery, multi-color",65000,18,"Microsoft",1405],
["Gaming Headset HyperX Cloud II","7.1 surround sound, 53mm drivers, noise-cancelling mic, USB/3.5mm",85000,15,"HyperX",1406],
["Gaming Monitor 27in 144Hz","27in FHD IPS, 144Hz, 1ms, AMD FreeSync, HDMI+DP, for gaming",280000,10,"Asus",1407],
["Gaming Chair RGB","Ergonomic gaming chair, lumbar support, adjustable armrests, RGB LED",180000,8,"Local Brand",1408],
["Mechanical Keyboard Gaming","RGB mechanical keyboard, blue switches, TKL layout, USB-C, anti-ghost",85000,12,"Redragon",1409],
["Gaming Mouse 16000 DPI","Wired gaming mouse, 16000 DPI, 7 buttons, RGB, ergonomic, 1000Hz",35000,20,"Logitech",1410],
["Nintendo Switch Games Bundle","Mario Kart 8 + Zelda BOTW + Animal Crossing, digital codes",95000,10,"Nintendo",1411],
["PS5 Game FIFA 24","FIFA 24 for PS5, 4K 60fps, HyperMotion V, Ultimate Team, disc",45000,20,"EA Sports",1412],
["Xbox Game Pass 3 Months","3-month Xbox Game Pass Ultimate, 100+ games, cloud gaming, code",28000,30,"Microsoft",1413],
["Razer Kraken Headset","Wired gaming headset, 50mm drivers, cooling gel ear cushions, 3.5mm",65000,15,"Razer",1414],
["Gaming Desk 140cm","140x60cm gaming desk, cable management, cup holder, headset hook",95000,8,"Local Brand",1415],
["Capture Card 4K","4K HDMI capture card, USB 3.0, for streaming/recording, plug-and-play",55000,12,"Elgato",1416],
["VR Headset Meta Quest 2","128GB standalone VR headset, 6DOF, hand tracking, 2hr battery",480000,5,"Meta",1417],
["Gaming Router WiFi 6","AX5400 gaming router, 2.5G port, QoS, MU-MIMO, low latency",180000,8,"Asus",1418],
["SSD 500GB NVMe","500GB NVMe M.2 SSD, 3500MB/s read, PCIe 3.0, for gaming PC upgrade",85000,15,"Samsung",1419],
["RAM 32GB DDR4 Gaming","32GB (2x16GB) DDR4 3600MHz, RGB, CL18, for gaming PC, Intel/AMD",95000,10,"Corsair",1420],
["Streaming Microphone USB","USB condenser microphone, cardioid, for streaming/podcast, RGB stand",65000,12,"Blue Yeti",1421],
["Gaming Mousepad XL","XL gaming mousepad, 900x400mm, stitched edges, non-slip rubber base",12000,25,"Redragon",1422],
["Controller Charging Dock","Dual controller charging dock for PS5/Xbox, LED indicator, USB-C",18000,20,"Local Brand",1423],
["Portable Gaming Console","Handheld gaming console, 3.5in screen, 10000 games built-in, HDMI out",45000,15,"Local Brand",1424],
["Gaming Glasses Anti-Blue","Anti-blue light gaming glasses, UV400, clear lens, reduces eye strain",8500,30,"Local Brand",1425]
];
foreach($p15 as $p) $products[]=[$p[0],$p[1],$p[2],15,$p[3],$p[4],$p[5]];

// ── INSERT ALL PRODUCTS ──────────────────────────────────────
$total = count($products);
echo "<span class='info'>Inserting $total products...</span>\n"; flush();

$inserted = 0;
foreach($products as $p){
    [$name,$desc,$price,$cat,$stock,$brand,$seed] = $p;
    $n = $conn->real_escape_string($name);
    $d = $conn->real_escape_string($desc);
    $b = $conn->real_escape_string($brand);
    $img = "products/p{$seed}.jpg";
    $conn->query("INSERT INTO products (name,description,price,image,category_id,stock,brand)
        VALUES ('$n','$d',$price,'$img',$cat,$stock,'$b')");
    $inserted++;
    if($inserted % 50 === 0){ echo "<span class='ok'>✓ $inserted / $total inserted...</span>\n"; flush(); }
}

echo "<span class='ok'>✓ DONE! $inserted products inserted successfully.</span>\n\n";

// Summary by category
echo "<span class='info'>── Summary by Category ──</span>\n";
$res = $conn->query("SELECT c.name, COUNT(p.id) as total FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.id");
while($row=$res->fetch_assoc()){
    echo "<span class='ok'>  {$row['name']}: {$row['total']} products</span>\n";
}

$grand = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
echo "\n<span class='ok'>✓ Total products in database: $grand</span>\n";
echo "<span class='ok'>✓ Admin login: ericniringiyimana123@gmail.com / admin123</span>\n";
echo "<span class='ok'>✓ Customer login: eric@gmail.com / admin123</span>\n";
echo "</pre><br><a href='/' style='color:#58a6ff;font-size:16px'>→ Go to Shop</a>
&nbsp;&nbsp;<a href='/ecommerce-chatbot/admin/' style='color:#f0883e;font-size:16px'>→ Admin Panel</a>
</body></html>";
