<?php

require_once __DIR__ . '/config.php';

final class InstallationRepository
{
    public function __construct(private readonly PDO $pdo = new PDO('x') /* overridden below */)
    {
    }

    public static function create(): self
    {
        $repo = new self(getPdo());
        return $repo;
    }

    public function save(array $tokens, string $domain, string $applicationToken): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_installations
                (member_id, domain, access_token, refresh_token, expires_at, scope,
                 application_token, status, installed_at, updated_at)
             VALUES
                (:member_id, :domain, :access_token, :refresh_token, :expires_at, :scope,
                 :application_token, "active", NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                domain = VALUES(domain),
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                scope = VALUES(scope),
                application_token = VALUES(application_token),
                status = "active",
                uninstalled_at = NULL,
                purge_after = NULL,
                updated_at = NOW()'
        );

        $stmt->execute([
            'member_id'         => $tokens['member_id'],
            'domain'            => $domain,
            'access_token'      => $tokens['access_token'],
            'refresh_token'     => $tokens['refresh_token'],
            'expires_at'        => date('Y-m-d H:i:s', time() + (int)$tokens['expires_in']),
            'scope'             => $tokens['scope'],
            'application_token' => $applicationToken,
        ]);
    }

    public function findActiveByMemberId(string $memberId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM app_installations WHERE member_id = ? AND status = "active"'
        );
        $stmt->execute([$memberId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Читает строку с блокировкой (FOR UPDATE) и, если нужно, обновляет токены
     * внутри той же транзакции. Вызывающий код передаёт $refresher — функцию,
     * которая обменяет refresh_token на новую пару токенов.
     *
     * Блокировка строки гарантирует: пока один параллельный запрос рефрешит
     * токен для этого member_id, второй ждёт на SELECT ... FOR UPDATE и после
     * коммита первого получает уже свежий access_token без повторного рефреша.
     */
    public function getValidAccessTokenWithLock(string $memberId, callable $refresher): string
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT access_token, refresh_token, expires_at
                 FROM app_installations
                 WHERE member_id = ? AND status = "active"
                 FOR UPDATE'
            );
            $stmt->execute([$memberId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new RuntimeException("Active installation not found: {$memberId}");
            }

            // Запас 60 секунд на сетевую задержку между "токен ещё жив" и фактическим вызовом API
            if (strtotime($row['expires_at']) > time() + 60) {
                $this->pdo->commit();
                return $row['access_token'];
            }

            $fresh = $refresher($row['refresh_token']);

            $this->pdo->prepare(
                'UPDATE app_installations
                 SET access_token = :access_token,
                     refresh_token = :refresh_token,
                     expires_at = :expires_at,
                     updated_at = NOW()
                 WHERE member_id = :member_id'
            )->execute([
                'access_token'  => $fresh['access_token'],
                'refresh_token' => $fresh['refresh_token'],
                'expires_at'    => date('Y-m-d H:i:s', time() + (int)$fresh['expires_in']),
                'member_id'     => $memberId,
            ]);

            $this->pdo->commit();
            return $fresh['access_token'];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function markUninstalled(string $memberId, int $purgeAfterDays = 14): void
    {
        $this->pdo->prepare(
            'UPDATE app_installations
             SET status = "uninstalled",
                 uninstalled_at = NOW(),
                 purge_after = DATE_ADD(NOW(), INTERVAL :days DAY),
                 updated_at = NOW()
             WHERE member_id = :member_id'
        )->execute(['days' => $purgeAfterDays, 'member_id' => $memberId]);
    }

    /** Для cron-воркера: находит установки, у которых прошёл срок хранения после удаления */
    public function findDueForPurge(): array
    {
        $stmt = $this->pdo->query(
            'SELECT member_id FROM app_installations
             WHERE status = "uninstalled" AND purge_after <= NOW()'
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function purge(string $memberId): void
    {
        // Окончательное удаление персональных данных портала.
        // В реальном приложении здесь же чистятся связанные таблицы
        // (например, импортированные сущности, логи с ФИО/телефонами и т.д.)
        $this->pdo->prepare('DELETE FROM app_installations WHERE member_id = ?')
            ->execute([$memberId]);
    }
}
