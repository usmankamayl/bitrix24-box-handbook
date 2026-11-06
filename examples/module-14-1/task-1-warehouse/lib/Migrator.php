<?php
/**
 * Система миграций базы данных
 *
 * Управляет версионными изменениями структуры БД:
 * - Применение новых миграций
 * - Откат миграций
 * - Отслеживание текущей версии
 */

namespace Acme\Warehouse;

use Bitrix\Main\Config\Option;

class Migrator
{
    /** @var string ID модуля */
    protected $moduleId = 'acme.warehouse';

    /**
     * Применить все новые миграции
     *
     * Применяет все миграции, которые еще не были выполнены
     *
     * @return int Количество примененных миграций
     */
    public function migrate(): int
    {
        $currentVersion = $this->getCurrentVersion();
        $migrations = $this->getMigrations();

        $applied = 0;

        foreach ($migrations as $version => $migration) {
            if ($version > $currentVersion) {
                try {
                    // Применяем миграцию
                    $migration->up();

                    // Сохраняем новую версию
                    $this->setCurrentVersion($version);

                    $applied++;

                    AddMessage2Log(
                        "Migrator: Применена миграция #{$version}",
                        'acme.warehouse'
                    );
                } catch (\Exception $e) {
                    AddMessage2Log(
                        "Migrator: Ошибка применения миграции #{$version}: " . $e->getMessage(),
                        'acme.warehouse'
                    );
                    throw $e;
                }
            }
        }

        return $applied;
    }

    /**
     * Откатить последнюю миграцию
     *
     * @return bool
     */
    public function rollback(): bool
    {
        $currentVersion = $this->getCurrentVersion();

        if ($currentVersion === 0) {
            return false; // Нечего откатывать
        }

        $migrations = $this->getMigrations();

        if (!isset($migrations[$currentVersion])) {
            return false;
        }

        try {
            // Откатываем миграцию
            $migrations[$currentVersion]->down();

            // Уменьшаем версию
            $this->setCurrentVersion($currentVersion - 1);

            AddMessage2Log(
                "Migrator: Откачена миграция #{$currentVersion}",
                'acme.warehouse'
            );

            return true;
        } catch (\Exception $e) {
            AddMessage2Log(
                "Migrator: Ошибка отката миграции #{$currentVersion}: " . $e->getMessage(),
                'acme.warehouse'
            );
            throw $e;
        }
    }

    /**
     * Получить текущую версию миграций
     *
     * @return int
     */
    public function getCurrentVersion(): int
    {
        return (int)Option::get($this->moduleId, 'migration_version', 0);
    }

    /**
     * Установить текущую версию миграций
     *
     * @param int $version
     */
    protected function setCurrentVersion(int $version): void
    {
        Option::set($this->moduleId, 'migration_version', $version);
    }

    /**
     * Получить список всех миграций
     *
     * @return array [версия => объект миграции]
     */
    protected function getMigrations(): array
    {
        return [
            1 => new \Acme\Warehouse\Migrations\Migration001AddCategoryField(),
            // 2 => new \Acme\Warehouse\Migrations\Migration002AddSupplierField(),
            // 3 => new \Acme\Warehouse\Migrations\Migration003AddIndexes(),
        ];
    }

    /**
     * Получить статус миграций
     *
     * @return array
     */
    public function getStatus(): array
    {
        $currentVersion = $this->getCurrentVersion();
        $migrations = $this->getMigrations();

        $status = [
            'current_version' => $currentVersion,
            'total_migrations' => count($migrations),
            'applied' => [],
            'pending' => [],
        ];

        foreach ($migrations as $version => $migration) {
            if ($version <= $currentVersion) {
                $status['applied'][] = $version;
            } else {
                $status['pending'][] = $version;
            }
        }

        return $status;
    }
}
