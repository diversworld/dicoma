<?php
declare(strict_types=1);
namespace App\Command;
use App\Entity\TuvNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(name:'app:tuv:send-notifications',description:'Sendet vorgemerkte TÜV-Nachrichten')]
final class SendTuvNotificationsCommand extends Command {
	
	public function __construct(private readonly EntityManagerInterface $em,private readonly MailerInterface $mailer){
		parent::__construct();
	}
 
	protected function execute(InputInterface $input,OutputInterface $output):int {
  		$from=(string)($_SERVER['TUV_MAIL_FROM']??$_ENV['TUV_MAIL_FROM']??'');
  
		if(!filter_var($from,FILTER_VALIDATE_EMAIL)){
			$output->writeln('<error>TUV_MAIL_FROM ungültig.</error>');return Command::FAILURE;
		}
		
		$rows=$this->em->getRepository(TuvNotification::class)->findBy(['state'=>'pending'],['id'=>'ASC'],30);

		foreach($rows as $row){
			try{$this->mailer->send(
				(new Email())
					->from($from)
					->to($row->getRecipient())
					->subject($row->getSubject())
					->text($row->getBody()));

				$row->markSent();

				$this->em->flush();
			}
			catch(\Throwable $e){
				$output->writeln('<error>Nachricht #'.$row->getId().' fehlgeschlagen: '.$e->getMessage().'</error>');

				return Command::FAILURE;
			}
		}
		
 		$output->writeln(count($rows).' Nachrichten verarbeitet.');
		
		return Command::SUCCESS;
 	}
}
