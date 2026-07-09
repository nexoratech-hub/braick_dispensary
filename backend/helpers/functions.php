<?php
// backend/helpers/functions.php

// Check if function already exists before declaring
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        $time = strtotime($timestamp);
        $diff = time() - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
        return date('M d, Y', $time);
    }
}

if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return number_format($amount, 0, '.', ',');
    }
}

// Add more helper functions here if needed