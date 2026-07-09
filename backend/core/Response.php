<?php
// backend/core/Response.php

class Response {
    public static function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
    
    public static function success($data = null, $message = 'Success') {
        return self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    public static function error($message = 'Error', $code = 400) {
        return self::json([
            'status' => 'error',
            'message' => $message
        ], $code);
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        return self::json([
            'status' => 'error',
            'message' => $message
        ], 401);
    }
    
    public static function notFound($message = 'Not Found') {
        return self::json([
            'status' => 'error',
            'message' => $message
        ], 404);
    }
}
?>