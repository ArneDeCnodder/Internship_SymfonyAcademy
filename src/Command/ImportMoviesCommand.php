<?php

namespace App\Command;

use App\Repository\GenreRepository;
use App\Repository\MovieRepository;
use App\Service\MovieImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import-movies',
    description: 'Imports movie data from TheMovieDb',
)]
class ImportMoviesCommand extends Command
{
    protected static $defaultName = 'app:import-movies';

    public function __construct(
        private MovieImportService $movieImportService,
        private MovieRepository $movieRepository,
        private GenreRepository $genreRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln([
            'Trying to get most popular movies',
            '=================================',
            '',
        ]);
        $movies = $this->movieImportService->getPopularMovies();
        if (!$movies) {
            $output->writeln('<fg=red>Oops, try to resolve the error!</>');
            $output->writeln('<fg=red>Aborting command</>');

            return Command::FAILURE;
        }
        $output->writeln('<fg=green>Success!</>');
        $output->writeln([
            '',
            'Trying to persist movies to database',
            '====================================',
            '',
        ]);
        foreach ($movies as $movie) {
            $addMovie = $this->movieImportService->setMovieEntity($movie);
            if (!$addMovie) {
                $output->writeln([
                    '',
                    "<fg=red>Oops, we couldn't add this movie to the database, skipping it!</>",
                    '',
                ]);
            }
        }
        $output->writeln('<fg=green>Success!</>');

        return Command::SUCCESS;
    }
}
