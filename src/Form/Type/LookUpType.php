<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class LookUpType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('search', TextType::class, [
                'required' => false,
            ])
            ->add('choice', ChoiceType::class, [
                'choices' => [
                    'All'=> 'all',
                    'movie.year' => 'year',
                    'movie.title' => 'title',
                    'movie.director' => 'director',
                ],
            ])
            ->add('fuzzy', SubmitType::class)
            ->add('save', SubmitType::class);
    }
}
