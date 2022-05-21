<?php

namespace App\Message;

class SendMovieToMovieAdmin
{
    public function __construct(private int $movieId)
    {
    }

    public function getMovieId(): int
    {
        return $this->movieId;
    }
}
