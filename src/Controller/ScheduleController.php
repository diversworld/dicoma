<?php

namespace App\Controller;

use App\Entity\Schedule;
use App\Form\ScheduleType;
use App\Repository\ScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/schedule')]
class ScheduleController extends AbstractController
{

    #[Route('/', name: 'app_schedule_index', methods: ['GET'])]
    public function index(ScheduleRepository $scheduleRepository): Response
    {
        return $this->render('schedule/index.html.twig', [
            'schedules' => $scheduleRepository->findAll()
        ]);
    }

    #[Route('/calendar/{month?}', name: 'app_schedule_calendar', methods: ['GET'])]
    public function calendar(int $month = null, ScheduleRepository $scheduleRepository): Response
    {
        if ($month === null) {
            $currentDate = new \DateTime();
        } else {
            if ($month < 1) {
                $month = 12;
                $year = date('Y') - 1;
            } elseif ($month > 12) {
                $month = 1;
                $year = date('Y') + 1;
            } else {
                $year = date('Y');
            }
            $currentDate = new \DateTime("$year-$month-01");
        }
        $month = $currentDate->format('m');
        $year = $currentDate->format('Y');

        $calendar = $this->generateCalendar($month, $year);

        return $this->render('schedule/calendar.html.twig', [
            'schedules' => $scheduleRepository->findAll(),
            'calendar'  => $calendar,
            'currentDate' => $currentDate
        ]);
    }

    #[Route('/new', name: 'app_schedule_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $schedule = new Schedule();
        $form = $this->createForm(ScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //EntityManager
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $dateiName = $imageFile->getFilename(). '.' .$imageFile->guessClientExtension();
                $imageFile->move($this->getParameter('bilder_ordner'), $dateiName);
                $schedule->setImage($dateiName);
            }

            $entityManager->persist($schedule);
            $entityManager->flush();

            return $this->redirectToRoute('app_schedule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('schedule/new.html.twig', [
            'schedule' => $schedule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_schedule_show', methods: ['GET'])]
    public function show(Schedule $schedule): Response
    {
        return $this->render('schedule/show.html.twig', [
            'schedule' => $schedule,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_schedule_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Schedule $schedule, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_schedule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('schedule/edit.html.twig', [
            'schedule' => $schedule,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_schedule_delete', methods: ['POST'])]
    public function delete(Request $request, Schedule $schedule, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$schedule->getId(), $request->request->get('_token'))) {
            $entityManager->remove($schedule);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_schedule_index', [], Response::HTTP_SEE_OTHER);
    }

    public function generateCalendar(int $month, int $year): array
    {
        $startTime = mktime(0, 0, 0, $month, 1, $year);
        $firstDayOfMonth = date('N', $startTime);
        $numDays = date('t', $startTime);
        $datesMonth = [];

        // Adjust the first day of month and add empty days for preceding days
        if ($firstDayOfMonth > 1) {
            for ($i = 1; $i < $firstDayOfMonth; $i++) {
                $week[] = ' '; // or null, depending upon what you want to print in your template
            }
        }

        for ($i = 1; $i <= $numDays; $i++) {
            $time = mktime(0, 0, 0, $month, $i, $year);
            $dayOfWeek = date('N', $time);
            $week[] = date('Y-m-d', $time);

            // If it's Sunday, or we've reached the end of the month, push the week into the calendar
            if ($dayOfWeek == 7 || $i == $numDays) {
                // Add empty days for following days if we've reached the end of the month and it's not Sunday
                if ($i == $numDays && $dayOfWeek != 7) {
                    for ($j = $dayOfWeek; $j < 7; $j++) {
                        $week[] = ' '; // or null, depending upon what you want to print in your template
                    }
                }
                $datesMonth[] = $week;
                $week = [];  // start a new week
            }
        }

        return $datesMonth;
    }
}
