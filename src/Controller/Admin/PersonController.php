<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Domain\PersonDuplicateFinder;
use App\Domain\PersonMerger;
use App\Domain\PersonSearch;
use App\Entity\Person;
use App\Repository\PersonRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écrans de curation des transmetteurs.
 *
 * La saisie complète d'une fiche viendra avec les phases suivantes ; ici on
 * couvre ce dont dépend tout le reste : retrouver une personne par n'importe
 * lequel de ses noms, et réparer les doublons.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/personnes')]
final class PersonController extends AbstractController
{
    #[Route('', name: 'admin_person_index', methods: ['GET'])]
    public function index(Request $request, PersonSearch $search, PersonRepository $people): Response
    {
        $term = trim((string) $request->query->get('q', ''));

        return $this->render('admin/person/index.html.twig', [
            'term' => $term,
            'results' => '' === $term ? [] : $search->search($term, 25),
            'total' => $people->count([]),
        ]);
    }

    #[Route('/doublons', name: 'admin_person_duplicates', methods: ['GET'])]
    public function duplicates(PersonDuplicateFinder $finder): Response
    {
        return $this->render('admin/person/duplicates.html.twig', [
            'candidates' => $finder->findExactFormCollisions(),
        ]);
    }

    #[Route('/{slug}', name: 'admin_person_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Person $person): Response
    {
        return $this->render('admin/person/show.html.twig', ['person' => $person]);
    }

    /**
     * La fusion se fait en deux temps : un aperçu de l'impact, puis la
     * confirmation. L'opération est irréversible côté données — seul le journal
     * permet de la reconstituer.
     */
    #[Route('/{slug}/fusionner/{keptSlug}', name: 'admin_person_merge', methods: ['GET', 'POST'])]
    public function merge(
        Request $request,
        // Deux paramètres de route : le mappage doit être explicite, sinon
        // Symfony ne sait pas lequel désigne la fiche à absorber.
        #[MapEntity(mapping: ['slug' => 'slug'])] Person $person,
        string $keptSlug,
        PersonRepository $people,
        PersonMerger $merger,
    ): Response {
        $kept = $people->findOneBySlug($keptSlug);
        if (null === $kept) {
            throw $this->createNotFoundException('Fiche à conserver introuvable.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('merge'.$person->getSlug(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $log = $merger->merge($person, $kept, $this->getUser());
            $this->addFlash('success', \sprintf(
                '« %s » a été fusionnée dans « %s ».',
                $log->getAbsorbedLabel(),
                $kept->getDisplayName() ?? $kept->getSlug(),
            ));

            return $this->redirectToRoute('admin_person_show', ['slug' => $kept->getSlug()]);
        }

        return $this->render('admin/person/merge.html.twig', [
            'absorbed' => $person,
            'kept' => $kept,
            'impact' => $merger->preview($person, $kept),
        ]);
    }
}
