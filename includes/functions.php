<?php
// includes/functions.php

if (!function_exists('getFormattedName')) {
    /**
     * Formate un nom complet pour n'en garder que le nom de famille ou une partie pertinente.
     * Gère les noms composés comme AIT HAMDANE.
     * @param string|null $name Le nom complet à formater.
     * @return string Le nom formaté.
     */
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $words = array_filter(preg_split('/\s+/', trim(strtoupper($name))));
        $wordCount = count($words);
        if ($wordCount === 0) return '';
        if ($wordCount <= 1) return $words[0];
        if ($wordCount >= 3 && mb_strlen($words[1]) <= 3) return $words[1] . ' ' . $words[2];
        $dernierMot = $words[$wordCount - 1];
        if (mb_strlen($dernierMot) <= 2 && $wordCount > 1) return $words[$wordCount - 2] . ' ' . $dernierMot;
        return $dernierMot;
    }
}
?>