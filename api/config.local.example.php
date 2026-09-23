<?php

// Copy to config.local.php on your server and set a strong random secret.
// Do not commit config.local.php to version control.

return [
    'session_secret' => 'change-this-to-a-long-random-string',
    // Optional overrides. Defaults (if omitted) are:
    //   username: superadmin
    //   password: Woodpecker#Admin1
    'super_admin_username' => 'superadmin',
    'super_admin_password' => 'Woodpecker#Admin1',
];
