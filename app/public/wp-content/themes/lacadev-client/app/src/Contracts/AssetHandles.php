<?php
/**
 * @package LacadevHub
 */

namespace App\Contracts;

interface AssetHandles
{
    // ── Frontend ──────────────────────────────────────────────────────────
    const THEME_JS      = 'theme-js-bundle';
    const THEME_CSS     = 'theme-css-bundle';
    const THEME_STYLES  = 'theme-styles';
    const VENDORS_JS    = 'theme-vendors-js';
    const CRITICAL_JS   = 'theme-critical-js';
    const ARCHIVE_JS    = 'theme-archive-js';
    const COMMENTS_JS   = 'theme-comments-js';
    const SINGLE_CSS    = 'theme-single-css';

    // ── Admin ─────────────────────────────────────────────────────────────
    const ADMIN_CSS     = 'theme-admin-css-bundle';
    const ADMIN_JS      = 'theme-admin-js-bundle';

    // ── Editor (Gutenberg) ────────────────────────────────────────────────
    const EDITOR_CSS    = 'theme-editor-css-bundle';
    const EDITOR_JS     = 'theme-editor-js-bundle';

    // ── Login page ────────────────────────────────────────────────────────
    const LOGIN_JS      = 'theme-login-js-bundle';
    const LOGIN_CSS     = 'theme-login-css-bundle';
}
