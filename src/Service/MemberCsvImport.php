<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;

final class MemberCsvImport
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return array{rows:int,created:int,duplicates:list<int>,errors:list<string>} */
    public function run(Club $club, string $path, bool $apply): array
    {
        if (!is_file($path) || !is_readable($path)) { throw new \DomainException('CSV-Datei fehlt oder ist nicht lesbar.'); }
        $handle = fopen($path, 'r');
        if ($handle === false) { throw new \DomainException('CSV-Datei konnte nicht geöffnet werden.'); }
        $first = fgets($handle);
        rewind($handle);
        if ($first === false) { throw new \DomainException('CSV-Datei ist leer.'); }
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($headers === false) { throw new \DomainException('CSV-Kopf fehlt.'); }
        $headers = array_map(static fn (mixed $value): string => trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF"), $headers);
        foreach (['firstname', 'lastname', 'memberNumber'] as $required) {
            if (!in_array($required, $headers, true)) { throw new \DomainException('Pflichtspalte '.$required.' fehlt.'); }
        }
        $result = ['rows' => 0, 'created' => 0, 'duplicates' => [], 'errors' => []];
        $seenNumbers = [];
        $seenEmails = [];
        $pending = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            ++$line;
            if ($values === [null] || $values === []) { continue; }
            ++$result['rows'];
            $row = array_combine($headers, array_pad($values, count($headers), null));
            if ($row === false) { $result['errors'][] = 'Zeile '.$line.': Spaltenanzahl stimmt nicht.'; continue; }
            $number = trim((string) ($row['memberNumber'] ?? ''));
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            if ($number === '' || trim((string) ($row['firstname'] ?? '')) === '' || trim((string) ($row['lastname'] ?? '')) === '') {
                $result['errors'][] = 'Zeile '.$line.': Vorname, Nachname und Mitgliedsnummer sind erforderlich.';
                continue;
            }
            $duplicate = isset($seenNumbers[$number]) || isset($seenEmails[$email]) && $email !== ''
                || $this->em->getRepository(Member::class)->count(['club' => $club, 'memberNumber' => $number]) > 0
                || ($email !== '' && $this->em->getRepository(Person::class)->count(['email' => $email]) > 0);
            if ($duplicate) { $result['duplicates'][] = $line; continue; }
            $seenNumbers[$number] = true;
            if ($email !== '') { $seenEmails[$email] = true; }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $result['errors'][] = 'Zeile '.$line.': E-Mail-Adresse ist ungültig.';
                continue;
            }
            try { $pending[] = $this->member($club, $row); } catch (\Throwable $exception) { $result['errors'][] = 'Zeile '.$line.': '.$exception->getMessage(); }
        }
        fclose($handle);
        if ($result['errors'] !== []) {
            return $result;
        }
        $result['created'] = count($pending);
        if (!$apply) {
            return $result;
        }
        $this->em->wrapInTransaction(function () use ($pending): void {
            foreach ($pending as $member) { $this->em->persist($member); }
            $this->em->flush();
        });
        return $result;
    }

    /** @param array<string,mixed> $row */
    private function member(Club $club, array $row): Member
    {
        $person = (new Person())
            ->setFirstname(trim((string) $row['firstname']))
            ->setLastname(trim((string) $row['lastname']))
            ->setEmail($this->nullable($row['email'] ?? null))
            ->setGender($this->nullable($row['gender'] ?? null))
            ->setMobile($this->nullable($row['mobile'] ?? null))
            ->setPhone($this->nullable($row['phone'] ?? null))
            ->setStreet($this->nullable($row['street'] ?? null))
            ->setPostalCode($this->nullable($row['postalCode'] ?? null))
            ->setCity($this->nullable($row['city'] ?? null));
        $person->setBirthday($this->date($row['birthday'] ?? null));
        $status = MembershipStatus::tryFrom(trim((string) ($row['status'] ?? MembershipStatus::ACTIVE->value)));
        if ($status === null) { throw new \DomainException('Unbekannter Mitgliedsstatus.'); }
        $member = (new Member())->setClub($club)->setPerson($person)->setMemberNumber(trim((string) $row['memberNumber']));
        $member->setMembershipStatus($status);
        $member->setJoinedAt($this->date($row['joinedAt'] ?? null));
        $member->setMembershipType($this->nullable($row['membershipType'] ?? null));
        return $member;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) { throw new \DomainException('Datum muss YYYY-MM-DD sein.'); }
        return $date;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
