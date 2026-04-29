<?php

declare(strict_types=1);

final class AdminMiddleware
{
    public function __construct(
        private AuthMiddleware $auth,
        private User $users
    ) {
    }

    public function requireAdminUserId(): int
    {
        $uid = $this->auth->requireUserId();
        if (!$this->users->isAdmin($uid)) {
            JsonResponse::error(403, 'Accès réservé aux administrateurs.', 'forbidden');
            exit;
        }
        return $uid;
    }
}
