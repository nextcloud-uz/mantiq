<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$base_url = 'http://192.168.1.88:5001';
$action = isset($_GET['action']) ? $_GET['action'] : 'start';

// 1. ASYNC TASKNI BOSHLASH
if ($action === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Fayl yuklanmadi yoki xatolik yuz berdi.']);
        exit();
    }

    $format = isset($_POST['format']) ? $_POST['format'] : 'md';
    $cfile = new CURLFile($_FILES['file']['tmp_name'], $_FILES['file']['type'], $_FILES['file']['name']);

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
        header("Content-Type: application/json");
        echo $response;
    }
    curl_close($ch);
}

// 3. TAYYOR NATIJANI OLISH
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
        header("Content-Type: application/json");
        echo $response;
    }
    curl_close($ch);
}
