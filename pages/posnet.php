<?php
require_once __DIR__ . '/../config.php';

if (strtoupper($_SESSION['usuario']['rol'] ?? '') !== 'POSNET') {
    header('Location: ../index.html');
    exit;
}

include __DIR__ . '/posnet.html';