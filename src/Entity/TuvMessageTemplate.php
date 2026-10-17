<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'tuv_message_template')]
class TuvMessageTemplate
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $kind;
    
	#[ORM\Column(length: 200)]
    private string $subject;
    
	#[ORM\Column(type: 'text')]
    private string $body;
    
	#[ORM\Column(type: 'boolean', options: ['default' => true])]
	private bool $enabled = true;

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; }
    public function __construct(string $kind, string $subject, string $body) { $this->kind=$kind; $this->subject=$subject; $this->body=$body; }
    public function getKind(): string { return $this->kind; }
    public function getSubject(): string { return $this->subject; }
    public function getBody(): string { return $this->body; }
    public function update(string $subject,string $body): void { $this->subject=$subject; $this->body=$body; }
}
