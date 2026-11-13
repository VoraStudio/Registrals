<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\PaymentTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Troba l'última factura emesa per a una sèrie i any concrets (per calcular el següent número i hash anterior).
     */
    public function findLatestInvoice(?string $series = null, ?int $year = null): ?Invoice
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1);

        if ($series !== null) {
            $qb->andWhere('i.series = :series')
                ->setParameter('series', $series);
        }

        if ($year !== null) {
            $qb->andWhere('i.year = :year')
                ->setParameter('year', $year);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Troba l'últim hash emès a tota la plataforma (per a l'encadenament global VeriFactu).
     */
    public function findLatestGlobalHash(): ?string
    {
        $latest = $this->createQueryBuilder('i')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $latest?->getCurrentHash();
    }

    /**
     * Retorna la factura associada a una transacció de pagament de Stripe.
     */
    public function findByPaymentTransaction(PaymentTransaction $transaction): ?Invoice
    {
        return $this->findOneBy(['paymentTransaction' => $transaction]);
    }

    /**
     * Retorna les factures d'un titular ordenades per data descendent.
     *
     * @return list<Invoice>
     */
    public function findByUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.issuedAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retorna totes les factures emeses en un trimestre fiscal (per a l'exportació de gestoria / Model 303).
     *
     * @return list<Invoice>
     */
    public function findForQuarter(int $year, int $quarter): array
    {
        return $this->findAllForPeriod($year, $quarter);
    }

    /**
     * Retorna el QueryBuilder filtrat per exercici i trimestre fiscal.
     */
    private function createPeriodQueryBuilder(int $year, int $quarter = 0): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.year = :year')
            ->setParameter('year', $year);

        if ($quarter >= 1 && $quarter <= 4) {
            $startMonth = (($quarter - 1) * 3) + 1;
            $endMonth = $quarter * 3;

            $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $startMonth));
            $lastDay = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $endMonth)))->format('t');
            $endDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 23:59:59', $year, $endMonth, $lastDay));

            $qb->andWhere('i.issuedAt >= :start')
               ->andWhere('i.issuedAt <= :end')
               ->setParameter('start', $startDate)
               ->setParameter('end', $endDate);
        }

        return $qb;
    }

    /**
     * Retorna les factures paginades per a un període fiscal.
     *
     * @return list<Invoice>
     */
    public function findPaginatedForPeriod(int $year, int $quarter = 0, int $page = 1, int $limit = 25): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        return $this->createPeriodQueryBuilder($year, $quarter)
            ->orderBy('i.issuedAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recompte total de factures d'un període fiscal.
     */
    public function countForPeriod(int $year, int $quarter = 0): int
    {
        return (int) $this->createPeriodQueryBuilder($year, $quarter)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Càlcul agregat de totals financers per al període complet (sense biaix de paginació).
     *
     * @return array{count: int, totalCents: int, subtotalCents: int, vatCents: int}
     */
    public function getFinancialTotalsForPeriod(int $year, int $quarter = 0): array
    {
        $res = $this->createPeriodQueryBuilder($year, $quarter)
            ->select('COUNT(i.id) AS cnt, COALESCE(SUM(i.totalCents), 0) AS total, COALESCE(SUM(i.subtotalCents), 0) AS subtotal, COALESCE(SUM(i.vatAmountCents), 0) AS vat')
            ->getQuery()
            ->getSingleResult();

        return [
            'count' => (int) ($res['cnt'] ?? 0),
            'totalCents' => (int) ($res['total'] ?? 0),
            'subtotalCents' => (int) ($res['subtotal'] ?? 0),
            'vatCents' => (int) ($res['vat'] ?? 0),
        ];
    }

    /**
     * Retorna totes les factures d'un període fiscal per a l'exportació oficial (Model 303 / Gestoria).
     *
     * @return list<Invoice>
     */
    public function findAllForPeriod(int $year, int $quarter = 0): array
    {
        return $this->createPeriodQueryBuilder($year, $quarter)
            ->orderBy('i.issuedAt', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

