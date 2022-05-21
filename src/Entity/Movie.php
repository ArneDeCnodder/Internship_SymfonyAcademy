<?php

namespace App\Entity;

use App\Repository\MovieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
class Movie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $title;

    #[ORM\Column(type: 'string', length: 4)]
    #[Assert\NotBlank]
    #[Assert\Length(
        min: 4,
        max: 4,
        exactMessage: 'Oops, the year seems wrong, must be {{ limit }} numbers!',
    )]
    private string $year;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $director;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imdb;

    #[ORM\ManyToOne(targetEntity: Genre::class)]
    private ?Genre $genre;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $apiId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $poster;

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getYear(): string
    {
        return $this->year;
    }

    public function setYear(string $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDirector(): string
    {
        return $this->director;
    }

    public function setDirector(string $director): self
    {
        $this->director = $director;

        return $this;
    }

    public function getImdb(): ?string
    {
        return $this->imdb;
    }

    public function setImdb(?string $imdb): self
    {
        $this->imdb = $imdb;

        return $this;
    }

    public function getGenre(): ?Genre
    {
        return $this->genre;
    }

    public function setGenre(?Genre $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getApiId(): ?int
    {
        return $this->apiId;
    }

    public function setApiId(?int $apiId): self
    {
        $this->apiId = $apiId;

        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): self
    {
        $this->poster = $poster;

        return $this;
    }
}
