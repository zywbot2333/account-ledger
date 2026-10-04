<?php

function e(mixed $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function money(mixed $v): string
{
    return number_format((float)$v, 2);
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function today(): string
{
    return date('Y-m-d');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_SESSION['csrf'], $_POST['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** 内联 SVG 图标（feather 风格线性图标） */
function icon(string $name, string $cls = ''): string
{
    $paths = [
        'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'chart'   => '<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>',
        'plus'    => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'trash'   => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-.7 13.1a2 2 0 0 1-2 1.9H7.7a2 2 0 0 1-2-1.9L5 6"/>',
        'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'chev-r'  => '<path d="M9 18l6-6-6-6"/>',
        'arr-l'   => '<path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>',
        'arr-r'   => '<path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>',
        'in'      => '<path d="M17 7L7 17"/><path d="M17 17H7V7"/>',
        'out'     => '<path d="M7 17L17 7"/><path d="M7 7h10v10"/>',
        'balance' => '<path d="M5 9h14"/><path d="M5 15h14"/>',
        'wallet'  => '<path d="M20 7H5a2 2 0 0 1-2-2 2 2 0 0 1 2-2h13v4"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-2"/><path d="M16.5 13.5h.01"/>',
        'note'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    ];
    $p = $paths[$name] ?? $paths['grid'];
    return '<svg class="ic ' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** 由账号名生成稳定的色相值，用于头像渐变配色 */
function avatar_hue(string $name): int
{
    return crc32($name) % 360;
}
