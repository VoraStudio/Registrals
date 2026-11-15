<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Repository\UserActivityLogRepository;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Enum\KycStatus;
use App\Form\ChangePasswordFormType;
use App\Form\LegacyDesignationFormType;
use App\Form\ProfileFormType;
use App\Repository\LegacyContactRepository;
use App\Repository\SuccessionClaimRepository;
use App\Service\Identity\KycVerificationService;
use App\Service\Legacy\LegacyCertificateIssuerService;
use App\Service\Legacy\LegacyDesignationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/app/perfil', name: 'app_profile', methods: ['GET', 'POST'])]
    #[Route('/app/profile', name: 'app_profile_legacy', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ?LegacyContactRepository $legacyContactRepository = null,
        ?LegacyDesignationService $legacyDesignationService = null,
        ?LegacyCertificateIssuerService $legacyCertificateIssuer = null,
        ?SuccessionClaimRepository $successionClaimRepository = null,
        ?KycVerificationService $kycService = null,
        ?UserActivityLogRepository $activityLogRepository = null,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isIdentityLocked = in_array($user->getKycStatus(), [KycStatus::PENDING, KycStatus::APPROVED], true);
        $isTaxIdLocked = $isIdentityLocked && trim((string) ($user->getTaxId() ?? '')) !== '';

        // Formulari de dades personals
        $profileForm = $this->createForm(ProfileFormType::class, $user, [
            'lock_identity_fields' => $isIdentityLocked,
            'lock_tax_id' => $isTaxIdLocked,
        ]);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $normalizedTaxId = trim((string) ($user->getTaxId() ?? ''));
            $user->setTaxId($normalizedTaxId !== '' ? strtoupper($normalizedTaxId) : null);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Les dades del teu perfil s\'han actualitzat correctament.');
            return $this->redirectToRoute('app_profile');
        }

        // Formulari de contrasenya (només si és compte local)
        $passwordForm = null;
        $isOAuthUser = $user->getAuthProvider() !== 'local';

        if (!$isOAuthUser) {
            $passwordForm = $this->createForm(ChangePasswordFormType::class);
            $passwordForm->handleRequest($request);

            if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
                $newPassword = (string) $passwordForm->get('newPassword')->getData();
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'La contrasenya de seguretat s\'ha modificat correctament.');
                return $this->redirectToRoute('app_profile');
            }
        }

        $legacyContacts = $legacyContactRepository?->findByOwnerOrdered($user) ?? [];
        $incomingLegacyContacts = $legacyDesignationService?->getIncomingDesignations($user) ?? [];
        $incomingClaimsByContactUuid = $successionClaimRepository?->findLatestByLegacyContacts($incomingLegacyContacts) ?? [];
        $legacyEligibility = $legacyDesignationService?->evaluateDesignationEligibility($user) ?? [
            'allowed' => false,
            'reason' => 'plan_not_allowed',
            'maxSlots' => 0,
            'usedSlots' => 0,
        ];
        $legacyForm = null;
        if ($legacyDesignationService !== null && $legacyEligibility['allowed']) {
            $legacyForm = $this->createForm(LegacyDesignationFormType::class, null, [
                'action' => $this->generateUrl('app_profile_legacy_designate'),
            ])->createView();
        }

        $hasCompleteLegalName = trim($user->getFirstName()) !== '' && trim($user->getLastName()) !== '';
        $hasCompleteIdentityProfile = $kycService?->hasCompleteIdentityProfile($user)
            ?? ($hasCompleteLegalName && trim((string) ($user->getTaxId() ?? '')) !== '');

        $kycContext = [
            'priceCents' => $kycService?->getKycPriceCents($user) ?? ($user->getPlan()?->getKycPriceCents() ?? 350),
            'isIncludedInPlan' => $kycService?->isIncludedInPlan($user) ?? (in_array('ROLE_ADMIN', $user->getRoles(), true) || ($user->getPlan()?->getKycIncluded() ?? 0) > 0),
            'canStartDirectly' => $kycService?->canStartWithoutCheckout($user) ?? (in_array('ROLE_ADMIN', $user->getRoles(), true) || ($user->getPlan()?->getKycIncluded() ?? 0) > 0 || $user->getKycPaidCredits() > 0),
            'hasCompleteLegalName' => $hasCompleteLegalName,
            'hasCompleteIdentityProfile' => $hasCompleteIdentityProfile,
            'identityLocked' => $isIdentityLocked,
            'taxIdLocked' => $isTaxIdLocked,
        ];

        $activityLogs = [];
        $activityTotal = 0;
        if ($activityLogRepository !== null) {
            try {
                $activityResult = $activityLogRepository->findPaginatedByUserUuid($user->getUuid()->toRfc4122(), 1, 10);
                $activityLogs = $activityResult['items'];
                $activityTotal = $activityResult['total'];
            } catch (\Throwable) {
                $activityLogs = [];
                $activityTotal = 0;
            }
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm?->createView(),
            'isOAuthUser' => $isOAuthUser,
            'legacyContacts' => $legacyContacts,
            'incomingLegacyContacts' => $incomingLegacyContacts,
            'incomingClaimsByContactUuid' => $incomingClaimsByContactUuid,
            'legacyEligibility' => $legacyEligibility,
            'legacyCertEligibility' => $legacyCertificateIssuer?->evaluateEmissionEligibility($user),
            'legacyForm' => $legacyForm,
            'kycContext' => $kycContext,
            'activityLogs' => $activityLogs,
            'activityTotal' => $activityTotal,
            'activityActionGroups' => UserActivityAction::groupedByCategory(),
        ]);
    }

    #[Route('/app/perfil/seguretat/2fa', name: 'app_profile_toggle_2fa', methods: ['POST'])]
    public function toggleTwoFactor(
        Request $request,
        EntityManagerInterface $entityManager,
        ?UserActivityLogger $activityLogger = null
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getAuthProvider() !== 'local') {
            $this->addFlash('error', 'Els comptes vinculats a Google gestionen la verificació en dos passos directament amb Google.');
            return $this->redirectToRoute('app_profile');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('profile_toggle_2fa', $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Torna-ho a provar.');
            return $this->redirectToRoute('app_profile');
        }

        $newState = !$user->isTwoFactorEnabled();
        $user->setTwoFactorEnabled($newState);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $activityLogger?->log(
            user: $user,
            action: UserActivityAction::TWO_FACTOR_TOGGLED,
            description: $newState
                ? 'Activació de verificació en dos passos (2FA) per correu electrònic al perfil'
                : 'Desactivació de verificació en dos passos (2FA) per correu electrònic al perfil',
            details: ['two_factor_enabled' => $newState]
        );

        $this->addFlash(
            'success',
            $newState
                ? 'Doble factor (2FA) activat! En iniciar sessió rebràs un codi de 6 dígits al teu correu.'
                : 'Doble factor (2FA) desactivat per a l\'inici de sessió.'
        );

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/app/perfil/activitat', name: 'app_profile_activity_logs', methods: ['GET'])]
    public function activityLogs(Request $request, UserActivityLogRepository $activityLogRepository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->getString('q', '');
        $action = $request->query->getString('action', '');

        $result = $activityLogRepository->findPaginatedByUserUuid(
            userUuid: $user->getUuid()->toRfc4122(),
            page: $page,
            limit: 25,
            search: $search !== '' ? $search : null,
            action: $action !== '' ? $action : null,
        );

        $items = array_map(static function ($log): array {
            return [
                'actionLabel' => $log->getAction()->label(),
                'actionBadgeClasses' => $log->getAction()->badgeClasses(),
                'description' => $log->getDescription(),
                'createdAt' => $log->getCreatedAt()->format('d/m/Y H:i:s'),
                'ipAddress' => $log->getIpAddress(),
            ];
        }, $result['items']);

        return $this->json([
            'items' => $items,
            'total' => $result['total'],
            'page' => $page,
            'pages' => $result['pages'],
            'limit' => 25,
        ]);
    }
}
