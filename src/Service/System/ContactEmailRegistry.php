<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Repository\SystemSettingRepository;
use Throwable;

/**
 * Registre centralitzat de les adreces de correu corporatives i operatives de Registrals.
 */
final class ContactEmailRegistry
{
    public const DEFINITIONS = [
        'general' => [
            'settingKey' => 'contact_email_general',
            'label' => 'Contacte General & Landing',
            'description' => 'Canal principal de comunicació de la plataforma.',
            'places' => ['Landing (secció Contacte)', 'Peu de pàgina (Footer)', 'Pàgina /contacte', 'Avís Legal (/legal)', 'JSON-LD SEO'],
            'default' => 'info@registrals.com',
        ],
        'suport' => [
            'settingKey' => 'contact_email_suport',
            'label' => 'Suport & Atenció al Client',
            'description' => 'Consultes d\'ús i suport a usuaris registrats.',
            'places' => ['Àrea privada: Plans i quotes (/app/plans)'],
            'default' => 'suport@registrals.com',
        ],
        'legal' => [
            'settingKey' => 'contact_email_legal',
            'label' => 'Departament Jurídic',
            'description' => 'Compliment normatiu i requeriments legals.',
            'places' => ['Avís Legal (/legal)'],
            'default' => 'legal@registrals.com',
        ],
        'privacitat' => [
            'settingKey' => 'contact_email_privacitat',
            'label' => 'Privadesa & DPO (RGPD)',
            'description' => 'Exercici de drets ARCO-POL i delegat de protecció de dades.',
            'places' => ['Avís Legal (/legal)', 'Política de Privadesa RGPD'],
            'default' => 'privacitat@registrals.com',
        ],
        'premsa' => [
            'settingKey' => 'contact_email_premsa',
            'label' => 'Premsa & Comunicació',
            'description' => 'Sala de premsa i peticions de mitjans.',
            'places' => ['Sala de Premsa (/premsa)', 'Pàgina /contacte'],
            'default' => 'premsa@registrals.com',
        ],
        'perits' => [
            'settingKey' => 'contact_email_perits',
            'label' => 'Perits & Asseguradores',
            'description' => 'Consultes corporatives de verificació de certificats.',
            'places' => ['Verificació pública QR (/verify/{uuid})'],
            'default' => 'perits@registrals.cat',
        ],
        'alertes' => [
            'settingKey' => 'contact_email_alertes',
            'label' => 'Alertes Tècniques & Workers',
            'description' => 'Destinatari de les alertes automàtiques de cues i sistema.',
            'places' => ['Comanda cron (app:system:check-workers)'],
            'default' => 'carles@voradata.cat',
        ],
    ];

    /** @var array<string, string>|null */
    private ?array $resolvedCache = null;

    public function __construct(
        private readonly SystemSettingRepository $settingRepository
    ) {
    }

    public function get(string $channel): string
    {
        $all = $this->all();

        return $all[$channel] ?? (self::DEFINITIONS['general']['default']);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->resolvedCache !== null) {
            return $this->resolvedCache;
        }

        $result = [];
        foreach (self::DEFINITIONS as $channel => $def) {
            $result[$channel] = $def['default'];
        }

        try {
            $keys = array_column(self::DEFINITIONS, 'settingKey');
            $rows = $this->settingRepository->findBy(['key' => $keys]);
            $byKey = [];
            foreach ($rows as $row) {
                $val = trim((string) $row->getValue());
                if ($val !== '') {
                    $byKey[$row->getKey()] = $val;
                }
            }

            foreach (self::DEFINITIONS as $channel => $def) {
                if (isset($byKey[$def['settingKey']])) {
                    $result[$channel] = $byKey[$def['settingKey']];
                }
            }
        } catch (Throwable) {
        }

        return $this->resolvedCache = $result;
    }

    public function set(string $channel, string $email): void
    {
        if (!isset(self::DEFINITIONS[$channel])) {
            return;
        }

        $def = self::DEFINITIONS[$channel];
        $this->settingRepository->set($def['settingKey'], trim($email), $def['label']);
        $this->resolvedCache = null;
    }
}

