<?php
/**
 * Public entry point for the Customer360 folder.
 *
 * Apache can expose this directory directly, so this tiny redirect prevents users
 * from seeing a raw directory listing and sends them into the finished landing page.
 */
header('Location: frontend/index.html', true, 302);
exit;
