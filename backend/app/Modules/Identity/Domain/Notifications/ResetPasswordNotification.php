<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
