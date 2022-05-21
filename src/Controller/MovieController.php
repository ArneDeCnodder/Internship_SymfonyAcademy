<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Form\Type\LookUpType;
use App\Form\Type\MovieType;
use App\Message\SendMovieToMovieAdmin;
use App\Repository\MovieRepository;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Knp\Component\Pager\PaginatorInterface;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MovieController extends AbstractController
{
    public function __construct(
        private PaginatedFinderInterface $finder,
    ) {
    }

    #[Route(path: [
        'en' => '/movie',
        'nl' => '/film',
    ], name: 'movie_index')]
    public function index(TranslatorInterface $translator, MovieRepository $movieRepository, Request $request, PaginatorInterface $paginator)
    {
        $search = $request->query->get('search');
        $choice = $request->query->get('choice');
        $form = $this->createForm(LookUpType::class, ['search' => $search, 'choice'=>$choice]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $searchValue = $form->getData()['search'];
            $choiceValue = $form->getData()['choice'];

            return $this->redirectToRoute('movie_index', ['search' => $searchValue, 'choice' => $choiceValue]);
        }

        //more specific search querries
        $checkYear = (int) $search;
        $boolQuery = new BoolQuery();
        $yearQuery = new MatchQuery();
        $directorQuery = new MatchQuery();
        $titleQuery = new MatchQuery();

        if ($choice === 'all') {
            //partly match
            $titleQuery->setFieldQuery('title', '*'.$search.'*');
            $titleQuery->setFieldFuzziness('title','AUTO');
            $boolQuery->addShould($titleQuery);

            $directorQuery->setFieldQuery('director', '*'.$search.'*');
            $directorQuery->setFieldFuzziness('director','AUTO');
            $boolQuery->addShould($directorQuery);
        } elseif ($choice=== 'title' ) {
            //exact match
            $search = strtolower($search);
            $titleQuery->setFieldQuery('title.raw', ucwords($search));
            $directorQuery->setFieldFuzziness('title.raw','AUTO');
            $boolQuery->addShould($titleQuery);
        } elseif ($choice === 'year'){
            $yearQuery->setFieldQuery('year', $checkYear);
            $boolQuery->addShould($yearQuery);
        } elseif ($choice=== 'director'){
            $search = strtolower($search);
            $directorQuery->setFieldQuery('director.raw', ucwords($search));
            $directorQuery->setFieldFuzziness('director.raw','AUTO');
            $boolQuery->addShould($directorQuery);

        }

        $results = $this->finder->createPaginatorAdapter($boolQuery);

        $result = $paginator->paginate(
            $results,
            $request->query->getInt('page', 1), // page number
            15, // limit per page
        );
        $count = count($result);
        if (count($result) === 0){
            $this->addFlash('danger', $count." movies found for that specific search! This is an exact search, did you misspell something? Try to search on 'All' movies for a greater search");
        }

        return $this->renderForm('movie/index.html.twig', [
            'movies' => $result,
            'form' => $form,
            'button' => $translator->trans('movie.index.button'),
            'buttonFuzzy' => "I'm feeling lucky",
        ]);
    }

    #[Route(path: [
        'en' => '/movie/edit/{id}',
        'nl' => '/film/bewerk/{id}',
    ], name: 'movie_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(TranslatorInterface $translator, ValidatorInterface $validator, int $id, MovieRepository $movieRepository, Request $request, MessageBusInterface $messageBus)
    {
        $movie = $movieRepository->find($id);

        if (!$movie instanceof Movie) {
            throw $this->createNotFoundException('No movie found for id '.$id);
        }

        $form = $this->createForm(MovieType::class, $movie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $movieRepository->add($form->getData());

            $this->addFlash('success', $translator->trans('movie.edit.success'));
            $message = new SendMovieToMovieAdmin($form->getData()->getId());
            $messageBus->dispatch($message);

            return $this->redirectToRoute('movie_index');
        }

        return $this->renderForm('movie/form.html.twig', [
            'form' => $form,
            'button' => $translator->trans('movie.edit.button'),
            'title' => $translator->trans('movie.edit.title').' '.$movie->getTitle(),
            'pageTitle' => $translator->trans('movie.edittitle'),
        ]);
    }

    #[Route(path: [
        'en' => '/movie/show/{id}',
        'nl' => '/film/toon/{id}',
    ], name: 'movie_show')]
    public function show(int $id, MovieRepository $movieRepository)
    {
        $movie = $movieRepository->find($id);
        if (!$movie instanceof Movie) {
            $this->addFlash('danger', 'We found no movie to show with that id!');

            return $this->redirectToRoute('movie_index');
        }

        return $this->render('movie/show.html.twig', ['movie' => $movie]);
    }

    #[Route(path: [
        'en' => '/movie/create',
        'nl' => '/film/maak',
    ], name: 'movie_create')]
    public function create(TranslatorInterface $translator, Request $request, MovieRepository $movieRepository, MessageBusInterface $messageBus)
    {
        $form = $this->createForm(MovieType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $movieRepository->add($form->getData());

            $this->addFlash('success', $translator->trans('movie.create.success'));
            $message = new SendMovieToMovieAdmin($form->getData()->getId());
            $messageBus->dispatch($message);

            return $this->redirectToRoute('movie_index');
        }

        return $this->renderForm('movie/form.html.twig', [
            'form' => $form,
            'button' => $translator->trans('movie.create.button'),
            'title' => $translator->trans('movie.create.title'),
            'pageTitle' => $translator->trans('movie.createtitle'),
        ]);
    }

    #[Route(path: [
        'en' => '/movie/delete/{id}',
        'nl' => '/film/verwijder/{id}',
    ], name: 'movie_delete_temp')]
    #[IsGranted('ROLE_USER')]
    public function deletePage(int $id, MovieRepository $movieRepository)
    {
        $movie = $movieRepository->find($id);
        if (!$movie instanceof Movie) {
            $this->addFlash('danger', 'We found no movie to delete!');

            return $this->redirectToRoute('movie_index');
        }

        return $this->render('movie/delete.html.twig', ['movie' => $movie]);
    }

    #[Route(path: [
        'en' => '/movie/remove/{id}',
        'nl' => '/film/vernietig/{id}',
    ], name: 'movie_delete')]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id, MovieRepository $movieRepository)
    {
        $movie = $movieRepository->find($id);
        if (!$movie instanceof Movie) {
            $this->addFlash('danger', 'We found no movie to delete!');

            return $this->redirectToRoute('movie_index');
        }

        $movieRepository->remove($movie);
        $this->addFlash('success', 'The movie was successfully deleted!');

        return $this->redirectToRoute('movie_index');
    }

    #[Route(path: [
        'en' => '/test',
        'nl' => '/test',
    ], name: 'test')]
    public function test()
    {
        $cijfer = 'Hopelijk ga je dit niet raden';
        $bytes = md5($cijfer);
        $hash = password_hash($cijfer,PASSWORD_BCRYPT);

        return $this->render('movie/test.html.twig', ['cijfer' => $cijfer, 'bytes' => $bytes,'hash'=> $hash]);
    }

    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
