<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Sport;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamTraining;
use App\Entity\Tournament;
use App\Entity\TournamentDocument;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\TournamentPayment;
use App\Entity\TournamentPlayer;
use App\Entity\User;
use App\Enum\TeamType;
use App\Enum\TournamentStatus;
use App\Form\TeamMemberType;
use App\Form\TournamentType;
use App\Service\CompetitionAccess;
use App\Service\PrivateMemberDocumentStorage;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type as F;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Filesystem\Filesystem;

#[IsGranted('ROLE_USER')]
final class CompetitionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompetitionAccess $access,
        private readonly TournamentService $tournaments,
    ) {}

    #[Route('/admin/teams', name: 'competition_teams', methods: ['GET'])]
    public function teams(): Response
    {
        $this->access->require($this->access->isStaff());
        return $this->render('competition/teams.html.twig', [
            'teams' => $this->teamsForUser(), 'canCreate' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    /** @return list<Team> */
    private function teamsForUser(): array
    {
        return array_values(array_filter($this->em->getRepository(Team::class)->findBy([], ['name' => 'ASC']),
            fn (Team $team): bool => $this->access->managesTeam($team)));
    }

    #[Route('/admin/teams/new', name: 'competition_team_new', methods: ['GET', 'POST'], priority: 10)]
    #[Route('/admin/teams/{id}/edit', name: 'competition_team_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function teamEdit(Request $request, ?int $id = null): Response
    {
        $team = $id === null ? new Team() : $this->team($id);
        $this->access->require($id === null ? $this->isGranted('ROLE_ADMIN') : $this->access->managesTeam($team));
        $sports = array_values(array_filter($this->em->getRepository(Sport::class)->findAll(),
            fn (Sport $sport): bool => $sport->getClub() !== null && $this->access->managesClub($sport->getClub())));
        if ($id !== null && !$this->isGranted('ROLE_ADMIN')) { $sports = [$team->getSport()]; }
        $form = $this->createFormBuilder($team, ['action' => $this->generateUrl($id === null ? 'competition_team_new' : 'competition_team_edit', $id === null ? [] : ['id' => $id])])
            ->add('name', F\TextType::class, ['label' => 'Mannschaftsname'])
            ->add('sport', EntityType::class, ['class' => Sport::class, 'choices' => $sports, 'label' => 'Sportart', 'disabled' => $id !== null])
            ->add('type', F\EnumType::class, ['class' => TeamType::class, 'choice_label' => static fn (TeamType $t): string => $t->label(), 'label' => 'Typ'])
            ->add('description', F\TextareaType::class, ['label' => 'Beschreibung', 'required' => false])
            ->add('active', F\CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($team); $this->em->flush();
            return $this->redirectToRoute('competition_team', ['id' => $team->getId()]);
        }
        return $this->formPage('Mannschaft bearbeiten', $form, 'competition_teams');
    }

    #[Route('/admin/teams/{id}', name: 'competition_team', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function teamShow(int $id): Response
    {
        $team = $this->team($id);
        $this->access->require($this->access->managesTeam($team));
        return $this->render('competition/team.html.twig', [
            'team' => $team,
            'roster' => $this->em->getRepository(TeamMember::class)->findBy(['team' => $team], ['role' => 'ASC', 'id' => 'ASC']),
            'sessions' => $this->em->getRepository(TeamTraining::class)->findBy(['team' => $team], ['startsAt' => 'ASC']),
        ]);
    }

    #[Route('/admin/teams/{id}/roster/new', name: 'competition_roster_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[Route('/admin/teams/roster/{assignmentId}/edit', name: 'competition_roster_edit', requirements: ['assignmentId' => '\d+'], methods: ['GET', 'POST'])]
    public function roster(Request $request, ?int $id = null, ?int $assignmentId = null): Response
    {
        $assignment = $assignmentId === null ? (new TeamMember())->setTeam($this->team($id)) : $this->em->find(TeamMember::class, $assignmentId);
        if (!$assignment instanceof TeamMember || $assignment->getTeam() === null) { throw $this->createNotFoundException(); }
        $team = $assignment->getTeam();
        $this->access->require($this->access->managesTeam($team));
        $form = $this->createForm(TeamMemberType::class, $assignment, [
            'action' => $this->generateUrl($assignmentId === null ? 'competition_roster_new' : 'competition_roster_edit', $assignmentId === null ? ['id' => $id] : ['assignmentId' => $assignmentId]),
            'members' => $this->em->getRepository(Member::class)->findBy(['club' => $team->getSport()->getClub()]),
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($assignment); $this->em->flush();
            return $this->redirectToRoute('competition_team', ['id' => $team->getId()]);
        }
        return $this->formPage('Kaderzuordnung bearbeiten', $form, 'competition_team', ['id' => $team->getId()]);
    }

    #[Route('/admin/teams/{id}/training/new', name: 'competition_training_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[Route('/admin/teams/training/{sessionId}/edit', name: 'competition_training_edit', requirements: ['sessionId' => '\d+'], methods: ['GET', 'POST'])]
    public function training(Request $request, ?int $id = null, ?int $sessionId = null): Response
    {
        $session = $sessionId === null ? new TeamTraining($this->team($id)) : $this->em->find(TeamTraining::class, $sessionId);
        if (!$session instanceof TeamTraining) { throw $this->createNotFoundException(); }
        $this->access->require($this->access->managesTeam($session->getTeam()));
        $form = $this->createFormBuilder($session, ['action' => $this->generateUrl($sessionId === null ? 'competition_training_new' : 'competition_training_edit', $sessionId === null ? ['id' => $id] : ['sessionId' => $sessionId])])
            ->add('title', F\TextType::class, ['label' => 'Training', 'empty_data' => ''])
            ->add('startsAt', F\DateTimeType::class, ['label' => 'Beginn', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endsAt', F\DateTimeType::class, ['label' => 'Ende', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('location', F\TextType::class, ['label' => 'Trainingsort', 'empty_data' => ''])
            ->add('cancelled', F\CheckboxType::class, ['label' => 'Abgesagt', 'required' => false])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($session); $this->em->flush();
            return $this->redirectToRoute('competition_team', ['id' => $session->getTeam()->getId()]);
        }
        return $this->formPage('Training planen', $form, 'competition_team', ['id' => $session->getTeam()->getId()]);
    }

    #[Route('/admin/tournaments', name: 'competition_tournaments', methods: ['GET'])]
    public function tournaments(): Response
    {
        $this->access->require($this->access->isStaff());
        return $this->render('competition/tournaments.html.twig', [
            'tournaments' => array_filter($this->em->getRepository(Tournament::class)->findBy([], ['startsAt' => 'DESC']), $this->access->viewsTournament(...)),
            'canCreate' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/admin/tournaments/new', name: 'competition_tournament_new', methods: ['GET', 'POST'], priority: 10)]
    #[Route('/admin/tournaments/{id}/edit', name: 'competition_tournament_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function tournamentEdit(Request $request, ?int $id = null): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($id === null) {
            $clubs = array_values(array_filter($this->em->getRepository(Club::class)->findBy([], ['name' => 'ASC']), $this->access->managesClub(...)));
            $clubId = $request->query->getInt('club');
            $club = $clubId > 0 ? $this->em->find(Club::class, $clubId) : ($clubs[0] ?? null);
            if (!$club instanceof Club) { throw $this->createAccessDeniedException('Ein verwalteter Verein wird benötigt.'); }
            $this->access->require($this->access->managesClub($club));
            $tournament = new Tournament($club);
        } else { $tournament = $this->tournament($id); $clubs = []; }
        $this->access->require($this->access->managesClub($tournament->getClub()));
        return $this->em->getConnection()->transactional(function () use ($request, $tournament, $id, $clubs): Response {
            if ($id !== null && $request->isMethod('POST') && $this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                $this->em->lock($tournament, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($tournament);
            }
            if (!in_array($tournament->getStatus(), [TournamentStatus::DRAFT, TournamentStatus::OPEN], true)
                || ($id !== null && ($this->tournaments->entries($tournament) !== [] || $this->tournaments->matches($tournament) !== []))) {
                throw $this->createAccessDeniedException('Stammdaten und Wertungsregeln sind nach der ersten Meldung oder dem Turnierstart gesperrt.');
            }
            $form = $this->createForm(TournamentType::class, $tournament, [
                'action' => $this->generateUrl($id === null ? 'competition_tournament_new' : 'competition_tournament_edit', $id === null ? ['club' => $tournament->getClub()->getId()] : ['id' => $id]),
            ]);
            $form->add('version', F\HiddenType::class, ['mapped' => false, 'data' => (string) $tournament->getVersion()]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($form->get('version')->getData() !== (string) $tournament->getVersion()) {
                    $form->addError(new FormError('Das Turnier wurde zwischenzeitlich geändert. Bitte neu laden.'));
                } else {
                    $this->em->persist($tournament); $this->em->flush();
                    return $this->redirectToRoute('competition_tournament', ['id' => $tournament->getId()]);
                }
            }
            return $this->render('competition/form.html.twig', ['title' => 'Turnier bearbeiten', 'form' => $form,
                'backUrl' => $this->generateUrl('competition_tournaments'), 'clubs' => $clubs, 'selectedClub' => $tournament->getClub()]);
        });
    }

    #[Route('/admin/tournaments/{id}', name: 'competition_tournament', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function tournamentShow(int $id): Response
    {
        $tournament = $this->tournament($id);
        $this->access->require($this->access->viewsTournament($tournament));
        $manager = $this->access->managesClub($tournament->getClub());
        return $this->render('competition/tournament.html.twig', [
            'tournament' => $tournament, 'manager' => $manager,
            'entries' => $this->tournaments->entries($tournament), 'matches' => $this->tournaments->matches($tournament),
            'standings' => $tournament->getArchive()['standings'] ?? $this->tournaments->standings($tournament),
            'documents' => $this->em->getRepository(TournamentDocument::class)->findBy(['tournament' => $tournament]),
            'access' => $this->access,
            'rosterOpen' => $this->tournaments->rosterIsOpen($tournament),
        ]);
    }

    #[Route('/admin/tournaments/{id}/entry', name: 'competition_entry_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function entryNew(int $id, Request $request): Response
    {
        $tournament = $this->tournament($id);
        $this->access->require($this->access->viewsTournament($tournament));
        $this->access->require($this->tournaments->rosterIsOpen($tournament));
        $entry = new TournamentEntry($tournament);
        $manager = $this->access->managesClub($tournament->getClub());
        $builder = $this->createFormBuilder($entry, ['action' => $this->generateUrl('competition_entry_new', ['id' => $id])])
            ->add('team', EntityType::class, ['class' => Team::class, 'choices' => $this->teamsForUser(), 'required' => !$manager,
                'placeholder' => $manager ? 'Externe Mannschaft (ohne Vereinsanlage)' : 'Mannschaft auswählen', 'label' => 'Eigene Mannschaft'])
            ->add('name', F\TextType::class, ['label' => 'Meldename', 'empty_data' => ''])
            ->add('contactEmail', F\EmailType::class, ['label' => 'Kontaktadresse', 'required' => false]);
        if ($manager) { $builder->add('groupName', F\TextType::class, ['label' => 'Turniergruppe', 'empty_data' => '']); }
        $form = $builder->getForm()->handleRequest($request);
        if ($this->process($form, fn () => $this->tournaments->register($entry))) {
            return $this->redirectToRoute('competition_entry', ['id' => $entry->getId()]);
        }
        return $this->formPage('Mannschaft melden', $form, 'competition_tournament', ['id' => $id]);
    }

    #[Route('/admin/tournaments/entry/{id}', name: 'competition_entry', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function entryShow(TournamentEntry $entry, Request $request): Response
    {
        $this->access->require($this->access->managesEntry($entry));
        $rosterOpen = $this->tournaments->rosterIsOpen($entry->getTournament()) && !$entry->isWithdrawn();
        if ($request->isMethod('POST')) { $this->access->require($rosterOpen); }
        $player = new TournamentPlayer($entry);
        $builder = $this->createFormBuilder($player, ['action' => $this->generateUrl('competition_entry', ['id' => $entry->getId()])]);
        if ($entry->getTeam() !== null) {
            $builder->add('teamMember', EntityType::class, ['class' => TeamMember::class,
                'choices' => $this->em->getRepository(TeamMember::class)->findBy(['team' => $entry->getTeam()]),
                'choice_label' => static fn (TeamMember $m): string => $m->getMember()?->getFullName() ?? '',
                'label' => 'Spieler aus dem Kader', 'constraints' => [new Assert\NotNull()]]);
            $player->setName('Kaderspieler');
        } else {
            $builder->add('name', F\TextType::class, ['label' => 'Spielername', 'empty_data' => ''])
                ->add('position', F\TextType::class, ['label' => 'Spielerposition', 'required' => false])
                ->add('jerseyNumber', F\IntegerType::class, ['label' => 'Rückennummer', 'required' => false]);
        }
        $form = $builder->add('eligibilityEvidence', F\TextType::class, ['label' => 'Geprüfter Berechtigungsnachweis', 'empty_data' => ''])
            ->getForm()->handleRequest($request);
        if ($this->process($form, fn () => $this->tournaments->addPlayer($player))) { return $this->redirectToRoute('competition_entry', ['id' => $entry->getId()]); }
        return $this->render('competition/entry.html.twig', [
            'entry' => $entry, 'players' => $this->em->getRepository(TournamentPlayer::class)->findBy(['entry' => $entry]),
            'paid' => $this->tournaments->paidCents($entry),
            'payments' => $this->em->getRepository(TournamentPayment::class)->findBy(['entry' => $entry], ['id' => 'ASC']),
            'manager' => $this->access->managesClub($entry->getTournament()->getClub()), 'form' => $form,
            'rosterOpen' => $rosterOpen,
        ]);
    }

    #[Route('/admin/tournaments/entry/{id}/withdraw', name: 'competition_entry_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function withdraw(TournamentEntry $entry, Request $request): Response
    {
        $this->token($request, 'entry_withdraw_' . $entry->getId());
        $this->action(fn () => $this->tournaments->withdraw($entry));
        return $this->redirectToRoute('competition_tournament', ['id' => $entry->getTournament()->getId()]);
    }

    #[Route('/admin/tournaments/player/{id}/remove', name: 'competition_player_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function playerRemove(TournamentPlayer $player, Request $request): Response
    {
        $this->token($request, 'player_remove_' . $player->getId());
        $entryId = $player->getEntry()->getId();
        $this->action(fn () => $this->tournaments->removePlayer($player));
        return $this->redirectToRoute('competition_entry', ['id' => $entryId]);
    }

    #[Route('/admin/tournaments/{id}/match/new', name: 'competition_match_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[Route('/admin/tournaments/match/{matchId}/edit', name: 'competition_match_edit', requirements: ['matchId' => '\d+'], methods: ['GET', 'POST'])]
    public function matchEdit(Request $request, ?int $id = null, ?int $matchId = null): Response
    {
        $match = $matchId === null ? new TournamentMatch($this->tournament($id)) : $this->em->find(TournamentMatch::class, $matchId);
        if (!$match instanceof TournamentMatch) { throw $this->createNotFoundException(); }
        $tournament = $match->getTournament();
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $choices = array_filter($this->tournaments->entries($tournament), static fn (TournamentEntry $e): bool => !$e->isWithdrawn());
        $form = $this->createFormBuilder($match, ['action' => $this->generateUrl($matchId === null ? 'competition_match_new' : 'competition_match_edit', $matchId === null ? ['id' => $id] : ['matchId' => $matchId])])
            ->add('home', EntityType::class, ['class' => TournamentEntry::class, 'choices' => $choices, 'label' => 'Mannschaft 1'])
            ->add('away', EntityType::class, ['class' => TournamentEntry::class, 'choices' => $choices, 'label' => 'Gegner'])
            ->add('roundNumber', F\IntegerType::class, ['label' => 'Runde', 'constraints' => [new Assert\NotNull()],
                'setter' => static function (TournamentMatch $match, ?int $value): void { if ($value !== null) { $match->setRoundNumber($value); } }])
            ->add('durationMinutes', F\IntegerType::class, ['label' => 'Minuten je Spiel einschließlich Wechselzeit', 'constraints' => [new Assert\NotNull()],
                'setter' => static function (TournamentMatch $match, ?int $value): void { if ($value !== null) { $match->setDurationMinutes($value); } }])
            ->add('startsAt', F\DateTimeType::class, ['label' => 'Spielbeginn', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('location', F\TextType::class, ['label' => 'Spielfeld / Ort', 'required' => false])
            ->add('cancelled', F\CheckboxType::class, ['label' => 'Abgesagt', 'required' => false])
            ->getForm()->handleRequest($request);
        if ($this->process($form, fn () => $this->tournaments->saveMatch($match))) { return $this->redirectToRoute('competition_tournament', ['id' => $tournament->getId()]); }
        return $this->formPage('Spiel planen', $form, 'competition_tournament', ['id' => $tournament->getId()]);
    }

    #[Route('/admin/tournaments/match/{id}/result', name: 'competition_result', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function result(TournamentMatch $match, Request $request): Response
    {
        $this->access->require($this->access->managesClub($match->getTournament()->getClub()));
        $form = $this->createFormBuilder(['home' => $match->getHomeScore(), 'away' => $match->getAwayScore(), 'version' => $match->getVersion()], [
            'action' => $this->generateUrl('competition_result', ['id' => $match->getId()]),
        ])
            ->add('home', F\IntegerType::class, ['label' => $match->getHome()->getName(), 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 9999)]])
            ->add('away', F\IntegerType::class, ['label' => $match->getAway()->getName(), 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 0, max: 9999)]])
            ->add('reason', F\TextType::class, ['label' => 'Erfassungs- / Korrekturgrund', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('version', F\IntegerType::class, ['label' => 'Datensatzversion', 'attr' => ['readonly' => true], 'constraints' => [new Assert\Positive()]])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $this->tournaments->recordResult($match, $data['home'], $data['away'], $this->getUser()->getUserIdentifier(), $data['reason'], $data['version']);
                return $this->redirectToRoute('competition_tournament', ['id' => $match->getTournament()->getId()]);
            } catch (\DomainException $e) { $form->addError(new FormError($e->getMessage())); }
            catch (OptimisticLockException) { $form->addError(new FormError('Das Ergebnis wurde zwischenzeitlich geändert. Bitte neu laden.')); }
        }
        return $this->formPage('Ergebnis erfassen', $form, 'competition_tournament', ['id' => $match->getTournament()->getId()]);
    }

    #[Route('/admin/tournaments/{id}/generate', name: 'competition_generate', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function generate(int $id, Request $request): Response
    {
        $tournament = $this->tournament($id);
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $form = $this->createFormBuilder(['slotMinutes' => 20], ['action' => $this->generateUrl('competition_generate', ['id' => $id])])->add('slotMinutes', F\IntegerType::class, [
            'label' => 'Minuten je Spiel einschließlich Wechselzeit', 'constraints' => [new Assert\NotNull(), new Assert\Range(min: 5, max: 360)],
        ])->getForm()->handleRequest($request);
        if ($this->process($form, fn () => $this->tournaments->generate($tournament, $form->getData()['slotMinutes']))) {
            return $this->redirectToRoute('competition_tournament', ['id' => $id]);
        }
        return $this->formPage('Jeder-gegen-jeden-Spielplan erzeugen', $form, 'competition_tournament', ['id' => $id]);
    }

    #[Route('/admin/tournaments/{id}/reset-schedule', name: 'competition_reset_schedule', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resetSchedule(int $id, Request $request): Response
    {
        $tournament = $this->tournament($id);
        $this->token($request, 'tournament_reset_' . $id);
        $this->action(function () use ($tournament, $request): void {
            if (!$request->request->getBoolean('confirm')) { throw new \DomainException('Bitte das vollständige Entfernen des ungewerteten Spielplans bestätigen.'); }
            $this->tournaments->resetSchedule($tournament);
        });
        return $this->redirectToRoute('competition_tournament', ['id' => $id]);
    }

    #[Route('/admin/tournaments/{id}/status', name: 'competition_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(int $id, Request $request): Response
    {
        $tournament = $this->tournament($id);
        $this->token($request, 'tournament_status_' . $id);
        $next = TournamentStatus::tryFrom((string) $request->request->get('status'));
        if ($next === null) { throw $this->createNotFoundException('Unbekannter Turnierstatus.'); }
        $this->action(fn () => $this->tournaments->transition($tournament, $next));
        return $this->redirectToRoute('competition_tournament', ['id' => $id]);
    }

    #[Route('/admin/tournaments/entry/{id}/payment', name: 'competition_payment', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function payment(TournamentEntry $entry, Request $request): Response
    {
        $this->access->require($this->access->managesClub($entry->getTournament()->getClub()));
        $form = $this->createFormBuilder(null, ['action' => $this->generateUrl('competition_payment', ['id' => $entry->getId()])])
            ->add('amount', F\MoneyType::class, ['label' => 'Betrag (Erstattung negativ)', 'currency' => 'EUR', 'divisor' => 100, 'input' => 'integer',
                'constraints' => [new Assert\NotNull(), new Assert\Range(min: -99999999, max: 99999999)]])
            ->add('reference', F\TextType::class, ['label' => 'Eindeutiger Zahlungsbeleg', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->getForm()->handleRequest($request);
        if ($this->process($form, fn () => $this->tournaments->payment($entry, $form->getData()['amount'], $form->getData()['reference'], $this->getUser()->getUserIdentifier()))) {
            return $this->redirectToRoute('competition_entry', ['id' => $entry->getId()]);
        }
        return $this->formPage('Turniergebühr verbuchen', $form, 'competition_entry', ['id' => $entry->getId()]);
    }

    #[Route('/admin/tournaments/{id}/document', name: 'competition_document_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function upload(int $id, Request $request, PrivateMemberDocumentStorage $storage): Response
    {
        $tournament = $this->tournament($id);
        $this->access->require($this->access->managesClub($tournament->getClub()));
        $this->token($request, 'tournament_document_' . $id);
        if (in_array($tournament->getStatus(), [TournamentStatus::COMPLETED, TournamentStatus::CANCELLED], true)) { throw $this->createAccessDeniedException('Historische Dokumente sind gesperrt.'); }
        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile) { $this->addFlash('danger', 'Bitte eine Datei auswählen.'); }
        else {
            try {
                [$name, $mime, $size] = $storage->store($file);
                $saved = false;
                try {
                    $document = new TournamentDocument($tournament, mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), $name, $mime, $size);
                    $this->em->persist($document); $this->em->flush();
                    $saved = true;
                } finally {
                    if (!$saved) { (new Filesystem())->remove($storage->path($name)); }
                }
                $this->addFlash('success', 'Dokument hochgeladen.');
            } catch (\InvalidArgumentException $e) { $this->addFlash('danger', $e->getMessage()); }
        }
        return $this->redirectToRoute('competition_tournament', ['id' => $id]);
    }

    #[Route('/admin/tournaments/document/{id}', name: 'competition_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(TournamentDocument $document, PrivateMemberDocumentStorage $storage): BinaryFileResponse
    {
        $this->access->require($this->access->viewsTournament($document->getTournament()));
        $path = $storage->path($document->getStorageName());
        if (!is_file($path)) { throw $this->createNotFoundException('Die Dokumentdatei ist nicht verfügbar.'); }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $document->getMimeType());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getOriginalName());
        return $response;
    }

    private function team(?int $id): Team
    {
        return ($id !== null ? $this->em->find(Team::class, $id) : null) ?? throw $this->createNotFoundException('Mannschaft nicht gefunden.');
    }
    private function tournament(?int $id): Tournament
    {
        return ($id !== null ? $this->em->find(Tournament::class, $id) : null) ?? throw $this->createNotFoundException('Turnier nicht gefunden.');
    }
    private function token(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
    }
    private function formPage(string $title, FormInterface $form, string $backRoute, array $parameters = []): Response
    {
        return $this->render('competition/form.html.twig', ['title' => $title, 'form' => $form, 'backUrl' => $this->generateUrl($backRoute, $parameters)]);
    }
    private function process(FormInterface $form, callable $operation): bool
    {
        if (!$form->isSubmitted() || !$form->isValid()) { return false; }
        try { $operation(); return true; }
        catch (\DomainException $e) { $form->addError(new FormError($e->getMessage())); return false; }
    }
    private function action(callable $operation): void
    {
        try { $operation(); $this->addFlash('success', 'Änderung gespeichert.'); }
        catch (\DomainException $e) { $this->addFlash('danger', $e->getMessage()); }
    }
}
