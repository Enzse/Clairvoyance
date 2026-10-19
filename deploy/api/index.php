<?php

// Public entry point at https://<domain>/api. The API itself lives outside the web root, in
// ../../clairvoyance, so its configuration and code can never be downloaded.
require __DIR__ . '/../../clairvoyance/index.php';
