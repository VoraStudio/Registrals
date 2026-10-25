<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SystemSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemSetting>
 */
class SystemSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemSetting::class);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->findOneBy(['key' => $key]);
        return $setting ? $setting->getValue() : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $val = $this->get($key);
        if ($val === null) {
            return $default;
        }

        return \in_array(strtolower($val), ['1', 'true', 'yes', 'on'], true);
    }

    public function set(string $key, ?string $value, ?string $description = null): void
    {
        $setting = $this->findOneBy(['key' => $key]);
        if (!$setting) {
            $setting = new SystemSetting($key, $value, $description);
            $this->getEntityManager()->persist($setting);
        } else {
            $setting->setValue($value);
            if ($description !== null) {
                $setting->setDescription($description);
            }
        }

        $this->getEntityManager()->flush();
    }
}
