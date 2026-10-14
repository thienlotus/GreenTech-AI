<?php

declare(strict_types=1);

if (!function_exists('get_ai_detect_endpoint')) {
    function get_ai_detect_endpoint(): string
    {
        // Priority: Environment variable (production) -> default Hugging Face endpoint
        $fromEnv = trim((string)getenv('AI_DETECT_URL'));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return 'https://levuthien-greentech-ai-api.hf.space/detect';
    }
}

if (!function_exists('get_ai_live_track_endpoint')) {
    function get_ai_live_track_endpoint(): string
    {
        $fromEnv = trim((string)getenv('AI_LIVE_TRACK_URL'));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $detectEndpoint = get_ai_detect_endpoint();
        if (preg_match('#/detect/?$#i', $detectEndpoint)) {
            return (string)preg_replace('#/detect/?$#i', '/live_track', $detectEndpoint);
        }

        return rtrim($detectEndpoint, '/') . '/live_track';
    }
}

if (!function_exists('get_ai_timeout_seconds')) {
    function get_ai_timeout_seconds(): int
    {
        $value = (int)getenv('AI_TIMEOUT_SECONDS');
        return $value > 0 ? $value : 35;
    }
}

if (!function_exists('get_ai_connect_timeout_seconds')) {
    function get_ai_connect_timeout_seconds(): int
    {
        $value = (int)getenv('AI_CONNECT_TIMEOUT_SECONDS');
        return $value > 0 ? $value : 10;
    }
}

if (!function_exists('get_ai_detect_connect_timeout_seconds')) {
    function get_ai_detect_connect_timeout_seconds(): int
    {
        $value = (int)getenv('AI_DETECT_CONNECT_TIMEOUT_SECONDS');
        return $value > 0 ? $value : get_ai_connect_timeout_seconds();
    }
}

if (!function_exists('get_ai_live_connect_timeout_seconds')) {
    function get_ai_live_connect_timeout_seconds(): int
    {
        $value = (int)getenv('AI_LIVE_CONNECT_TIMEOUT_SECONDS');
        if ($value > 0) {
            return $value;
        }

        return min(get_ai_connect_timeout_seconds(), 10);
    }
}

if (!function_exists('get_ai_retry_attempts')) {
    function get_ai_retry_attempts(): int
    {
        $value = (int)getenv('AI_RETRY_ATTEMPTS');
        return $value > 0 ? $value : 2;
    }
}

if (!function_exists('get_ai_detect_timeout_seconds')) {
    function get_ai_detect_timeout_seconds(): int
    {
        $value = (int)getenv('AI_DETECT_TIMEOUT_SECONDS');
        return $value > 0 ? $value : get_ai_timeout_seconds();
    }
}

if (!function_exists('get_ai_live_timeout_seconds')) {
    function get_ai_live_timeout_seconds(): int
    {
        $value = (int)getenv('AI_LIVE_TIMEOUT_SECONDS');
        if ($value > 0) {
            return $value;
        }

        return min(get_ai_timeout_seconds(), 12);
    }
}

if (!function_exists('get_ai_detect_retry_attempts')) {
    function get_ai_detect_retry_attempts(): int
    {
        $value = (int)getenv('AI_DETECT_RETRY_ATTEMPTS');
        return $value > 0 ? $value : 2;
    }
}

if (!function_exists('get_ai_live_retry_attempts')) {
    function get_ai_live_retry_attempts(): int
    {
        $value = (int)getenv('AI_LIVE_RETRY_ATTEMPTS');
        return $value > 0 ? $value : 2;
    }
}

if (!function_exists('should_relax_ai_ssl_verify')) {
    function should_relax_ai_ssl_verify(): bool
    {
        $raw = trim((string)getenv('AI_RELAX_SSL_VERIFY'));
        if ($raw === '') {
            return false;
        }

        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
    }
}
