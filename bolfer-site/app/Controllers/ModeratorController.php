<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ModeratorRepository;
use App\Support\View;

final class ModeratorController
{
    public function show(string $slug): void
    {
        $moderator = (new ModeratorRepository())->find($slug);

        if ($moderator === null) {
            http_response_code(404);
            View::render('404');
            return;
        }

        View::render('moderators/profile', [
            'moderator' => $moderator,
        ]);
    }
}
