<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Konfigurasi
$data_file = '/var/www/html/data.json';
$log_file = '/var/www/html/api_log.txt';
$max_file_size = 50 * 1024 * 1024; // 50MB

// Fungsi untuk log
function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Fungsi untuk validasi MSISDN
function validate_msisdn($msisdn) {
    // Hapus spasi dan karakter non-digit
    $msisdn = preg_replace('/[^0-9]/', '', $msisdn);
    
    // Validasi panjang (min 10, max 15 digit)
    if (strlen($msisdn) < 10 || strlen($msisdn) > 15) {
        return false;
    }
    
    // Validasi prefix (contoh: 08, 628, dll)
    if (!preg_match('/^(08|628|\+62)/', $msisdn)) {
        return false;
    }
    
    return $msisdn;
}

// Fungsi untuk load data JSON
function load_data() {
    global $data_file;
    
    if (!file_exists($data_file)) {
        file_put_contents($data_file, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    
    $content = file_get_contents($data_file);
    if (empty($content)) {
        return [];
    }
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// Fungsi untuk save data JSON
function save_data($data) {
    global $data_file;
    
    // Backup data lama sebelum overwrite
    if (file_exists($data_file)) {
        $backup_file = '/var/www/html/data_backup_' . date('Ymd_His') . '.json';
        copy($data_file, $backup_file);
    }
    
    // Hapus backup lama (simpan hanya 5 backup terakhir)
    $backup_files = glob('/var/www/html/data_backup_*.json');
    if (count($backup_files) > 5) {
        usort($backup_files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        for ($i = 0; $i < count($backup_files) - 5; $i++) {
            unlink($backup_files[$i]);
        }
    }
    
    // Simpan data baru
    $result = file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
    
    return $result !== false;
}

// Fungsi untuk menambahkan data
function add_data($new_data) {
    $current_data = load_data();
    
    // Buat array untuk tracking MSISDN yang sudah ada
    $existing_msisdns = [];
    foreach ($current_data as $item) {
        $existing_msisdns[$item['msisdn']] = true;
    }
    
    // Filter dan validasi data baru
    $valid_new_data = [];
    $duplicates = 0;
    $invalid = 0;
    
    foreach ($new_data as $item) {
        // Validasi format
        if (!isset($item['msisdn']) || !isset($item['nama_paket'])) {
            $invalid++;
            continue;
        }
        
        // Validasi MSISDN
        $valid_msisdn = validate_msisdn($item['msisdn']);
        if (!$valid_msisdn) {
            $invalid++;
            continue;
        }
        
        // Cek duplikat
        if (isset($existing_msisdns[$valid_msisdn])) {
            $duplicates++;
            continue;
        }
        
        // Tambahkan ke array valid
        $valid_item = [
            'msisdn' => $valid_msisdn,
            'nama_paket' => trim($item['nama_paket'])
        ];
        
        $valid_new_data[] = $valid_item;
        $existing_msisdns[$valid_msisdn] = true;
    }
    
    // Gabungkan data
    $updated_data = array_merge($current_data, $valid_new_data);
    
    // Save data
    $save_result = save_data($updated_data);
    
    return [
        'success' => $save_result,
        'added' => count($valid_new_data),
        'duplicates' => $duplicates,
        'invalid' => $invalid,
        'total_now' => count($updated_data)
    ];
}

// Handle preflight CORS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle request
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['REQUEST_URI'] ?? '/';
    
    log_message("Request: $method $path");
    
    // GET request untuk melihat data
    if ($method === 'GET') {
        $data = load_data();
        
        // Filter berdasarkan query parameters
        if (isset($_GET['msisdn'])) {
            $search_msisdn = validate_msisdn($_GET['msisdn']);
            $data = array_filter($data, function($item) use ($search_msisdn) {
                return $item['msisdn'] === $search_msisdn;
            });
            $data = array_values($data); // Reset keys
        }
        
        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = ($page - 1) * $limit;
        
        $total = count($data);
        $paged_data = array_slice($data, $offset, $limit);
        
        echo json_encode([
            'success' => true,
            'data' => $paged_data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        
        exit();
    }
    
    // POST request untuk menambahkan data
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        
        // Log raw input untuk debugging
        log_message("Raw input: " . substr($input, 0, 1000));
        
        // Parse JSON input
        $new_data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
        
        // Handle single object
        if (isset($new_data['msisdn'])) {
            $new_data = [$new_data];
        }
        
        // Validasi array
        if (!is_array($new_data)) {
            throw new Exception('Data must be an array or object');
        }
        
        // Validasi jumlah data (limit 1000 per request)
        if (count($new_data) > 1000) {
            throw new Exception('Maximum 1000 records per request');
        }
        
        // Tambahkan data
        $result = add_data($new_data);
        
        // Response
        $response = [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Data berhasil ditambahkan' : 'Gagal menyimpan data',
            'details' => [
                'added' => $result['added'],
                'duplicates_skipped' => $result['duplicates'],
                'invalid_skipped' => $result['invalid'],
                'total_records' => $result['total_now']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        log_message("Response: " . json_encode($response));
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
        exit();
    }
    
    // PUT request untuk update data
    if ($method === 'PUT') {
        $input = file_get_contents('php://input');
        $update_data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format');
        }
        
        $current_data = load_data();
        
        // Cari dan update data berdasarkan MSISDN
        $updated = 0;
        foreach ($current_data as &$item) {
            if ($item['msisdn'] === $update_data['msisdn']) {
                $item['nama_paket'] = $update_data['nama_paket'];
                $updated++;
                break;
            }
        }
        
        if ($updated > 0) {
            save_data($current_data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Data berhasil diupdate',
                'updated' => $updated,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Data tidak ditemukan',
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
        }
        
        exit();
    }
    
    // DELETE request untuk menghapus data
    if ($method === 'DELETE') {
        $input = file_get_contents('php://input');
        $delete_data = json_decode($input, true);
        
        if (!isset($delete_data['msisdn'])) {
            throw new Exception('MSISDN is required for deletion');
        }
        
        $current_data = load_data();
        $initial_count = count($current_data);
        
        // Filter data (hapus yang MSISDN-nya match)
        $current_data = array_filter($current_data, function($item) use ($delete_data) {
            return $item['msisdn'] !== $delete_data['msisdn'];
        });
        
        $current_data = array_values($current_data); // Reset keys
        $final_count = count($current_data);
        $deleted = $initial_count - $final_count;
        
        save_data($current_data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil dihapus',
            'deleted' => $deleted,
            'total_now' => $final_count,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        
        exit();
    }
    
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
