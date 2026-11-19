<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\EquipmentItem;
use App\Entity\MembershipMessage;
use App\Entity\Regulator;
use App\Entity\Tank;
use App\Entity\User;
use App\Service\EquipmentInventory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:equipment:queue-inspection-reminders', description: 'Bereitet überfällige und binnen 30 Tagen fällige Geräteprüfungen vor.')]
final class QueueEquipmentInspectionRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentInventory $inventory,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+30 days');
        $queued = 0;
        $assets = [];
        foreach ([EquipmentItem::class, Regulator::class, Tank::class] as $class) {
            array_push($assets, ...$this->em->getRepository($class)->findAll());
        }
        $managers = [];
        foreach ($this->em->getRepository(User::class)->findAll() as $user) {
            $clubId = $user->getMember()?->getClub()?->getId();
            if ($clubId !== null && $user->getMember()?->isActiveMember()
                && in_array(User::ROLE_EQUIPMENT_MANAGER, $user->getRoles(), true)
                && filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
                $managers[$clubId][] = $user;
            }
        }
        foreach ($assets as $asset) {
            $club = $asset->getClub();
            $due = $this->inventory->dueAt($asset);
            if ($club === null || $due === null || $due->format('Y-m-d') > $until->format('Y-m-d')
                || !isset($managers[$club->getId()])) {
                continue;
            }
            foreach ($managers[$club->getId()] as $manager) {
                $key = sprintf('equipment-inspection:%d:%d:%s', $asset->getId(), $manager->getId(), $due->format('Y-m-d'));
                if ($this->em->getRepository(MembershipMessage::class)->findOneBy(['messageKey' => $key]) !== null) {
                    continue;
                }
                $this->em->persist(new MembershipMessage(
                    $key,
                    $manager->getEmail(),
                    'Geräteprüfung fällig: '.(string) $asset,
                    sprintf(
                        "Für „%s“ ist die Prüfung %s am %s fällig.\n\nBitte die Geräteakte in DICOMA prüfen.",
                        (string) $asset,
                        $due->format('Y-m-d') < $today->format('Y-m-d') ? 'überfällig' : 'demnächst',
                        $due->format('d.m.Y'),
                    ),
                ));
                ++$queued;
            }
        }
        $this->em->flush();
        $output->writeln(sprintf('%d Prüfhinweise vorbereitet.', $queued));
        return Command::SUCCESS;
    }
}
