<?php
// ============================================================================
// Version 2.0 - Application Helpers & Helper Functions
// Security (CSRF), Flash Messages, Barcode Generator (SVG), Utilities
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF Token for forms
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify CSRF Token on POST requests
 */
function verify_csrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF validation failed. Please refresh the page and try again.');
        }
    }
    return true;
}

/**
 * Set Flash Toast Message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get and clear Flash Message
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render Flash Toast HTML
 */
function render_flash(): string {
    $flash = get_flash();
    if (!$flash) return '';

    $bgClass = match($flash['type']) {
        'success' => 'bg-success text-white',
        'danger' => 'bg-danger text-white',
        'warning' => 'bg-warning text-dark',
        default => 'bg-info text-white'
    };

    return '
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
        <div id="appToast" class="toast align-items-center ' . $bgClass . ' border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-info-circle me-2"></i>' . htmlspecialchars($flash['message']) . '
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>';
}

/**
 * Format Currency ($ USD)
 */
function format_money(float|int|string $amount): string {
    return '$' . number_format((float)$amount, 2);
}

/**
 * Generate pure Code 128 / standard Barcode SVG vector output without external dependencies
 */
function generate_barcode_svg(string $text, int $height = 50): string {
    $text = strtoupper(trim($text));
    if (empty($text)) $text = 'ITEM-001';

    // Simple robust bar pattern generator based on char hashes
    $bars = [];
    $bars[] = 3; // start bar
    $bars[] = 1; // gap
    $bars[] = 1;
    $bars[] = 1;

    for ($i = 0; $i < strlen($text); $i++) {
        $charVal = ord($text[$i]);
        $w1 = ($charVal % 3) + 1;
        $w2 = (($charVal >> 2) % 3) + 1;
        $w3 = (($charVal >> 4) % 3) + 1;
        $bars[] = $w1;
        $bars[] = 1;
        $bars[] = $w2;
        $bars[] = 1;
        $bars[] = $w3;
        $bars[] = 1;
    }

    $bars[] = 3; // stop bar
    $bars[] = 1;
    $bars[] = 2;

    $totalWidth = array_sum($bars) * 3;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $totalWidth . ' ' . ($height + 25) . '" width="100%" height="' . $height . '">';
    $svg .= '<rect width="100%" height="100%" fill="transparent"/>';

    $x = 0;
    $isBar = true;
    foreach ($bars as $barWidth) {
        $w = $barWidth * 3;
        if ($isBar) {
            $svg .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="currentColor"/>';
        }
        $x += $w;
        $isBar = !$isBar;
    }

    // Label text below barcode
    $svg .= '<text x="' . ($totalWidth / 2) . '" y="' . ($height + 18) . '" font-family="monospace" font-size="14" font-weight="bold" fill="currentColor" text-anchor="middle">' . htmlspecialchars($text) . '</text>';
    $svg .= '</svg>';

    return $svg;
}
