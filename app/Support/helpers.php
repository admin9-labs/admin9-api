<?php

if (! function_exists('is_prod')) {
    function is_prod(): bool
    {
        return app()->environment('production');
    }
}

if (! function_exists('is_local')) {
    function is_local(): bool
    {
        return app()->environment('local');
    }
}
