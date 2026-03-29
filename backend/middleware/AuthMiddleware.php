<?php

declare(strict_types=1);

final class AuthMiddleware
{
    public function __construct(private array $config)
    {
    }

    /**
     * Retourne l'ID utilisateur ou null si non authentifié.
     */
    public function optionalUserId(): ?int
    {
        $uid = $this->parseBearerUserId();
        return $uid;
    }

    /**
     * Exige un JWT valide ; envoie 401 et termine si absent/invalide.
     */
    public function requireUserId(): int
    {
        $uid = $this->parseBearerUserId();
        if ($uid === null) {
            JsonResponse::error(401, 'Authentification requise', 'unauthorized');
            exit;
        }
        return $uid;
    }

    private function parseBearerUserId(): ?int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $header = is_string($v) ? $v : '';
                    break;
                }
            }
        }
        if (!preg_match('/^Bearer\s+(\S+)/i', $header, $m)) {
            return null;
        }
        $token = $m[1];
        try {
            $payload = Jwt::decode($token, $this->config['jwt_secret']);
            $sub = $payload['sub'] ?? null;
            if (!is_numeric($sub)) {
                return null;
            }
            return (int) $sub;
        } catch (Throwable) {
            return null;
        }
    }
}
