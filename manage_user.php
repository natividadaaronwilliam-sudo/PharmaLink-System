<?php
header('Content-Type: application/json');

// Hardcoded staff at customers
$staff = [
    ['user_id' => 1, 'username' => 'admin', 'role_id' => 1, 'role' => 'Admin'],
    ['user_id' => 2, 'username' => 'cashier', 'role_id' => 2, 'role' => 'Cashier']
];

$customers = [
    ['user_id' => 3, 'username' => 'customer', 'role_id' => 3, 'role' => 'Customer']
];

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    switch ($action) {
        case 'get_staff':
            echo json_encode($staff);
            break;

        case 'get_customers':
            echo json_encode($customers);
            break;

        case 'add_staff':
            $username = trim($_POST['username'] ?? '');
            $role_id = intval($_POST['role_id'] ?? 0);
            $password = trim($_POST['password'] ?? '');

            if (empty($username) || empty($role_id) || empty($password)) {
                throw new Exception('All fields are required');
            }
            if (!in_array($role_id, [1, 2])) {
                throw new Exception('Invalid role');
            }

            // I-check kung umiiral na ang username
            foreach ($staff as $s) {
                if ($s['username'] === $username) {
                    throw new Exception('Username already exists');
                }
            }

            // Magdagdag ng bagong staff (simulated)
            $new_staff = [
                'user_id' => count($staff) + count($customers) + 1,
                'username' => $username,
                'role_id' => $role_id,
                'role' => $role_id == 1 ? 'Admin' : 'Cashier'
            ];
            $staff[] = $new_staff;
            echo json_encode(['success' => true, 'message' => 'Staff added successfully']);
            break;

        case 'add_customer':
            $username = trim($_POST['username'] ?? '');
            if (empty($username)) {
                throw new Exception('Username is required');
            }

            // I-check kung umiiral na ang username
            foreach ($customers as $c) {
                if ($c['username'] === $username) {
                    throw new Exception('Username already exists');
                }
            }

            // Magdagdag ng bagong customer (simulated)
            $new_customer = [
                'user_id' => count($staff) + count($customers) + 1,
                'username' => $username,
                'role_id' => 3,
                'role' => 'Customer'
            ];
            $customers[] = $new_customer;
            echo json_encode(['success' => true, 'message' => 'Customer added successfully']);
            break;

        case 'edit_staff':
            $user_id = intval($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $role_id = intval($_POST['role_id'] ?? 0);
            $password = trim($_POST['password'] ?? '');

            if ($user_id <= 0 || empty($username) || empty($role_id)) {
                throw new Exception('Username and role are required');
            }
            if (!in_array($role_id, [1, 2])) {
                throw new Exception('Invalid role');
            }

            // I-check kung umiiral na ang username
            foreach ($staff as $s) {
                if ($s['username'] === $username && $s['user_id'] != $user_id) {
                    throw new Exception('Username already exists');
                }
            }

            // I-update ang staff (simulated)
            foreach ($staff as &$s) {
                if ($s['user_id'] == $user_id) {
                    $s['username'] = $username;
                    $s['role_id'] = $role_id;
                    $s['role'] = $role_id == 1 ? 'Admin' : 'Cashier';
                    break;
                }
            }
            echo json_encode(['success' => true, 'message' => 'Staff updated successfully']);
            break;

        case 'edit_customer':
            $user_id = intval($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            if ($user_id <= 0 || empty($username)) {
                throw new Exception('Username is required');
            }

            // I-check kung umiiral na ang username
            foreach ($customers as $c) {
                if ($c['username'] === $username && $c['user_id'] != $user_id) {
                    throw new Exception('Username already exists');
                }
            }

            // I-update ang customer (simulated)
            foreach ($customers as &$c) {
                if ($c['user_id'] == $user_id) {
                    $c['username'] = $username;
                    break;
                }
            }
            echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
            break;

        case 'delete_staff':
        case 'delete_customer':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID');
            }

            if ($action == 'delete_staff') {
                $staff = array_filter($staff, fn($s) => $s['user_id'] != $user_id);
            } else {
                $customers = array_filter($customers, fn($c) => $c['user_id'] != $user_id);
            }
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>