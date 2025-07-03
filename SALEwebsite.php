<?php
session_start();

// Cấu hình database (SQLite để đơn giản)
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:shop.db');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            die("Lỗi kết nối database: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        // Bảng users
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Bảng products
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            image TEXT,
            stock INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Bảng orders
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            customer_name TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            customer_address TEXT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Bảng order_items
$this->pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER,
    product_id INTEGER,
    quantity INTEGER NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
)");


        // Bảng coupons
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            discount_percent INTEGER NOT NULL,
            expires_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");


        // Bảng message
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id integer,
            message text not null,
            sender text not null check(sender in ('user', 'admin')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            foreign key(user_id) references users(id)
            )");

        // Bảng requests
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id integer,
            reason text,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            foreign key(order_id) references orders(id)
            )");
        
        // Tạo admin mặc định
        $this->createDefaultAdmin();
        $this->createSampleProducts();
    }
    
        private function createDefaultAdmin() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)")
                     ->execute(['admin', 'admin@shop.vn', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
        }
    }
    
    private function createSampleProducts() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM products");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $products = [
                ['Áo thun nam', 'Áo thun nam chất liệu cotton cao cấp', 299000, 'https://via.placeholder.com/300x300/007bff/ffffff?text=Áo+thun+nam', 50],
                ['Quần jeans', 'Quần jeans nam form slim fit', 599000, 'https://via.placeholder.com/300x300/28a745/ffffff?text=Quần+jeans', 30],
                ['Giày sneaker', 'Giày sneaker thể thao', 899000, 'https://via.placeholder.com/300x300/dc3545/ffffff?text=Giày+sneaker', 20],
                ['Túi xách', 'Túi xách nữ da PU cao cấp', 450000, 'https://via.placeholder.com/300x300/ffc107/ffffff?text=Túi+xách', 25]
            ];
            
            foreach ($products as $product) {
                $this->pdo->prepare("INSERT INTO products (name, description, price, image, stock) VALUES (?, ?, ?, ?, ?)")
                         ->execute($product);
            }
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

$db = new Database();
$pdo = $db->getConnection();

// Khởi tạo giỏ hàng
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Xử lý đăng ký
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password]);
        $message = "Đăng ký thành công! Vui lòng đăng nhập.";
    } catch (PDOException $e) {
        $error = "Lỗi đăng ký: Username hoặc email đã tồn tại.";
    }
}

// Xử lý đăng nhập
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $message = "Đăng nhập thành công!";
    } else {
        $error = "Tên đăng nhập hoặc mật khẩu không đúng.";
    }
}

