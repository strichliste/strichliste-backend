<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
class CreateUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'user.create.name_label',
            'attr' => [
                'autofocus' => true,
                'autocomplete' => 'off',
                'maxlength' => 64,
                'minlength' => 1,
            ],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(min: 1, max: 64),
                new Assert\Regex(pattern: '/[\x00-\x1F\x7F]/u', match: false, message: 'user.create.errors.invalid_name'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
