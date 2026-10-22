<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tuv_invoice_settings')]
class TuvInvoiceSettings
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(name: 'club_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Club $club;

    #[ORM\Column(name: 'tax_mode', length: 20)]
    private string $taxMode = 'unconfigured';

    #[ORM\Column(name: 'tax_number', length: 60, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(name: 'vat_id', length: 40, nullable: true)]
    private ?string $vatId = null;

    #[ORM\Column(name: 'invoice_note', type: 'text', nullable: true)]
    private ?string $invoiceNote = null;

    #[ORM\Column(name: 'member_agency_enabled', type: 'boolean', options: ['default' => false])]
    private bool $memberAgencyEnabled = false;

    public function isMemberAgencyEnabled(): bool { return $this->memberAgencyEnabled; }
    public function setMemberAgencyEnabled(bool $enabled): void { $this->memberAgencyEnabled = $enabled; }

    public function __construct(Club $club) { $this->club = $club; }
    public function getClub(): Club { return $this->club; }
    public function getTaxMode(): string { return $this->taxMode; }
    public function getTaxNumber(): ?string { return $this->taxNumber; }
    public function getVatId(): ?string { return $this->vatId; }
    public function getInvoiceNote(): ?string { return $this->invoiceNote; }
    public function configure(string $mode, ?string $number, ?string $vatId, ?string $note): void
    {
        if (!in_array($mode, ['small_business', 'standard'], true)) {
            throw new \InvalidArgumentException('Ungültige Besteuerungsart.');
        }
        $number = trim((string)$number); $vatId = trim((string)$vatId); $note = trim((string)$note);
        if (mb_strlen($number) > 60 || mb_strlen($vatId) > 40 || mb_strlen($note) > 2000) {
            throw new \InvalidArgumentException('Ein Eingabefeld überschreitet die zulässige Länge.');
        }
        if ($number === '' && $vatId === '') {
            throw new \InvalidArgumentException('Bitte Steuernummer oder Umsatzsteuer-ID angeben.');
        }
        $this->taxMode = $mode;
        $this->taxNumber = $number ?: null;
        $this->vatId = $vatId ?: null;
        $this->invoiceNote = $note ?: null;
    }
}
