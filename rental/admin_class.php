<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_admin();
ini_set('display_errors', 1);

class Action {
    private $db;

    public function __construct() {
        ob_start();
        include 'db_connect.php';
        $this->db = $conn;
    }

    function __destruct() {
        $this->db->close();
        ob_end_flush();
    }

    // Secure login with prepared statements and password_hash
    function login() {
        if(empty($_POST['username']) || empty($_POST['password'])) {
            return 3; // Missing credentials
        }

        $username = trim($_POST['username']);
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if(password_verify($_POST['password'], $user['password'])) {
                foreach ($user as $key => $value) {
                    if($key != 'password' && !is_numeric($key)) {
                        $_SESSION[$key] = $value;
                    }
                }
                
                if($_SESSION['user_type'] != 1) {
                    require_once '../config/auth.php';
                    logout();
                    return 2; // Not admin
                }
                return 1; // Success
            }
        }
        return 3; // Invalid credentials
    }

    function login2() {
        if(empty($_POST['email']) || empty($_POST['password'])) {
            return 3;
        }

        $email = trim($_POST['email']);
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if(password_verify($_POST['password'], $user['password'])) {
                foreach ($user as $key => $value) {
                    if($key != 'password' && !is_numeric($key)) {
                        $_SESSION[$key] = $value;
                    }
                }

                if($_SESSION['user_id'] > 0) {
                    $bio_stmt = $this->db->prepare("SELECT * FROM alumnus_bio WHERE id = ?");
                    $bio_stmt->bind_param("i", $_SESSION['user_id']);
                    $bio_stmt->execute();
                    $bio = $bio_stmt->get_result()->fetch_assoc();
                    
                    if($bio) {
                        foreach ($bio as $key => $value) {
                            if($key != 'password' && !is_numeric($key)) {
                                $_SESSION['bio'][$key] = $value;
                            }
                        }
                    }
                }

                if($_SESSION['bio']['status'] != 1) {
                    require_once '../config/auth.php';
                    logout();
                    return 2;
                }
                return 1;
            }
        }
        return 3;
    }

    function logout() {
        $_SESSION = array();
    }

    function logout2() {
        require_once '../config/auth.php';
        logout();
    }
    }

    // Secure user management
    function save_user() {
        if(empty($_POST['name']) || empty($_POST['username']) || empty($_POST['type'])) {
            return "Required fields are missing";
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $type = (int)$_POST['type'];
        $password = !empty($_POST['password']) ? $_POST['password'] : null;

        // Check for duplicate username
        $check_stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $id);
        $check_stmt->execute();
        
        if($check_stmt->get_result()->num_rows > 0) {
            return 2;
        }

        if($id > 0) {
            if($password) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->db->prepare("UPDATE users SET name = ?, username = ?, password = ?, type = ? WHERE id = ?");
                $stmt->bind_param("sssii", $name, $username, $hashed_password, $type, $id);
            } else {
                $stmt = $this->db->prepare("UPDATE users SET name = ?, username = ?, type = ? WHERE id = ?");
                $stmt->bind_param("ssii", $name, $username, $type, $id);
            }
        } else {
            if(empty($password)) {
                return "Password is required for new users";
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (name, username, password, type) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name, $username, $hashed_password, $type);
        }

        return $stmt->execute() ? 1 : $this->db->error;
    }

    function delete_user() {
        if(empty($_POST['id']) || !is_numeric($_POST['id'])) {
            return "Invalid user ID";
        }

        $id = (int)$_POST['id'];
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute() ? 1 : $this->db->error;
    }

    function signup() {
        $required = ['firstname', 'lastname', 'email', 'password'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                return "Missing required field: $field";
            }
        }

        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email address";
        }

        $this->db->autocommit(FALSE);
        try {
            // Check if email exists
            $check_stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            
            if($check_stmt->get_result()->num_rows > 0) {
                return 2;
            }

            // Insert user
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $name = trim($_POST['firstname']).' '.trim($_POST['lastname']);
            
            $user_stmt = $this->db->prepare("INSERT INTO users (name, username, password) VALUES (?, ?, ?)");
            $user_stmt->bind_param("sss", $name, $email, $hashed_password);
            
            if(!$user_stmt->execute()) {
                throw new Exception($this->db->error);
            }
            
            $uid = $this->db->insert_id;
            $data = [];
            
            foreach($_POST as $k => $v) {
                if($k != 'password' && !is_numeric($k)) {
                    $data[$k] = $v;
                }
            }

            if(!empty($_FILES['img']['tmp_name'])) {
                $fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
                if(move_uploaded_file($_FILES['img']['tmp_name'], 'assets/uploads/'.$fname)) {
                    $data['avatar'] = $fname;
                }
            }

            $columns = implode(", ", array_keys($data));
            $values = "'".implode("', '", array_values($data))."'";
            
            $bio_stmt = $this->db->prepare("INSERT INTO alumnus_bio ($columns) VALUES ($values)");
            if(!$bio_stmt->execute()) {
                throw new Exception($this->db->error);
            }
            
            $aid = $this->db->insert_id;
            $update_stmt = $this->db->prepare("UPDATE users SET alumnus_id = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $aid, $uid);
            
            if(!$update_stmt->execute()) {
                throw new Exception($this->db->error);
            }
            
            $this->db->commit();
            return $this->login2();
        } catch (Exception $e) {
            $this->db->rollback();
            return $e->getMessage();
        }
    }

    function update_account() {
        $required = ['firstname', 'lastname', 'email'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                return "Missing required field: $field";
            }
        }

        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email address";
        }

        $this->db->autocommit(FALSE);
        try {
            // Check if email exists for other users
            $check_stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
            $check_stmt->execute();
            
            if($check_stmt->get_result()->num_rows > 0) {
                return 2;
            }

            // Update user
            $name = trim($_POST['firstname']).' '.trim($_POST['lastname']);
            $data = "name = ?, username = ?";
            $params = [$name, $email];
            $types = "ss";
            
            if(!empty($_POST['password'])) {
                $data .= ", password = ?";
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $params[] = $hashed_password;
                $types .= "s";
            }
            
            $params[] = $_SESSION['user_id'];
            $types .= "i";
            
            $user_stmt = $this->db->prepare("UPDATE users SET $data WHERE id = ?");
            $user_stmt->bind_param($types, ...$params);
            
            if(!$user_stmt->execute()) {
                throw new Exception($this->db->error);
            }

            // Update bio
            $data = [];
            $params = [];
            $types = "";
            
            foreach($_POST as $k => $v) {
                if($k != 'password' && !is_numeric($k)) {
                    $data[] = "$k = ?";
                    $params[] = $v;
                    $types .= "s";
                }
            }

            if(!empty($_FILES['img']['tmp_name'])) {
                $fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
                if(move_uploaded_file($_FILES['img']['tmp_name'], 'assets/uploads/'.$fname)) {
                    $data[] = "avatar = ?";
                    $params[] = $fname;
                    $types .= "s";
                }
            }
            
            $params[] = $_SESSION['user_id'];
            $types .= "i";
            
            $set_clause = implode(", ", $data);
            $bio_stmt = $this->db->prepare("UPDATE alumnus_bio SET $set_clause WHERE id = ?");
            $bio_stmt->bind_param($types, ...$params);
            
            if(!$bio_stmt->execute()) {
                throw new Exception($this->db->error);
            }
            
            $this->db->commit();
            session_destroy();
            return $this->login2();
        } catch (Exception $e) {
            $this->db->rollback();
            return $e->getMessage();
        }
    }

    function save_settings() {
        $required = ['name', 'email', 'contact'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                return "Missing required field: $field";
            }
        }

        $name = htmlspecialchars(trim($_POST['name']));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email address";
        }

        $contact = trim($_POST['contact']);
        $about = htmlspecialchars(trim($_POST['about'] ?? ''));

        $params = [$name, $email, $contact, $about];
        $types = "ssss";
        $data = "name = ?, email = ?, contact = ?, about_content = ?";

        if(!empty($_FILES['img']['tmp_name'])) {
            $fname = strtotime(date('y-m-d H:i')).'_'.$_FILES['img']['name'];
            if(move_uploaded_file($_FILES['img']['tmp_name'], 'assets/uploads/'.$fname)) {
                $data .= ", cover_img = ?";
                $params[] = $fname;
                $types .= "s";
            }
        }

        $check = $this->db->query("SELECT * FROM system_settings");
        if($check->num_rows > 0) {
            $stmt = $this->db->prepare("UPDATE system_settings SET $data");
        } else {
            $stmt = $this->db->prepare("INSERT INTO system_settings SET $data");
        }

        $stmt->bind_param($types, ...$params);
        if(!$stmt->execute()) {
            return $this->db->error;
        }

        // Refresh settings in session
        $query = $this->db->query("SELECT * FROM system_settings LIMIT 1");
        if($query->num_rows > 0) {
            $settings = $query->fetch_assoc();
            foreach ($settings as $key => $value) {
                if(!is_numeric($key)) {
                    $_SESSION['system'][$key] = $value;
                }
            }
        }

        return 1;
    }

    function save_category() {
        if(empty($_POST['name'])) {
            return "Category name is required";
        }

        $name = trim($_POST['name']);
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if($id > 0) {
            $stmt = $this->db->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $name, $id);
        } else {
            $stmt = $this->db->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
        }

        return $stmt->execute() ? 1 : $this->db->error;
    }

    function delete_category() {
        if(empty($_POST['id']) || !is_numeric($_POST['id'])) {
            return "Invalid category ID";
        }

        $id = (int)$_POST['id'];
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute() ? 1 : $this->db->error;
    }

    function save_house() {
        $required = ['house_no', 'description', 'category_id', 'price'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                return "Missing required field: $field";
            }
        }

        $house_no = trim($_POST['house_no']);
        $description = trim($_POST['description']);
        $category_id = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        // Check for duplicate house number
        $check_stmt = $this->db->prepare("SELECT id FROM houses WHERE house_no = ? AND id != ?");
        $check_stmt->bind_param("si", $house_no, $id);
        $check_stmt->execute();
        
        if($check_stmt->get_result()->num_rows > 0) {
            return 2;
        }

        if($id > 0) {
            $stmt = $this->db->prepare("UPDATE houses SET house_no = ?, description = ?, category_id = ?, price = ? WHERE id = ?");
            $stmt->bind_param("ssidi", $house_no, $description, $category_id, $price, $id);
        } else {
            $stmt = $this->db->prepare("INSERT INTO houses (house_no, description, category_id, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssid", $house_no, $description, $category_id, $price);
        }

        return $stmt->execute() ? 1 : $this->db->error;
    }

    function delete_house() {
        if(empty($_POST['id']) || !is_numeric($_POST['id'])) {
            return "Invalid house ID";
        }

        $id = (int)$_POST['id'];
        $stmt = $this->db->prepare("DELETE FROM houses WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute() ? 1 : $this->db->error;
    }

    function save_tenant() {
        $required = ['firstname', 'lastname', 'email', 'contact', 'house_id'];
        foreach($required as $field) {
            if(empty($_POST[$field])) {
                return "Missing required field: $field";
            }
        }

        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email address";
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = [
            'firstname' => trim($_POST['firstname']),
            'lastname' => trim($_POST['lastname']),
            'middlename' => trim($_POST['middlename'] ?? ''),
            'email' => $email,
            'contact' => trim($_POST['contact']),
            'house_id' => (int)$_POST['house_id'],
            'date_in' => !empty($_POST['date_in']) ? $_POST['date_in'] : date('Y-m-d')
        ];

        if($id > 0) {
            $query = "UPDATE tenants SET ";
            $params = [];
            $types = '';
            
            foreach($data as $field => $value) {
                $query .= "$field = ?, ";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
            
            $query = rtrim($query, ', ') . " WHERE id = ?";
            $params[] = $id;
            $types .= 'i';
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($types, ...$params);
        } else {
            $fields = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $stmt = $this->db->prepare("INSERT INTO tenants ($fields) VALUES ($placeholders)");
            $types = str_repeat('s', count($data));
            $values = array_values($data);
            $stmt->bind_param($types, ...$values);
        }

        return $stmt->execute() ? 1 : $this->db->error;
    }

    function delete_tenant() {
        if(empty($_POST['id']) || !is_numeric($_POST['id'])) {
            return "Invalid tenant ID";
        }

        $id = (int)$_POST['id'];
        $stmt = $this->db->prepare("UPDATE tenants SET status = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute() ? 1 : $this->db->error;
    }

    function get_tdetails() {
        if(empty($_POST['id']) || !is_numeric($_POST['id'])) {
            return json_encode(['error' => 'Invalid tenant ID']);
        }

        $id = (int)$_POST['id'];
        $pid = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;
        
        $stmt = $this->db->prepare("SELECT t.*, CONCAT(t.lastname, ', ', t.firstname, ' ', t.middlename) as name, 
                                  h.house_no, h.price 
                                  FROM tenants t 
                                  INNER JOIN houses h ON h.id = t.house_id 
                                  WHERE t.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $tenant = $stmt->get_result()->fetch_assoc();

        if(!$tenant) {
            return json_encode(['error' => 'Tenant not found']);
        }

        $months = abs(strtotime(date('Y-m-d')) - strtotime($tenant['date_in']));
        $months = floor($months / (30*60*60*24));
        $payable = abs($tenant['price'] * $months);
        
        $payment_stmt = $this->db->prepare("SELECT SUM(amount) as paid FROM payments WHERE id != ? AND tenant_id = ?");
        $payment_stmt->bind_param("ii", $pid, $id);
        $payment_stmt->execute();
        $paid = $payment_stmt->get_result()->fetch_assoc()['paid'] ?? 0;
        
        $last_payment_stmt = $this->db->prepare("SELECT date_created FROM payments WHERE id != ? AND tenant_id = ? ORDER BY date_created DESC LIMIT 1");
        $last_payment_stmt->bind_param("ii", $pid, $id);
        $last_payment_stmt->execute();
        $last_payment = $last_payment_stmt->get_result()->fetch_assoc();
        
        return json_encode([
            'months' => $months,
            'payable' => number_format($payable, 2),
            'paid' => number_format($paid, 2),
            'last_payment' => $last_payment ? date("M d, Y", strtotime($last_payment['date_created'])) : 'N/A',
            'outstanding' => number_format($payable - $paid, 2),
            'price' => number_format($tenant['price'], 2),
            'name' => ucwords($tenant['name']),
            'rent_started' => date('M d, Y', strtotime($tenant['date_in']))
        ]);
    }

    function save_payment() {
    $required = ['tenant_id', 'amount', 'invoice', 'date_created'];
    foreach($required as $field) {
        if(empty($_POST[$field])) {
            return "Missing required field: $field";
        }
    }

    try {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $data = [
            'tenant_id' => (int)$_POST['tenant_id'],
            'amount' => (float)$_POST['amount'],
            'invoice' => trim($_POST['invoice']),
            'date_created' => $_POST['date_created'],
            
        ];

        if($id > 0) {
            $stmt = $this->db->prepare("UPDATE payments SET 
                tenant_id = ?, amount = ?, invoice = ?, date_created = ?
                WHERE id = ?");
            $stmt->bind_param("idssi", 
                $data['tenant_id'], 
                $data['amount'], 
                $data['invoice'],
                $data['date_created'], 
                $id);
        } else {
            $stmt = $this->db->prepare("INSERT INTO payments 
                (tenant_id, amount, invoice, date_created) 
                VALUES (?, ?, ?, ?)");
            $stmt->bind_param("idss", 
                $data['tenant_id'], 
                $data['amount'], 
                $data['invoice'], 
                $data['date_created']);
        }

        if(!$stmt->execute()) {
            throw new Exception($this->db->error);
        }
        
        return 1;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}


    function delete_payment() {
        if(empty($_POST['id']) || !is_numeric($_POST['id'])) {
            return "Invalid payment ID";
        }

        $id = (int)$_POST['id'];
        $stmt = $this->db->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute() ? 1 : $this->db->error;
    }
}