<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\{MemberTankCheckBooking,TuvMessageTemplate};
use Doctrine\ORM\EntityManagerInterface;
final class TuvMessageRenderer
{
    public const LABELS = ['confirmation'=>'Buchungsbestätigung','status'=>'Statusinformation','pickup'=>'Abholinformation','cancellation'=>'Stornierungsinformation'];
    private const DEFAULTS = [
        'confirmation'=>['TÜV-Anmeldung #{buchung_id} bestätigt',"Guten Tag,\n\nIhre TÜV-Anmeldung #{buchung_id} wurde erfasst.\nStatus: {status}\nFlasche: {flasche}\n\nMit freundlichen Grüßen\nIhr Verein"],
        'status'=>['TÜV-Anmeldung #{buchung_id}: {status}',"Guten Tag,\n\nIhre TÜV-Anmeldung #{buchung_id} hat den Status: {status}.\nFlasche: {flasche}\n\nMit freundlichen Grüßen\nIhr Verein"],
        'pickup'=>['TÜV-Flasche zur Abholung – Anmeldung #{buchung_id}',"Guten Tag,\n\nIhre Flasche {flasche} zur TÜV-Anmeldung #{buchung_id} kann abgeholt werden.\n\nMit freundlichen Grüßen\nIhr Verein"],
        'cancellation'=>['TÜV-Anmeldung #{buchung_id}: Stornierung',"Guten Tag,\n\nIhre TÜV-Anmeldung #{buchung_id} wurde storniert.\n\nMit freundlichen Grüßen\nIhr Verein"],
    ];
    public function __construct(private readonly EntityManagerInterface $em) {}
    public function getTemplate(string $kind): array
    {
        if (!isset(self::LABELS[$kind])) { throw new \InvalidArgumentException('Unbekannter Nachrichtentyp.'); }
        $stored=$this->em->find(TuvMessageTemplate::class,$kind);
        return ['subject'=>$stored?->getSubject() ?? self::DEFAULTS[$kind][0], 'body'=>$stored?->getBody() ?? self::DEFAULTS[$kind][1], 'enabled'=>$stored?->isEnabled() ?? true];
    }
    public function save(string $kind,string $subject,string $body, bool $enabled = true): void
    {
        if (!isset(self::LABELS[$kind]) || trim($subject)==='' || trim($body)==='' || mb_strlen($subject)>200 || mb_strlen($body)>10000 || strpbrk($subject,"\r\n")!==false) {
            throw new \InvalidArgumentException('Betreff oder Nachrichtentext ungültig (max. 200 / 10000 Zeichen; Betreff einzeilig).');
        }
        $stored=$this->em->find(TuvMessageTemplate::class,$kind);
        if ($stored) { $stored->update($subject,$body); } else { $stored = new TuvMessageTemplate($kind,$subject,$body); $this->em->persist($stored); }
        $stored->setEnabled($enabled);
        $this->em->flush();
    }
    public function isEnabled(string $kind): bool
    {
        return (bool) $this->getTemplate($kind)['enabled'];
    }
    public function render(string $kind,MemberTankCheckBooking $booking): array
    {
        $template=$this->getTemplate($kind);
        $vars=['{buchung_id}'=>(string)$booking->getId(),'{status}'=>(string)$booking->getStatus(),'{flasche}'=>(string)$booking->getTankDisplay(),'{termin}'=>(string)$booking->getCheckDisplay()];
        return ['subject'=>strtr($template['subject'],$vars),'body'=>strtr($template['body'],$vars)];
    }
}
