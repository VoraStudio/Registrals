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
            'description' => 'Peu de pàgina, formulari de contacte, JSON-LD i avís legal general.',
            'default' => 'info@registrals.com',
        ],
        'suport' => [
            'settingKey' => 'contact_email_suport',
            'label' => 'Suport & Atenció al Client',
            'description' => 'Canvis de pla, consultes d\'ús i suport privat.',
            'default' => 'suport@registrals.com',
        ],
        'legal' => [
            'settingKey' => 'contact_email_legal',
            'label' => 'Departament Jurídic',
            'description' => 'Compliment normatiu i requeriments legals.',
            'default' => 'legal@registrals.com',
        ],
        'privacitat' => [
            'settingKey' => 'contact_email_privacitat',
            'label' => 'Privadesa & DPO (RGPD)',
            'description' => 'Exercici de drets ARCO-POL i delegat de protecció de dades.',
            'default' => 'privacitat@registrals.com',
        ],
        'premsa' => [
            'settingKey' => 'contact_email_premsa',
            'label' => 'Premsa & Comunicació',
            'description' => 'Sala de premsa i peticions de mitjans.',
            'default' => 'premsa@registrals.com',
        ],
        'perits' => [
            'settingKey' => 'contact_email_perits',
            'label' => 'Perits & Asseguradores',
            'description' => 'Consultes corporatives de verificació a /verify/{uuid}.',
            'default' => 'perits@registrals.cat',
        ],
        'alertes' => [
            'settingKey' => 'contact_email_alertes',
            'label' => 'Alertes Tècniques & Workers',
            'description' => 'Destinatari de les alertes automàtiques de cues i sistema.',
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

