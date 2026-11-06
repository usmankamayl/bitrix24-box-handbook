<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstallationRepository.php';

final class TokenProvider
{
    private ?string $cachedToken = null;

    public function __construct(
        private readonly InstallationRepository $repo,
        private readonly string $memberId,
    ) {
    }

    public function getToken(): string
    {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $this->cachedToken = $this->repo->getValidAccessTokenWithLock(
            $this->memberId,
            fn (string $refreshToken) => $this->refreshTokens($refreshToken)
        );

        return $this->cachedToken;
    }

    public function forceRefresh(): string
    {
        // Сбрасываем локальный кэш внутри запроса и идём в БД заново —
        // getValidAccessTokenWithLock сам решит, протух токен или нет
        $this->cachedToken = null;

        return $this->getToken();
    }

    private function refreshTokens(string $refreshToken): array
    {
        $ch = curl_init(APP_OAUTH_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'client_id' => APP_CLIENT_ID,
                'client_secret' => APP_CLIENT_SECRET,
                'refresh_token' => $refreshToken,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            throw new RuntimeException('Token refresh failed: ' . ($raw ?: "HTTP {$httpCode}"));
        }

        return $data;
    }
}