// Xử lý đăng xuất
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Xử lý lưu tin nhắn vào database
if (isset($_POST['send_message']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("INSERT INTO messages (user_id, message, sender) VALUES (?, ?, 'user')");
    $stmt->execute([$_SESSION['user_id'], $_POST['chat_message']]);
}

// Xử lý yêu cầu dịch vụ trả hàng
if (isset($_POST['request_return']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("INSERT INTO returns (order_id, reason) VALUES (?, ?)");
    $stmt->execute([$_POST['return_order_id'], $_POST['return_reason']]);
    $message = "Đã gửi yêu cầu trả hàng!";
}


// Xử lý thêm sản phẩm (Admin)
if (isset($_POST['add_product']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $name = $_POST['product_name'];
    $description = $_POST['product_description'];
    $price = $_POST['product_price'];
    $stock = $_POST['product_stock'];
    $image = $_POST['product_image'];
    
// Xử lý upload ảnh
    if (isset($_FILES['product_image_file']) && $_FILES['product_image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['product_image_file']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['product_image_file']['tmp_name'], $upload_path)) {
            $image = $upload_path;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, image, stock) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $price, $image, $stock]);
    $message = "Thêm sản phẩm thành công!";
}

// Xử lý xóa sản phẩm (Admin)
if (isset($_POST['delete_product']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $product_id = $_POST['product_id'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $message = "Xóa sản phẩm thành công!";
}

// Xử lý cập nhật sản phẩm (Admin)
if (isset($_POST['update_product']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $product_id = $_POST['product_id'];
    $name = $_POST['product_name'];
    $description = $_POST['product_description'];
    $price = $_POST['product_price'];
    $stock = $_POST['product_stock'];
    $image = $_POST['current_image'];
    
    // Xử lý upload ảnh mới
    if (isset($_FILES['product_image_file']) && $_FILES['product_image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['product_image_file']['name'], PATHINFO_EXTENSION);
        $file_name = time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['product_image_file']['tmp_name'], $upload_path)) {
            $image = $upload_path;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, image = ?, stock = ? WHERE id = ?");
    $stmt->execute([$name, $description, $price, $image, $stock, $product_id]);
    $message = "Cập nhật sản phẩm thành công!";
}

// Xử lý thêm vào giỏ hàng
if (isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stock >= ?");
    $stmt->execute([$product_id, $quantity]);
    $product = $stmt->fetch();
    
    if ($product) {
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        $message = "Đã thêm sản phẩm vào giỏ hàng!";
    } else {
        $error = "Sản phẩm không đủ số lượng trong kho!";
    }
}

// Xử lý xóa khỏi giỏ hàng
if (isset($_POST['remove_from_cart'])) {
    $product_id = (int)$_POST['product_id'];
    unset($_SESSION['cart'][$product_id]);
    $message = "Đã xóa sản phẩm khỏi giỏ hàng!";
}

// Xử lý cập nhật số lượng giỏ hàng
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $product_id => $quantity) {
        if ($quantity > 0) {
            $_SESSION['cart'][$product_id] = (int)$quantity;
        } else {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    $message = "Cập nhật giỏ hàng thành công!";
}

// Xử lý đặt hàng
if (isset($_POST['checkout'])) {
    $customer_name = $_POST['customer_name'];
    $customer_phone = $_POST['customer_phone'];
    $customer_address = $_POST['customer_address'];
    
    if (!empty($customer_name) && !empty($customer_phone) && !empty($customer_address) && !empty($_SESSION['cart'])) {
        $total_amount = 0;
        $valid_order = true;

// Kiểm tra tính hợp lệ thông tin
    // if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    //     $error = "Email không hợp lệ!";
    // } elseif (!preg_match('/^[0-9]{10}$/', $customer_phone)) {
    //     $error = "Số điện thoại phải gồm 10 chữ số!";
    // }
        
        
        // Tính tổng tiền và kiểm tra tồn kho
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            $stmt = $pdo->prepare("SELECT price, stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product && $product['stock'] >= $quantity) {
                $total_amount += $product['price'] * $quantity;
            } else {
                $valid_order = false;
                $error = "Sản phẩm không đủ số lượng trong kho!";
                break;
            }
        }
        
        if ($valid_order) {
            $pdo->beginTransaction();
            try {
                // Tạo đơn hàng
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, customer_address, total_amount) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $customer_name, $customer_phone, $customer_address, $total_amount]);
                $order_id = $pdo->lastInsertId();
                
                // Thêm chi tiết đơn hàng và cập nhật tồn kho
                foreach ($_SESSION['cart'] as $product_id => $quantity) {
                    $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    
                    // Thêm vào order_items
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);
                    
                    // Cập nhật tồn kho
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$quantity, $product_id]);
                }

// Xử lý mã giảm giá 
$coupon_code = $_POST['coupon_code'] ?? '';
$discount = 0;

if (!empty($coupon_code)) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND (expires_at IS NULL OR expires_at >= datetime('now'))");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch();

    if ($coupon) {
        $discount = $total_amount * ($coupon['discount_percent'] / 100);
        $total_amount -= $discount;
    } else {
        $error = "Mã giảm giá không hợp lệ hoặc đã hết hạn!";
    }
}
                
                $pdo->commit();
                $_SESSION['cart'] = [];
                $message = "Đặt hàng thành công! Mã đơn hàng: #" . $order_id;
            } catch (Exception $e) {
                $pdo->rollback();
                $error = "Lỗi khi đặt hàng: " . $e->getMessage();
            }
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin!";
    }
}


// Lấy danh sách sản phẩm
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY created_at DESC");
$stmt->execute();
$products = $stmt->fetchAll();

// Lấy danh sách users (Admin)
$users = [];
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
}

// Lấy danh sách đơn hàng (Admin)
$orders = [];
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
    $stmt->execute();
    $orders = $stmt->fetchAll();
}

