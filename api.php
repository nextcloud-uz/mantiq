<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-User-Id");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$base_url = 'http://192.168.1.88:5001';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isset($_SERVER['HTTP_X_USER_ID']) ? $_SERVER['HTTP_X_USER_ID'] : (isset($_POST['user_id']) ? $_POST['user_id'] : (isset($_GET['user_id']) ? $_GET['user_id'] : ''));

if (empty($user_id)) {
    http_response_code(401);
    echo json_encode(['error' => 'User ID is required.']);
    exit();
}

// User directory structure
$user_dir = __DIR__ . "/uploads/" . preg_replace('/[^a-zA-Z0-9_-]/', '', $user_id);
if (!is_dir($user_dir)) {
    mkdir($user_dir, 0777, true);
}
$history_file = $user_dir . "/history.json";

function getHistory($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true) ?: [];
    }
    return [];
}

function saveHistory($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// 1. ASYNC TASKNI BOSHLASH
if ($action === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Fayl yuklanmadi yoki xatolik yuz berdi.']);
        exit();
    }

    $format = isset($_POST['format']) ? $_POST['format'] : 'md';
    $original_name = $_FILES['file']['name'];
    $cfile = new CURLFile($_FILES['file']['tmp_name'], $_FILES['file']['type'], $original_name);

    $post_fields = array(
        'files' => $cfile,
        'to_formats' => $format
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . '/v1/convert/file/async');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'Docling bilan ulanishda xato: ' . curl_error($ch)]);
    } else {
        http_response_code($http_code);
        $res_data = json_decode($response, true);
        
        if (isset($res_data['task_id'])) {
            $history = getHistory($history_file);
            $history[] = [
                'task_id' => $res_data['task_id'],
                'file_name' => $original_name,
                'format' => $format,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'saved_file' => null
            ];
            saveHistory($history_file, $history);
        }

        header("Content-Type: application/json");
        echo $response;
    }
    curl_close($ch);
}
// 2. TASK HOLATINI TEKSHIRISH
elseif ($action === 'poll') {
    $task_id = isset($_GET['task_id']) ? $_GET['task_id'] : '';
    if (empty($task_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID berilmadi.']);
        exit();
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . '/v1/status/poll/' . $task_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'Docling bilan ulanishda xato: ' . curl_error($ch)]);
    } else {
        http_response_code($http_code);
        
        $res_data = json_decode($response, true);
        if (isset($res_data['task_status'])) {
            $status = strtolower($res_data['task_status']);
            
            // update history
            $history = getHistory($history_file);
            foreach ($history as &$item) {
                if ($item['task_id'] === $task_id) {
                    if ($item['status'] !== 'completed') { 
                        if ($status === 'success' || $status === 'completed' || $status === 'finished' || $status === 'done') {
                            $item['status'] = 'success'; // Ready to fetch result
                        } else if ($status === 'failed' || $status === 'error') {
                            $item['status'] = 'failed';
                        } else {
                            $item['status'] = $status;
                        }
                    }
                    break;
                }
            }
            saveHistory($history_file, $history);
        }

        header("Content-Type: application/json");
        echo $response;
    }
    curl_close($ch);
}
// 3. TAYYOR NATIJANI OLISH VA SAQLASH
elseif ($action === 'result') {
    $task_id = isset($_GET['task_id']) ? $_GET['task_id'] : '';
    if (empty($task_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID berilmadi.']);
        exit();
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . '/v1/result/' . $task_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'Docling bilan ulanishda xato: ' . curl_error($ch)]);
    } else {
        http_response_code($http_code);
        $apiData = json_decode($response, true);
        
        // Find our task in history
        $history = getHistory($history_file);
        $format = 'txt';
        $saved_file_name = null;
        
        foreach ($history as &$item) {
            if ($item['task_id'] === $task_id) {
                $format = $item['format'];
                $ext = $format === 'text' ? 'txt' : $format;
                // Add uniq suffix to avoid issues
                $saved_file_name = $task_id . '_' . uniqid() . '.' . $ext;
                
                $finalContent = "";
                if (isset($apiData['document'])) {
                    $doc = $apiData['document'];
                    if ($format === 'md') $finalContent = isset($doc['md_content']) ? $doc['md_content'] : '';
                    else if ($format === 'json') $finalContent = isset($doc['json_content']) ? json_encode($doc['json_content'], JSON_PRETTY_PRINT) : '';
                    else if ($format === 'text') $finalContent = isset($doc['text_content']) ? $doc['text_content'] : '';
                    else if ($format === 'html') $finalContent = isset($doc['html_content']) ? $doc['html_content'] : '';
					
					file_put_contents($user_dir . '/' . $saved_file_name, $finalContent);
                	$item['status'] = 'completed';
                	$item['saved_file'] = $saved_file_name;
                } else {
					$item['status'] = 'failed';
				}
                break;
            }
        }
        saveHistory($history_file, $history);
        
        header("Content-Type: application/json");
        echo $response;
    }
    curl_close($ch);
}
// 4. TARIXNI OLISH
elseif ($action === 'history') {
    $history = getHistory($history_file);
    header("Content-Type: application/json");
    // Sort array by created_at desc
    usort($history, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    echo json_encode($history);
}
// 5. NATIJANI O'QISH
elseif ($action === 'get_result') {
    $task_id = isset($_GET['task_id']) ? $_GET['task_id'] : '';
    $history = getHistory($history_file);
    $found = false;
    foreach ($history as $item) {
        if ($item['task_id'] === $task_id) {
            $found = $item;
            break;
        }
    }
    
    if ($found && !empty($found['saved_file'])) {
        $path = $user_dir . '/' . $found['saved_file'];
        if (file_exists($path)) {
            $content = file_get_contents($path);
            header("Content-Type: text/plain; charset=utf-8");
            echo $content;
            exit();
        }
    }
    http_response_code(404);
    echo "Fayl topilmadi yoki saqlanmagan.";
}
// 6. FAYLNI O'CHIRISH
elseif ($action === 'delete') {
    $task_id = isset($_POST['task_id']) ? $_POST['task_id'] : '';
    $history = getHistory($history_file);
    $new_history = [];
    $deleted = false;
    foreach ($history as $item) {
        if ($item['task_id'] === $task_id) {
            $deleted = true;
            if (!empty($item['saved_file'])) {
                $path = $user_dir . '/' . $item['saved_file'];
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        } else {
            $new_history[] = $item;
        }
    }
    if ($deleted) {
        saveHistory($history_file, $new_history);
        header("Content-Type: application/json");
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        header("Content-Type: application/json");
        echo json_encode(['error' => 'Topilmadi']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Noto\'g\'ri action.']);
}
