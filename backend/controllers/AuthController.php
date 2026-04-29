<?php

declare(strict_types=1);

final class AuthController
{
    public function __construct(
        private PDO $pdo,
        private array $config,
        private User $users
    ) {
    }

    public function register(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            JsonResponse::error(400, 'JSON invalide', 'invalid_json');
            return;
        }
        if (!is_array($data)) {
            JsonResponse::error(400, 'Corps de requête invalide', 'invalid_body');
            return;
        }

        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        $errors = [];
        if ($name === '' || strlen($name) > 120) {
            $errors[] = 'Le nom est requis (max 120 caractères).';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($errors !== []) {
            JsonResponse::send(422, ['error' => implode(' ', $errors), 'code' => 'validation_failed']);
            return;
        }

        if ($this->users->emailExists($email)) {
            JsonResponse::error(409, 'Cet email est déjà utilisé.', 'email_taken');
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = $this->users->create($name, $email, $hash);
        $token = Jwt::encode(['sub' => $id], $this->config['jwt_secret'], $this->config['jwt_ttl_seconds']);
        $user = $this->users->findById($id);

        JsonResponse::send(201, [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->config['jwt_ttl_seconds'],
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => ((int) ($user['is_admin'] ?? 0)) === 1,
            ],
        ]);
    }

    public function login(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            JsonResponse::error(400, 'JSON invalide', 'invalid_json');
            return;
        }
        if (!is_array($data)) {
            JsonResponse::error(400, 'Corps de requête invalide', 'invalid_body');
            return;
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            JsonResponse::error(422, 'Email et mot de passe requis.', 'validation_failed');
            return;
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password'])) {
            JsonResponse::error(401, 'Identifiants incorrects.', 'invalid_credentials');
            return;
        }

        $id = (int) $user['id'];
        $token = Jwt::encode(['sub' => $id], $this->config['jwt_secret'], $this->config['jwt_ttl_seconds']);

        JsonResponse::send(200, [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->config['jwt_ttl_seconds'],
            'user' => [
                'id' => $id,
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => ((int) ($user['is_admin'] ?? 0)) === 1,
            ],
        ]);
    }
}
