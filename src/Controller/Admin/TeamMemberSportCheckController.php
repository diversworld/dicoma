<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TeamMemberSportCheckController extends AbstractController
{
    #[Route('/admin/team-member/sport-check', name: 'admin_team_member_sport_check', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $memberId = $request->query->get('member');
        $teamId = $request->query->get('team');
        if (!is_string($memberId) || !ctype_digit($memberId) || (int) $memberId < 1
            || !is_string($teamId) || !ctype_digit($teamId) || (int) $teamId < 1) {
            return $this->json(['state' => 'unselected']);
        }

        $member = $em->getRepository(Member::class)->find((int) $memberId);
        $team = $em->getRepository(Team::class)->find((int) $teamId);
        if (!$member instanceof Member || !$team instanceof Team) {
            return $this->json(['state' => 'invalid'], 404);
        }

        $sport = $team->getSport();
        if ($sport === null) {
            return $this->json(['state' => 'no_sport']);
        }
        if ($sport->getClub() === null || $member->getClub() === null
            || $sport->getClub()->getId() !== $member->getClub()->getId()) {
            return $this->json(['state' => 'club_mismatch']);
        }

        $existing = $em->getRepository(MemberSport::class)->findOneBy([
            'member' => $member,
            'sport' => $sport,
        ]);

        return $this->json([
            'state' => $existing === null ? 'missing' : 'present',
            'sport' => $sport->getName(),
        ]);
    }
}
