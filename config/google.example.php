<?php
/**
 * Google OAuth configuration.
 * Copy this file to config/google.php and fill in your Client ID.
 *
 * How to get a Client ID:
 *   1. Go to https://console.cloud.google.com/
 *   2. Create a project (or select an existing one).
 *   3. APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client ID
 *   4. Application type: Web application
 *   5. Under "Authorized JavaScript origins" add:
 *        http://localhost          (local dev)
 *        https://yourdomain.com    (production)
 *   6. No redirect URIs needed — we use the One Tap / popup flow.
 *   7. Copy the Client ID (looks like: XXXXXXXXXX-xxxx.apps.googleusercontent.com)
 *
 * This file is git-ignored. Never commit it with a real client ID.
 */
return [
    'client_id' => '',   // paste your Client ID here
];
