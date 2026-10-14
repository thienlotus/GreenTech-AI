<?php

if (!function_exists('normalize_pest_key')) {
    function normalize_pest_key($name) {
        $normalized = strtolower(trim((string)$name));
        $normalized = str_replace(array('-', '_', '/'), ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }
}

if (!function_exists('translate_pest_name_vi')) {
    function translate_pest_name_vi($name) {
        $key = normalize_pest_key($name);

        $exact_map = array(
            'aphids' => 'Rệp mềm',
            'aphid' => 'Rệp mềm',
            'bocanhcung' => 'Bọ cánh cứng',
            'bo canh cung' => 'Bọ cánh cứng',
            'chauchau' => 'Châu chấu',
            'chau chau' => 'Châu chấu',
            'ocsen' => 'Ốc sên',
            'oc sen' => 'Ốc sên',
            'sauhai' => 'Sâu hại',
            'sau hai' => 'Sâu hại'
        );

        if (isset($exact_map[$key])) {
            return $exact_map[$key];
        }

        $contains_map = array(
            'aphid' => 'Rệp mềm',
            'bocanhcung' => 'Bọ cánh cứng',
            'bo canh cung' => 'Bọ cánh cứng',
            'chauchau' => 'Châu chấu',
            'chau chau' => 'Châu chấu',
            'ocsen' => 'Ốc sên',
            'oc sen' => 'Ốc sên',
            'sauhai' => 'Sâu hại',
            'sau hai' => 'Sâu hại'
        );

        foreach ($contains_map as $pattern => $translated) {
            if (strpos($key, $pattern) !== false) {
                return $translated;
            }
        }

        // Fall back to a readable label when we do not have a mapping yet.
        $clean = ucwords($key);
        return $clean !== '' ? $clean : 'Côn trùng chưa xác định';
    }
}
