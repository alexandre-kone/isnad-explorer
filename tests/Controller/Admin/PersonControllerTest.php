<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Domain\PersonNameNormaliser;
use App\Entity\Enum\NameKind;
use App\Entity\Enum\NameScript;
use App\Entity\Person;
use App\Entity\PersonName;
use App\Entity\User;
use App\Repository\PeriodRepository;
use App\Tests\PreparesHadithDatabase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PersonControllerTest extends WebTestCase
{
    use PreparesHadithDatabase;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::primeHadithDatabase($this->em);
    }

    public function testCurationScreensRequireAuthentication(): void
    {
        $this->client->request('GET', '/admin/personnes');

        self::assertResponseRedirects('/admin/connexion');
    }

    public function testSearchFindsAPersonByAnyOfItsNames(): void
    {
        $this->signIn();
        $this->personWithNames('abu-hanifa-test', [
            ['Abū Ḥanīfa', NameKind::Kunya, true],
            ['al-Nuʿmān ibn Thābit', NameKind::Ism, false],
        ]);

        $this->client->request('GET', '/admin/personnes', ['q' => 'al-Numan ibn Thabit']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="person-count"]');
        // La fiche remonte sous sa forme d'affichage, la kunya.
        self::assertSelectorTextContains('body', 'Abū Ḥanīfa');
        // Et l'écran indique quelle forme a produit la correspondance.
        self::assertSelectorTextContains('body', 'correspondance sur');
    }

    public function testUnknownTermShowsNoResult(): void
    {
        $this->signIn();

        $this->client->request('GET', '/admin/personnes', ['q' => 'zzzintrouvable']);

        self::assertSelectorExists('[data-testid="no-person"]');
    }

    public function testPersonSheetListsEveryNameForm(): void
    {
        $this->signIn();
        $this->personWithNames('multi-noms', [
            ['Forme latine', NameKind::Complete, true],
            ['Autre forme', NameKind::Shuhra, false],
        ]);

        $this->client->request('GET', '/admin/personnes/multi-noms');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="name-list"]', 'Forme latine');
        self::assertSelectorTextContains('[data-testid="name-list"]', 'Autre forme');
    }

    /**
     * Deux fiches partageant une forme normalisée sont signalées.
     */
    public function testDuplicateScreenReportsSharedForms(): void
    {
        $this->signIn();
        $this->personWithNames('doublon-un', [['Sufyân al-Thawrî', NameKind::Complete, true]]);
        $this->personWithNames('doublon-deux', [['Sufyan al-Thawri', NameKind::Complete, true]]);

        $this->client->request('GET', '/admin/personnes/doublons');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="duplicate-list"]');
        // L'écran regroupe par forme normalisée, pas par slug.
        self::assertSelectorTextContains('[data-testid="duplicate-list"]', 'sufyan al-thawri');
    }

    public function testMergeScreenShowsImpactThenMerges(): void
    {
        $this->signIn();
        $this->personWithNames('garde', [['Fiche gardée', NameKind::Complete, true]]);
        $this->personWithNames('absorbe', [['Fiche absorbée', NameKind::Complete, true]]);

        $crawler = $this->client->request('GET', '/admin/personnes/absorbe/fusionner/garde');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="merge-impact"]');

        $this->client->submit($crawler->selectButton('Confirmer la fusion')->form());

        self::assertResponseRedirects('/admin/personnes/garde');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'a été fusionnée');
    }

    private function signIn(): void
    {
        $curator = new User('curateur@example.test', 'Curateur');
        $curator->setRoles(['ROLE_ADMIN'])->setPassword('peu-importe');
        $this->em->persist($curator);
        $this->em->flush();

        $this->client->loginUser($curator);
    }

    /**
     * @param list<array{0: string, 1: NameKind, 2: bool}> $forms
     */
    private function personWithNames(string $slug, array $forms): Person
    {
        $normaliser = new PersonNameNormaliser();
        $period = static::getContainer()->get(PeriodRepository::class)->findOrdered()[1];

        $person = new Person($slug, $period);
        foreach ($forms as [$form, $kind, $display]) {
            $name = new PersonName(
                $person,
                $form,
                $normaliser->normalise($form, NameScript::Latin),
                NameScript::Latin,
                $kind,
            );
            $name->setDisplay($display);
            $person->addName($name);
            $this->em->persist($name);
        }

        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }
}
