<?php

namespace App\Controller;

use App\Entity\Genre;
use App\Form\Type\GenreType;
use App\Form\Type\LookUpType;
use App\Repository\GenreRepository;
use App\Repository\MovieRepository;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GenreController extends AbstractController
{
    public function __construct(
        private PaginatedFinderInterface $finder,
    ) {
    }

    #[Route(path: [
        'en' => '/genre/search',
        'nl' => '/genre/zoek',
    ], name: 'genre_index')]
    public function index(TranslatorInterface $translator,GenreRepository $genreRepository, PaginatorInterface $paginator, Request $request, string $slug = "")
    {
        $search = $request->query->get('search');
        $form = $this->createForm(LookUpType::class,['search'=>$search]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $searchValue = $form->getData()['search'];

            return $this->redirectToRoute('genre_index', ['search' => $searchValue]);
        }
        $results = $this->finder->createPaginatorAdapter('*'.$search.'*');
        $result = $paginator->paginate(
            $results,
            $request->query->getInt('page', 1), // page number
            15, // limit per page
        );

        $count = count($result);
        if (count($result) === 0){
            $this->addFlash('danger', $count." genres found for that specific search! Did you misspell something?");
        }

        return $this->renderForm('genre/index.html.twig', [
            'genres' => $result,
            'form' => $form,
            'button' => $translator->trans('movie.index.button'),
        ]);
    }

    #[Route(path: [
        'en' => '/genre/edit/{id}',
        'nl' => '/genre/bewerk/{id}',
    ], name: 'genre_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(TranslatorInterface $translator, int $id, GenreRepository $genreRepository, Request $request)
    {
        $genre = $genreRepository->find($id);

        if (!$genre instanceof Genre) {
            throw $this->createNotFoundException('No genre found for id '.$id);
        }

        $form = $this->createForm(GenreType::class, $genre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $genreRepository->add($form->getData());

            $this->addFlash('success', $translator->trans('genre.edit.success'));

            return $this->redirectToRoute('genre_index');
        }

        return $this->renderForm('genre/form.html.twig', [
            'form' => $form,
            'button' => $translator->trans('genre.edit.button'),
            'title' => $translator->trans('genre.edit.title'),
            'pageTitle' => $translator->trans('genre.edittitle'),
        ]);
    }

    #[Route(path: [
        'en' => '/genre/create',
        'nl' => '/genre/maak',
    ], name: 'genre_create')]
    public function create(TranslatorInterface $translator, Request $request, GenreRepository $genreRepository)
    {
        $form = $this->createForm(GenreType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $genreRepository->add($form->getData());

            $this->addFlash('success', $translator->trans('genre.create.success'));

            return $this->redirectToRoute('genre_index');
        }

        return $this->renderForm('genre/form.html.twig', [
            'form' => $form,
            'button' => $translator->trans('genre.create.button'),
            'title' => $translator->trans('genre.create.title'),
            'pageTitle' => $translator->trans('genre.createtitle'),
        ]);
    }

    #[Route(path: [
        'en' => '/genre/delete/{id}',
        'nl' => '/genre/verwijder/{id}',
    ], name: 'genre_delete_temp')]
    #[IsGranted('ROLE_USER')]
    public function deletePage(int $id, GenreRepository $genreRepository)
    {
        $genre = $genreRepository->find($id);
        if (!$genre instanceof Genre) {
            $this->addFlash('danger', 'We found no genre to delete!');

            return $this->redirectToRoute('genre_index');
        }

        return $this->render('genre/delete.html.twig', ['genre' => $genre]);
    }

    #[Route(path: [
        'en' => '/genre/remove/{id}',
        'nl' => '/genre/vernietig/{id}',
    ], name: 'genre_delete')]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id, GenreRepository $genreRepository, MovieRepository $movieRepository)
    {
        $genre = $genreRepository->find($id);

        if (!$genre instanceof Genre) {
            $this->addFlash('danger', 'We found no genre to delete!');

            return $this->redirectToRoute('genre_index');
        }
        $countRows = $movieRepository->count(['genre' => $genre]);

        if ($countRows !== 0) {
            $this->addFlash('danger', 'This genre is associated with a movie, unable to delete!');

            return $this->redirectToRoute('genre_delete_temp', ['id' => $id]);
        }

        $genreRepository->remove($genre);
        $this->addFlash('success', 'The genre was successfully deleted!');

        return $this->redirectToRoute('genre_index');
    }
}