// Xử lý xóa user (Admin)
if (isset($_POST['delete_user']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $user_id = $_POST['user_id'];
    if ($user_id != $_SESSION['user_id']) { // Không cho phép xóa chính mình
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "Xóa người dùng thành công!";
    } else {
        $error = "Không thể xóa chính mình!";
    }
}

// Hàm định dạng tiền tệ
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.') . ' VNĐ';
}

// Hàm tính tổng giỏ hàng
function calculateTotal($cart, $pdo) {
    $total = 0;
    foreach ($cart as $product_id => $quantity) {
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        if ($product) {
            $total += $product['price'] * $quantity;
        }
    }
    return $total;
}

// Lấy thông tin sản phẩm cho giỏ hàng
function getCartItems($cart, $pdo) {
    $items = [];
    foreach ($cart as $product_id => $quantity) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        if ($product) {
            $product['quantity'] = $quantity;
            $items[] = $product;
        }
    }
    return $items;
}

$current_page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Online - Hệ thống bán hàng</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.1);
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-info {
            background: #e74c3c;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .message {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .error {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .section {
            background: white;
            padding: 2rem;
            margin: 2rem 0;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .product-price {
            font-size: 1.2rem;
            color: #e74c3c;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .product-description {
            color: #666;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .product-stock {
            color: #27ae60;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .add-to-cart-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .quantity-input {
            width: 60px;
            padding: 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(86, 171, 47, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 65, 108, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
        }

        .cart-total {
            text-align: right;
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 3px solid #667eea;
        }

        .auth-forms {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .admin-actions {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .auth-forms {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">🛍️ Shop Online</div>
                <div class="nav-menu">
                    <a href="?page=home" class="<?php echo $current_page == 'home' ? 'active' : ''; ?>">Trang chủ</a>
                    <a href="?page=cart" class="<?php echo $current_page == 'cart' ? 'active' : ''; ?>">Giỏ hàng</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <a href="?page=admin" class="<?php echo $current_page == 'admin' ? 'active' : ''; ?>">Quản trị</a>
                        <?php endif; ?>
                        <a href="?page=orders" class="<?php echo $current_page == 'orders' ? 'active' : ''; ?>">Đơn hàng</a>
                    <?php endif; ?>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="?page=auth" class="<?php echo $current_page == 'auth' ? 'active' : ''; ?>">Đăng nhập</a>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span>Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="logout" class="btn btn-danger">Đăng xuất</button>
                        </form>
                    <?php endif; ?>
                    <div class="cart-info">
                        Giỏ hàng: <?php echo array_sum($_SESSION['cart']); ?> sản phẩm
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($current_page == 'home'): ?>
            <div class="section">
                <h2 class="section-title">Sản phẩm nổi bật</h2>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <img src="<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-price"><?php echo formatCurrency($product['price']); ?></div>
                                <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                <div class="product-stock">Còn lại: <?php echo $product['stock']; ?> sản phẩm</div>
                                <?php if ($product['stock'] > 0): ?>
                                    <form method="post" class="add-to-cart-form">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" class="quantity-input">
                                        <button type="submit" name="add_to_cart" class="btn btn-primary">Thêm vào giỏ</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>Hết hàng</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($current_page == 'cart'): ?>
            <div class="section">
                <h2 class="section-title">Giỏ hàng của bạn</h2>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <form method="post">
                        <?php foreach (getCartItems($_SESSION['cart'], $pdo) as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item-info">
                                    <img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image">
                                    <div>
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p>Đơn giá: <?php echo formatCurrency($item['price']); ?></p>
                                        <p>Số lượng: 
                                            <input type="number" name="quantities[<?php echo $item['id']; ?>]" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="0" max="<?php echo $item['stock']; ?>" 
                                                   class="quantity-input">
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <strong><?php echo formatCurrency($item['price'] * $item['quantity']); ?></strong>
                                    <form method="post" style="display: inline-block; margin-left: 1rem;">
                                        <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="remove_from_cart" class="btn btn-danger">Xóa</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-total">
                            <button type="submit" name="update_cart" class="btn btn-warning">Cập nhật giỏ hàng</button>
                            <div style="margin-top: 1rem;">
                                Tổng cộng: <?php echo formatCurrency(calculateTotal($_SESSION['cart'], $pdo)); ?>
                            </div>
                        </div>
                    </form>
                    
                    <div class="section">
                        <h3 class="section-title">Thông tin đặt hàng</h3>
                        <form method="post">
                            <div class="form-group">
                                <label for="customer_name">Họ và tên:</label>
                                <input type="text" id="customer_name" name="customer_name" required>
                            </div>
                            <div class="form-group">
                                <label for="customer_phone">Số điện thoại:</label>
                                <input type="tel" id="customer_phone" name="customer_phone" required>
                            </div>
                            <div class="form-group">
                                <label for="customer_address">Địa chỉ giao hàng:</label>
                                <textarea id="customer_address" name="customer_address" required></textarea>
                            </div>
                            <button type="submit" name="checkout" class="btn btn-success">Đặt hàng ngay</button>
                            <!-- Mã giảm giá --> 
                            <div class="form-group">      
                                 <label for="coupon_code">Mã giảm giá:</label>
                                <input type="text" id="coupon_code" name="coupon_code">
                            </div>

                            <!-- Nút mở chat -->
<div id="chat-toggle" onclick="toggleChat()" style="position: fixed; bottom: 20px; right: 20px; background: #667eea; color: white; padding: 10px 15px; border-radius: 50px; cursor: pointer; z-index: 1001;">
    💬 Chat
</div>

<!-- Khung chat -->
<div id="chat-box" style="position: fixed; bottom: 70px; right: 20px; width: 300px; height: 400px; background: white; border: 1px solid #ccc; display: none; z-index: 1000;">
    <div style="padding: 10px; background: #667eea; color: white; display: flex; justify-content: space-between;">
        <span>Hỗ trợ</span>
        <span onclick="toggleChat()" style="cursor: pointer;">✖</span>
    </div>
    <div id="chat-messages" style="height: 280px; overflow-y: auto; padding: 10px;">
    <?php
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$_SESSION['user_id']]);
        $chatMessages = $stmt->fetchAll();

        foreach ($chatMessages as $msg) {
            $align = $msg['sender'] === 'user' ? 'left' : 'right';
            $color = $msg['sender'] === 'user' ? '#f1f0f0' : '#d1e7dd';
            echo "<div style='text-align: $align; margin: 5px 0;'>
                    <div style='display: inline-block; background: $color; padding: 10px; border-radius: 10px; max-width: 80%;'>
                        " . htmlspecialchars($msg['message']) . "
                    </div>
                  </div>";
        }
    }
    ?>
</div>

    <form method="post" style="display: flex;">
        <input type="text" name="chat_message" placeholder="Nhắn..." style="flex: 1; padding: 10px;">
        <button name="send_message" class="btn btn-primary">Gửi</button>
    </form>
</div>

                            <form method="post">
                            <input type="hidden" name="return_order_id" value="<?php echo $order['id']; ?>">
                            <textarea name="return_reason" required placeholder="Lý do trả hàng"></textarea>
                            <button name="request_return" class="btn btn-warning">Yêu cầu trả hàng</button>
                            </form>


                        </form>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666;">Giỏ hàng trống. <a href="?page=home">Mua sắm ngay</a></p>
                <?php endif; ?>
            </div>

        <?php elseif ($current_page == 'auth' && !isset($_SESSION['user_id'])): ?>
            <div class="section">
                <h1 class="section-title">Đăng nhập / Đăng ký</h1>
                <div class="auth-forms">
                    <div>
                        <h2>Đăng nhập</h2>
                        <form method="post">
                            <div class="form-group">
                                <label for="login_username">Tên đăng nhập:</label>
                                <input type="text" id="login_username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="login_password">Mật khẩu:</label>
                                <input type="password" id="login_password" name="password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary">Đăng nhập</button>
                        </form>
                    </div>
                    <div>
                        <h2>Đăng ký</h2>
                        <form method="post">
                            <div class="form-group">
                                <label for="register_username">Tên đăng nhập:</label>
                                <input type="text" id="register_username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="register_email">Email:</label>
                                <input type="email" id="register_email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="register_password">Mật khẩu:</label>
                                <input type="password" id="register_password" name="password" required>
                            </div>
                            <button type="submit" name="register" class="btn btn-success">Đăng ký</button>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($current_page == 'admin' && isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <div class="section">
                <h2 class="section-title">Quản trị hệ thống</h2>
                
                <div style="margin-bottom: 2rem;">
                    <button onclick="openModal('addProductModal')" class="btn btn-primary">Thêm sản phẩm mới</button>
                </div>

                <h3>Quản lý sản phẩm</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên sản phẩm</th>
                            <th>Giá</th>
                            <th>Tồn kho</th>
                            <th>Hình ảnh</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo formatCurrency($product['price']); ?></td>
                                <td><?php echo $product['stock']; ?></td>
                                <td><img src="<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;"></td>
                                <td class="admin-actions">
                                    <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="btn btn-warning">Sửa</button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="delete_product" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Quản lý người dùng</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Email</th>
                            <th>Vai trò</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['role']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này?')">Xóa</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Quản lý đơn hàng</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mã đơn hàng</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                <td><?php echo $order['status']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($current_page == 'orders' && isset($_SESSION['user_id'])): ?>
            <div class="section">
                <h2 class="section-title">Đơn hàng của tôi</h2>
                <?php
                $user_id = $_SESSION['user_id'];
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$user_id]);
                $user_orders = $stmt->fetchAll();
                ?>
                <?php if (!empty($user_orders)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mã đơn hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày đặt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                    <td><?php echo $order['status']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #666;">Bạn chưa có đơn hàng nào.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal thêm sản phẩm -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addProductModal')">&times;</span>
            <h2>Thêm sản phẩm mới</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="product_name">Tên sản phẩm:</label>
                    <input type="text" id="product_name" name="product_name" required>
                </div>
                <div class="form-group">
                    <label for="product_description">Mô tả:</label>
                    <textarea id="product_description" name="product_description"></textarea>
                </div>
                <div class="form-group">
                    <label for="product_price">Giá:</label>
                    <input type="number" id="product_price" name="product_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="product_stock">Tồn kho:</label>
                    <input type="number" id="product_stock" name="product_stock" required>
                </div>
                <div class="form-group">
                    <label for="product_image">URL hình ảnh:</label>
                    <input type="url" id="product_image" name="product_image">
                </div>
                <div class="form-group">
                    <label for="product_image_file">Hoặc tải lên hình ảnh:</label>
                    <input type="file" id="product_image_file" name="product_image_file" accept="image/*">
                </div>
                <button type="submit" name="add_product" class="btn btn-primary">Thêm sản phẩm</button>
            </form>
        </div>
    </div>

    <!-- Modal sửa sản phẩm -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editProductModal')">&times;</span>
            <h2>Sửa sản phẩm</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" id="edit_product_id" name="product_id">
                <input type="hidden" id="edit_current_image" name="current_image">
                <div class="form-group">
                    <label for="edit_product_name">Tên sản phẩm:</label>
                    <input type="text" id="edit_product_name" name="product_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_product_description">Mô tả:</label>
                    <textarea id="edit_product_description" name="product_description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_product_price">Giá:</label>
                    <input type="number" id="edit_product_price" name="product_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="edit_product_stock">Tồn kho:</label>
                    <input type="number" id="edit_product_stock" name="product_stock" required>
                </div>
                <div class="form-group">
                    <label for="edit_product_image_file">Thay đổi hình ảnh:</label>
                    <input type="file" id="edit_product_image_file" name="product_image_file" accept="image/*">
                </div>
                <button type="submit" name="update_product" class="btn btn-success">Cập nhật sản phẩm</button>
            </form>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; 2024 Shop Online. Tất cả quyền được bảo lưu.</p>
            <p>Liên hệ: 0123.456.789 | Email: info@shop.vn</p>
        </div>
    </footer>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_product_name').value = product.name;
            document.getElementById('edit_product_description').value = product.description;
            document.getElementById('edit_product_price').value = product.price;
            document.getElementById('edit_product_stock').value = product.stock;
            document.getElementById('edit_current_image').value = product.image;
            openModal('editProductModal');
        }

        // Đóng modal khi click bên ngoài
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
    <script>        // chat box ẩn hiển
    function toggleChat() {
        const chatBox = document.getElementById("chat-box");
        const toggleBtn = document.getElementById("chat-toggle");
        if (chatBox.style.display === "none" || chatBox.style.display === "") {
            chatBox.style.display = "block";
        } else {
            chatBox.style.display = "none";
        }
    }
    </script>
</body>
</html>