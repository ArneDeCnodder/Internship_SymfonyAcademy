<?php

namespace App\MessageHandler;

use App\Message\SendMovieToMovieAdmin;
use App\Repository\MovieRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SendMovieToMovieAdminHandler implements MessageHandlerInterface
{
    public function __construct(
        private MovieRepository $movieRepository,
        private HttpClientInterface $client,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendMovieToMovieAdmin $sendMovieToMovieAdmin)
    {
        try {
            $movie = $this->movieRepository->find($sendMovieToMovieAdmin->getMovieId());

            $this->client->request('PUT', 'https://postman-echo.com/put?', [
                'query' => [
                    'id' => $movie->getId(),
                    'title' => $movie->getTitle(),
                    'directors' => $movie->getDirector(),
                    'year' => $movie->getYear(),
                    'running_time' => '100',
                ],
            ]);
        } catch (HttpExceptionInterface $e) {
            $code = $e->getCode();
            $this->logger->error($e->getMessage(), [
                'status code' => $code,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'function' => 'getPopularMovies()',
                'Possible cause' => 'No internet connection',
            ]);
        }
    }
}
