<?php

if (!function_exists('formatMD')) {
    function formatMD(string $t): string
    {
        $t = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $t);
        $t = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $t);
        $t = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $t);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $t);
        $t = preg_replace('/^[\*\-] (.+)$/m', '<li>$1</li>', $t);
        $t = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $t);
        $t = nl2br($t);
        return $t;
    }
}
