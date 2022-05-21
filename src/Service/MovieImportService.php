<?php

namespace App\Service;

use App\Entity\Genre;
use App\Entity\Movie;
use App\Message\SendMovieToMovieAdmin;
use App\Repository\GenreRepository;
use App\Repository\MovieRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MovieImportService
{
    public function __construct(
        private HttpClientInterface $client,
        private GenreRepository $genreRepository,
        private MovieRepository $movieRepository,
        private LoggerInterface $logger,
        private ContainerBagInterface $params,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function getPopularMovies(): ?array
    {
        try {
            $initialResponse = $this->client->request(
                'GET',
                $this->params->get('api.url').$this->params->get('api.popular'),
            );
            $content = $initialResponse->toArray();
            $results = $content['results'];

            foreach (range(2, 500) as $pageNumber) {
                $response = $this->client->request(
                    'GET',
                    $this->params->get('api.url').$this->params->get('api.popular').$pageNumber,
                );
                $contentPage = $response->toArray();
                $resultspage = $contentPage['results'];
                $results = array_merge($results, $resultspage);
            }

            return $results;
        } catch (HttpExceptionInterface $e) {
            $code = $e->getCode();
            $this->logger->error($e->getMessage(), [
                'status code' => $code,
            ]);

            return null;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());

            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
               'function' => 'getPopularMovies()',
               'Possible cause' => 'No internet connection',
           ]);

            return null;
        }
    }

    public function getDirector(int $movieId): ?string
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->params->get('api.url').'movie/'.$movieId.$this->params->get('api.director'),
            );
            $content = $response->toArray();
            $crew = $content['crew'];
            $director = '';
            foreach ($crew as $crewmember) {
                if ($crewmember['job'] === 'Director') {
                    $director = $crewmember['name'];
                }
            }

            return $director;
        } catch (HttpExceptionInterface $e) {
            $code = $e->getCode();
            $this->logger->error($e->getMessage(), [
                'status code' => $code,
            ]);

            return null;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());

            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'function' => 'getDirector()',
                'Possible cause' => 'No internet connection',
            ]);

            return null;
        }
    }

    public function getMovieDetails(int $movieId): ?array
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->params->get('api.url').'movie/'.$movieId.$this->params->get('api.details'),
            );
            $content = $response->toArray();
            $imdb = $content['imdb_id'];
            $genres = $content['genres'];
            $genre = '';
            if ($genres) {
                $genreId = $genres[0]['id'];
                $genre = $this->genreRepository->findOneBy(['apiId' => $genreId]) ?? new Genre();
                $genre->setApiId((int) $genres[0]['id']);
                $genre->setName($genres[0]['name']);
                $this->genreRepository->add($genre);
            }
            $posterPath = $content['poster_path'];

            return ['imdb' => $imdb, 'genre' => $genre,'posterPath' => $posterPath];
        } catch (HttpExceptionInterface $e) {
            $code = $e->getCode();
            $this->logger->error($e->getMessage(), [
                'status code' => $code,
            ]);

            return null;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());

            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'function' => 'getImdb()',
                'Possible cause' => 'No internet connection',
            ]);

            return null;
        }
    }

    public function setMovieEntity(array $movie): bool
    {
        try {
            $newMovie = $this->movieRepository->findOneBy(['apiId' => $movie['id']]) ?? new Movie();

            $director = $this->getDirector($movie['id']);

            $title = $movie['title'];
            $imdb = $this->getMovieDetails($movie['id'])['imdb'];
            $genre = $this->getMovieDetails($movie['id'])['genre'];
            $posterPath = $this->getMovieDetails($movie['id'])['posterPath'];

            if (!$movie['release_date'] or !$title or !$director) {
                $this->logger->error('movie '.$title.' not added', [
                    'Possible cause' => 'no year, title or director defined',
                ]);

                return false;
            }

            if ($imdb) {
                $newMovie->setImdb('https://www.imdb.com/title/'.$imdb);
            }
            if ($movie['overview']) {
                $description = $movie['overview'];
                $newMovie->setDescription($description);
            }
            if ($genre instanceof Genre) {
                $newMovie->setGenre($genre);
            }

            $year = substr($movie['release_date'], 0, 4);
            $newMovie->setYear($year);
            $newMovie->setTitle($title);
            $newMovie->setApiId($movie['id']);
            $newMovie->setDirector($director);
            if ($posterPath !== null) {
                $newMovie->setPoster('https://image.tmdb.org/t/p/original'.$posterPath);
            }
            $this->movieRepository->add($newMovie);
            $message = new SendMovieToMovieAdmin($newMovie->getId());
            $this->messageBus->dispatch($message);

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            return false;
        }
    }
}
