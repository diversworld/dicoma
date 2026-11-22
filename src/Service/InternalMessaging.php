<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\DivisionRoleAssignment;
use App\Entity\InternalMessage;
use App\Entity\InternalMessageRecipient;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\MembershipMessage;
use App\Entity\ContributionClass;
use App\Entity\CommunicationTemplate;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\DivisionRole;
use App\Enum\MemberSportStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class InternalMessaging
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    /** @return list<Member> */
    public function directChoices(Club $club): array
    {
        return $this->em->getRepository(Member::class)->createQueryBuilder('m')
            ->andWhere('m.club = :club')
            ->andWhere('m.membershipStatus = :active')
            ->andWhere('m.archivedAt IS NULL')
            ->setParameter('club', $club)
            ->setParameter('active', 'active')
            ->orderBy('m.person', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<Sport> */
    public function divisionChoices(): array
    {
        $user = $this->actor();
        $member = $user->getMember();
        $club = $member?->getClub();
        if ($member === null || $club === null) {
            return [];
        }
        $sports = $this->em->getRepository(Sport::class)->findBy(['club' => $club, 'active' => true], ['name' => 'ASC']);
        if ($this->canSendClubWide($user)) {
            return $sports;
        }
        return array_values(array_filter($sports, fn (Sport $sport): bool => $this->leadsDivision($member, $sport)));
    }

    /** @return array<string, string> */
    public function roleChoices(): array
    {
        if (!$this->canSendClubWide($this->actor())) {
            return [];
        }
        $choices = [];
        foreach (User::getAvailableRoles() as $label => $role) {
            if (!in_array($role, [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN], true)) {
                $choices[$label] = $role;
            }
        }
        return $choices;
    }

    public function send(
        User $sender,
        string $audience,
        string $subject,
        string $body,
        ?Member $member = null,
        ?Sport $division = null,
        ?string $role = null,
    ): InternalMessage {
        $senderMember = $sender->getMember();
        $club = $senderMember?->getClub();
        if ($senderMember === null || !$senderMember->isActiveMember() || $club === null) {
            throw new \DomainException('Aktive Mitgliedschaft erforderlich.');
        }
        $recipients = match ($audience) {
            'member' => $this->directRecipients($club, $member),
            'division' => $this->divisionRecipients($sender, $senderMember, $club, $division),
            'role' => $this->roleRecipients($sender, $club, $role),
            'all' => $this->allRecipients($sender, $club),
            'youth' => $this->categoryRecipients($sender, $club, 'youth'),
            'honorary' => $this->categoryRecipients($sender, $club, 'honorary'),
            'board' => $this->roleRecipients($sender, $club, User::ROLE_BOARD),
            default => throw new \DomainException('Ungültige Empfängergruppe.'),
        };
        if ($recipients === []) {
            throw new \DomainException('Für diese Empfängergruppe wurden keine aktiven Mitglieder gefunden.');
        }
        $message = new InternalMessage($club, $sender, $audience, $subject, $body);
        $message->setDivision($division);
        $message->setTargetRole($role);
        $this->em->persist($message);
        foreach ($recipients as $recipient) {
            $this->em->persist(new InternalMessageRecipient($message, $recipient));
        }
        $this->em->flush();
        foreach ($recipients as $recipient) {
            $email = $recipient->getEmail() ?: $recipient->getUser()?->getEmail();
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $key = sprintf('internal-message:%d:%d', $message->getId(), $recipient->getId());
                if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) === null) {
                    $personalizedSubject = strtr($message->getSubject(), $this->recipientValues($recipient));
                    $personalizedBody = strtr($message->getBody(), $this->recipientValues($recipient));
                    $campaign = $audience !== 'member';
                    $this->em->persist(new MembershipMessage(
                        $key,
                        $email,
                        $campaign ? $personalizedSubject : 'Neue DICOMA-Nachricht: '.$personalizedSubject,
                        $campaign ? $personalizedBody : "Du hast eine neue interne Nachricht von {$senderMember->getFullName()} erhalten.\n\nBetreff: {$personalizedSubject}\n\nBitte melde dich in DICOMA an und öffne deinen Posteingang.",
                    ));
                }
            }
        }
        $this->em->flush();
        return $message;
    }

    /** @return list<Member> */
    public function previewRecipients(User $sender, string $audience, ?Member $member = null, ?Sport $division = null, ?string $role = null): array
    {
        $senderMember = $sender->getMember();
        $club = $senderMember?->getClub();
        if ($senderMember === null || $club === null) { throw new \DomainException('Vereinszuordnung fehlt.'); }
        return match ($audience) {
            'member' => $this->directRecipients($club, $member),
            'division' => $this->divisionRecipients($sender, $senderMember, $club, $division),
            'role' => $this->roleRecipients($sender, $club, $role),
            'all' => $this->allRecipients($sender, $club),
            'youth' => $this->categoryRecipients($sender, $club, 'youth'),
            'honorary' => $this->categoryRecipients($sender, $club, 'honorary'),
            'board' => $this->roleRecipients($sender, $club, User::ROLE_BOARD),
            default => throw new \DomainException('Ungültige Empfängergruppe.'),
        };
    }

    public function renderTemplate(CommunicationTemplate $template, Member $member): array
    {
        $values = [
            '{{ firstname }}' => $member->getPerson()->getFirstname() ?? '',
            '{{ lastname }}' => $member->getPerson()->getLastname() ?? '',
            '{{ memberNumber }}' => $member->getMemberNumber() ?? '',
            '{{ club }}' => $member->getClub()?->getName() ?? '',
        ];
        return [strtr($template->getSubject(), $values), strtr($template->getBody(), $values)];
    }

    /** @return list<Member> */
    private function directRecipients(Club $club, ?Member $member): array
    {
        if ($member === null || $member->getClub()?->getId() !== $club->getId() || !$member->isActiveMember()) {
            throw new \DomainException('Der Empfänger gehört nicht zum aktiven Vereinsbestand.');
        }
        return [$member];
    }

    /** @return list<Member> */
    private function divisionRecipients(User $sender, Member $senderMember, Club $club, ?Sport $division): array
    {
        if ($division === null || $division->getClub()?->getId() !== $club->getId() || !$division->isActive()
            || (!$this->canSendClubWide($sender) && !$this->leadsDivision($senderMember, $division))) {
            throw new \DomainException('Kein berechtigter Spartenversand.');
        }
        $rows = $this->em->getRepository(MemberSport::class)->findBy([
            'sport' => $division, 'status' => MemberSportStatus::ACTIVE,
        ]);
        return array_values(array_filter(array_map(
            static fn (MemberSport $membership): ?Member => $membership->getMember(),
            $rows,
        ), static fn (?Member $member): bool => $member !== null && $member->isActiveMember()
            && $member->getClub()?->getId() === $club->getId()));
    }

    /** @return list<Member> */
    private function roleRecipients(User $sender, Club $club, ?string $role): array
    {
        $valid = in_array($role, User::getAvailableRoles(), true);
        if (!$this->canSendClubWide($sender) || !$valid || in_array($role, [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN], true)) {
            throw new \DomainException('Kein berechtigter Rollenversand.');
        }

        return array_values(array_filter($this->directChoices($club), static fn (Member $member): bool =>
            $member->getUser() !== null && in_array($role, $member->getUser()->getRoles(), true)
        ));
    }

    /** @return list<Member> */
    private function allRecipients(User $sender, Club $club): array
    {
        if (!$this->canSendClubWide($sender)) { throw new \DomainException('Kein berechtigter Gesamtversand.'); }
        return $this->directChoices($club);
    }

    /** @return list<Member> */
    private function categoryRecipients(User $sender, Club $club, string $category): array
    {
        if (!$this->canSendClubWide($sender)) { throw new \DomainException('Kein berechtigter Gruppenversand.'); }
        return array_values(array_filter($this->directChoices($club), static fn (Member $member): bool =>
            $member->getContributionClass()?->getMemberCategory() === $category
        ));
    }

    private function leadsDivision(Member $member, Sport $sport): bool
    {
        foreach ($this->em->getRepository(DivisionRoleAssignment::class)->findBy([
            'member' => $member, 'sport' => $sport, 'role' => DivisionRole::LEADER,
        ]) as $assignment) {
            if ($assignment->isAssignedOn(new \DateTimeImmutable('today'))) {
                return true;
            }
        }
        return false;
    }

    private function canSendClubWide(User $user): bool
    {
        return $this->security->isGranted(User::ROLE_ADMIN)
            || $this->security->isGranted(User::ROLE_BOARD)
            || $this->security->isGranted(User::ROLE_SUPER_ADMIN);
    }

    /** @return array<string, string> */
    private function recipientValues(Member $member): array
    {
        return [
            '{{ firstname }}' => $member->getPerson()->getFirstname() ?? '',
            '{{ lastname }}' => $member->getPerson()->getLastname() ?? '',
            '{{ memberNumber }}' => $member->getMemberNumber() ?? '',
            '{{ club }}' => $member->getClub()?->getName() ?? '',
        ];
    }

    private function actor(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \DomainException('Anmeldung erforderlich.');
        }
        return $user;
    }
}
