<?php

declare(strict_types=1);

// O backend não serve páginas: a SPA React é servida pelo bitman-web (nginx).
// O grupo "web" continua existindo para a sessão da SPA (Sanctum, CSRF).
